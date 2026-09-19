import hashlib
import json
import uuid

from fastapi import Depends, FastAPI, Header, HTTPException
from sqlalchemy.orm import Session

from app import schemas
from shared import models
from shared.database import Base, engine, get_db
from shared.redis_client import redis_client

Base.metadata.create_all(bind=engine)

app = FastAPI(title="Ordering Service")

IDEMPOTENCY_TTL_SECONDS = 5 * 60


def idempotency_redis_key(idempotency_key: str) -> str:
    return f"idempotency:{idempotency_key}"


@app.get("/")
def index():
    return {"status": "OK"}

@app.post("/orders", response_model=schemas.OrderOut, status_code=201)
def create_order(
    order_in: schemas.OrderCreate,
    db: Session = Depends(get_db),
    idempotency_key: str = Header(alias="Idempotency-Key"),
):
    if not order_in.items:
        raise HTTPException(
            status_code=422, detail="Order must contain at least one item"
        )

    # sha256 of the exact request body - lets us tell "same retry" apart
    # from "this key was reused for a different order" (a client bug).
    request_hash = hashlib.sha256(
        order_in.model_dump_json().encode("utf-8")
    ).hexdigest()
    redis_key = idempotency_redis_key(idempotency_key)

    # set(nx=True) is Redis's atomic "set only if this key doesn't already
    # exist" - only one of any concurrent requests sharing the same key can
    # win this claim, so this replaces the MySQL unique constraint +
    # IntegrityError we used to rely on for that race.
    claimed = redis_client.set(
        redis_key,
        json.dumps({"status": "processing"}),
        nx=True,
        ex=IDEMPOTENCY_TTL_SECONDS,
    )
    if not claimed:
        existing = json.loads(redis_client.get(redis_key))
        if existing["status"] == "processing":
            raise HTTPException(
                status_code=409,
                detail="A request with this Idempotency-Key is still being processed",
            )
        if existing["request_hash"] != request_hash:
            raise HTTPException(
                status_code=409,
                detail="Idempotency-Key was already used with a different request body",
            )
        return db.get(models.Order, existing["order_id"])

    order = models.Order(
        id=str(uuid.uuid4()), customer_id=order_in.customer_id, status="PENDING"
    )
    for item in order_in.items:
        order.items.append(
            models.OrderItem(product_id=item.product_id, quantity=item.quantity)
        )

    db.add(order)

    outbox_payload = {
        "order_id": order.id,
        "customer_id": order.customer_id,
        "items": [
            {"product_id": item.product_id, "quantity": item.quantity}
            for item in order.items
        ],
    }
    outbox_message = models.OutboxMessage(
        aggregate_type="Order",
        aggregate_ref_id=f"order-{order.id}",
        event_type="OrderCreated",
        payload=json.dumps(outbox_payload),
        status="pending",
    )
    db.add(outbox_message)

    try:
        db.commit()
    except Exception:
        db.rollback()
        # Release the claim so a retry isn't blocked behind a 5-minute
        # TTL for a request that actually failed, not one still running.
        redis_client.delete(redis_key)
        raise

    db.refresh(order)

    redis_client.set(
        redis_key,
        json.dumps(
            {
                "status": "completed",
                "order_id": order.id,
                "request_hash": request_hash,
            }
        ),
        ex=IDEMPOTENCY_TTL_SECONDS,
    )

    return order
