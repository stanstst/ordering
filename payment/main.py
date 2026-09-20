import json
import logging
import os
import random
import time

from kafka import KafkaConsumer
from sqlalchemy.dialects.mysql import insert as mysql_insert

from shared.database import SessionLocal
from shared.models import OrderStepResult

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("payment-service")

KAFKA_BROKER = os.environ.get("KAFKA_BROKER", "kafka:9092")
KAFKA_TOPIC = os.environ.get("KAFKA_TOPIC", "order-events")
CONSUMER_GROUP = "payment-service"

# Standing in for real network latency to an external Payment API that
# doesn't exist yet - a random one of these is picked per call.
SIMULATED_API_LATENCY_SECONDS = [1, 2, 3]


def call_payment_api(order_id: str, items: list) -> bool:
    """Stand-in for a real HTTP call to an external Payment gateway.

    No real charge happens - always "succeeds" after a simulated delay.
    Swap this for an actual request once that gateway integration exists.
    """
    delay = random.choice(SIMULATED_API_LATENCY_SECONDS)
    logger.info("Calling payment API for order %s (delay=%ss) ...", order_id, delay)
    time.sleep(delay)
    return True


def process_order_created(db, event: dict) -> None:
    order_id = event["data"]["order_id"]
    items = event["data"]["items"]
    message_id = event["id"]

    success = call_payment_api(order_id, items)
    status = "success" if success else "failed"

    # "INSERT ... ON DUPLICATE KEY UPDATE" - MySQL's atomic upsert. Inserts
    # a new (order_id, "payment") row, or updates it in place if one
    # already exists - makes this idempotent under Kafka's at-least-once
    # redelivery without a separate SELECT-then-write race.
    db_query = mysql_insert(OrderStepResult).values(
        order_id=order_id, step="payment", status=status, message_id=message_id
    )
    db_query = db_query.on_duplicate_key_update(
        status=db_query.inserted.status, message_id=db_query.inserted.message_id
    )

    db.execute(db_query)
    db.commit()

    logger.info(
        "Recorded payment step result for order %s: %s (message_id=%s)",
        order_id,
        status,
        message_id,
    )


def main():
    logger.info(
        "Starting payment service (broker=%s, topic=%s, group=%s)",
        KAFKA_BROKER,
        KAFKA_TOPIC,
        CONSUMER_GROUP,
    )

    consumer = KafkaConsumer(
        KAFKA_TOPIC,
        bootstrap_servers=KAFKA_BROKER,
        group_id=CONSUMER_GROUP,
        # Only commit our position in the topic AFTER the DB write has
        # committed - if we crash in between, the OrderCreated message gets
        # redelivered and the upsert above handles it idempotently, instead
        # of us silently losing the result.
        enable_auto_commit=False,
        auto_offset_reset="earliest",
    )

    for record in consumer:
        headers = dict(record.headers or [])
        event_type = headers.get("event_type", b"").decode("utf-8")
        if event_type != "OrderCreated":
            # Cheap filter: skip anything that isn't OrderCreated without
            # even parsing the JSON value.
            consumer.commit()
            continue

        event = json.loads(record.value.decode("utf-8"))
        db = SessionLocal()
        try:
            process_order_created(db, event)
            consumer.commit()
        except Exception:
            logger.exception(
                "Failed to process order %s", event.get("data", {}).get("order_id")
            )
            db.rollback()
            # Don't commit the offset - this message will be redelivered.
        finally:
            db.close()


if __name__ == "__main__":
    main()
