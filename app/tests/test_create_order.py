import json
import uuid

from shared.models import Order, OrderItem, OutboxMessage


def test_valid_order_is_saved_with_outbox_message(client, db_session):
    # pytest passes fixtures (from conftest.py) by matching parameter names.
    order_payload = {
        "customer_id": "123",
        "items": [
            {"product_id": "ABC", "quantity": 2},
            {"product_id": "XYZ", "quantity": 1},
        ],
    }

    response = client.post(
        "/orders",
        json=order_payload,
        headers={"Idempotency-Key": str(uuid.uuid4())},
    )

    # Response
    assert response.status_code == 201
    response_body = response.json()
    order_id = response_body["id"]
    assert response_body["customer_id"] == "123"
    assert response_body["status"] == "PENDING"

    # orders table
    orders = db_session.query(Order).all()
    assert len(orders) == 1
    assert orders[0].id == order_id
    assert orders[0].customer_id == "123"
    assert orders[0].status == "PENDING"

    # order_items table
    items = (
        db_session.query(OrderItem)
        .filter(OrderItem.order_id == order_id)
        .order_by(OrderItem.product_id)
        .all()
    )
    assert len(items) == 2
    assert items[0].product_id == "ABC"
    assert items[0].quantity == 2
    assert items[1].product_id == "XYZ"
    assert items[1].quantity == 1

    # outbox_messages table - the OrderCreated event, saved in the same
    # transaction as the order
    outbox_messages = db_session.query(OutboxMessage).all()
    assert len(outbox_messages) == 1
    outbox_message = outbox_messages[0]
    assert outbox_message.aggregate_type == "Order"
    assert outbox_message.aggregate_ref_id == f"order-{order_id}"
    assert outbox_message.event_type == "OrderCreated"
    assert outbox_message.status == "pending"
    assert json.loads(outbox_message.payload) == {
        "order_id": order_id,
        "customer_id": "123",
        "items": [
            {"product_id": "ABC", "quantity": 2},
            {"product_id": "XYZ", "quantity": 1},
        ],
    }
