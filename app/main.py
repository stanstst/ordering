import json
import uuid

from fastapi import Depends, FastAPI, HTTPException
from sqlalchemy.orm import Session

from app import models, schemas
from app.database import Base, engine, get_db

Base.metadata.create_all(bind=engine)

app = FastAPI(title="Ordering Service")

@app.get("/")
def index():
    return {"status": "OK"}

@app.post("/orders", response_model=schemas.OrderOut, status_code=201)
def create_order(order_in: schemas.OrderCreate, db: Session = Depends(get_db)):
    if not order_in.items:
        raise HTTPException(
            status_code=422, detail="Order must contain at least one item"
        )

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

    db.commit()
    db.refresh(order)

    return order
