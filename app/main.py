import hashlib
import json
import uuid
from enum import StrEnum
from http import HTTPStatus

from fastapi import Depends, FastAPI, Header, HTTPException
from sqlalchemy.orm import Session

from app import schemas
from shared import models
from shared.database import Base, engine, get_db
from shared.enums import AggregateType, EventType, OrderStatus, OutboxStatus
from shared.redis_client import redis_client

Base.metadata.create_all(bind=engine)

app = FastAPI(title="Ordering Service")

IDEMPOTENCY_TTL_SECONDS = 5 * 60
IDEMPOTENCY_KEY_HEADER = "Idempotency-Key"
IDEMPOTENCY_KEY_PREFIX = "idempotency:"
ORDER_REF_PREFIX = "order-"


class IdempotencyStatus(StrEnum):
    PROCESSING = "processing"
    COMPLETED = "completed"


def build_idempotency_redis_key(idempotency_key: str) -> str:
    return f"{IDEMPOTENCY_KEY_PREFIX}{idempotency_key}"


@app.get("/")
def get_health_status():
    return {"status": "OK"}

@app.post("/orders", response_model=schemas.OrderOut, status_code=HTTPStatus.CREATED)
def create_order(
    order_request: schemas.OrderCreate,
    db_session: Session = Depends(get_db),
    idempotency_key: str = Header(alias=IDEMPOTENCY_KEY_HEADER),
):
    if not order_request.items:
        raise HTTPException(
            status_code=HTTPStatus.UNPROCESSABLE_ENTITY, detail="Order must contain at least one item"
        )

    request_hash = hashlib.sha256(
        order_request.model_dump_json().encode("utf-8")
    ).hexdigest()
    redis_key = build_idempotency_redis_key(idempotency_key)

    is_claimed = redis_client.set(
        redis_key,
        json.dumps({"status": IdempotencyStatus.PROCESSING}),
        nx=True,
        ex=IDEMPOTENCY_TTL_SECONDS,
    )
    if not is_claimed:
        existing_claim = json.loads(redis_client.get(redis_key))
        if existing_claim["status"] == IdempotencyStatus.PROCESSING:
            raise HTTPException(
                status_code=HTTPStatus.CONFLICT,
                detail="A request with this Idempotency-Key is still being processed",
            )
        if existing_claim["request_hash"] != request_hash:
            raise HTTPException(
                status_code=HTTPStatus.CONFLICT,
                detail="Idempotency-Key was already used with a different request body",
            )
        return db_session.get(models.Order, existing_claim["order_id"])

    order = models.Order(
        id=str(uuid.uuid4()),
        customer_id=order_request.customer_id,
        status=OrderStatus.PENDING,
    )
    for item in order_request.items:
        order.items.append(
            models.OrderItem(product_id=item.product_id, quantity=item.quantity)
        )

    db_session.add(order)

    outbox_payload = {
        "order_id": order.id,
        "customer_id": order.customer_id,
        "items": [
            {"product_id": item.product_id, "quantity": item.quantity}
            for item in order.items
        ],
    }
    outbox_message = models.OutboxMessage(
        aggregate_type=AggregateType.ORDER,
        aggregate_ref_id=f"{ORDER_REF_PREFIX}{order.id}",
        event_type=EventType.ORDER_CREATED,
        payload=json.dumps(outbox_payload),
        status=OutboxStatus.PENDING,
    )
    db_session.add(outbox_message)

    try:
        db_session.commit()
    except Exception:
        db_session.rollback()
        # Release the claim so a retry isn't blocked behind a 5-minute
        # TTL for a request that actually failed, not one still running.
        redis_client.delete(redis_key)
        raise

    db_session.refresh(order)

    redis_client.set(
        redis_key,
        json.dumps(
            {
                "status": IdempotencyStatus.COMPLETED,
                "order_id": order.id,
                "request_hash": request_hash,
            }
        ),
        ex=IDEMPOTENCY_TTL_SECONDS,
    )

    return order
