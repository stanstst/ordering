import json
import logging
import os
import random
import time

from kafka import KafkaConsumer, TopicPartition
from sqlalchemy.dialects.mysql import insert as mysql_insert

from inventory.circuit_breaker import CircuitBreaker, CircuitOpenError
from shared.database import SessionLocal
from shared.models import OrderStepResult
from shared.redis_client import redis_client

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("inventory-service")

KAFKA_BROKER = os.environ.get("KAFKA_BROKER", "kafka:9092")
KAFKA_TOPIC = os.environ.get("KAFKA_TOPIC", "order-events")
CONSUMER_GROUP = "inventory-service"

# Fraction of calls (0.0 - 1.0) where the fake API raises a technical error,
# so the circuit breaker can be exercised locally.
INVENTORY_API_FAILURE_RATE = float(os.environ.get("INVENTORY_API_FAILURE_RATE", "0"))

# How long to wait before retrying the same message.
CIRCUIT_OPEN_RETRY_SECONDS = 5
API_ERROR_RETRY_SECONDS = 2

inventory_api_breaker = CircuitBreaker(
    name="inventory-api",
    redis_client=redis_client,
    failure_threshold=5,
    failure_window_seconds=60,
    cooldown_seconds=30,
)


class InventoryApiError(Exception):
    """Technical failure talking to the Inventory API (timeout, 5xx, ...).

    Different from a business "not in stock" answer, which is a normal
    False result and does not count towards the circuit breaker.
    """


def call_inventory_api(order_id: str, items: list) -> bool:
    """Stand-in for a real HTTP call to an external Inventory service.

    No local stock tracking anymore - always "succeeds" after a simulated
    delay. Swap this for an actual request once that service exists.
    """
    time.sleep(random.choice([1, 2, 3]))
    if random.random() < INVENTORY_API_FAILURE_RATE:
        raise InventoryApiError("simulated Inventory API outage")
    return True


def process_order_created(db, event: dict) -> None:
    order_id = event["data"]["order_id"]
    items = event["data"]["items"]
    message_id = event["id"]

    # Raises CircuitOpenError (API not called) or InventoryApiError - both
    # propagate to main(), which retries the same message later.
    success = inventory_api_breaker.call(call_inventory_api, order_id, items)
    status = "success" if success else "failed"

    db_query = mysql_insert(OrderStepResult).values(
        order_id=order_id, step="inventory", status=status, message_id=message_id
    )
    db_query = db_query.on_duplicate_key_update(
        status=db_query.inserted.status, message_id=db_query.inserted.message_id
    )

    db.execute(db_query)
    db.commit()

    logger.info(
        "Recorded inventory step result for order %s: %s (message_id=%s)",
        order_id,
        status,
        message_id,
    )


def main():
    logger.info(
        "Starting inventory service (broker=%s, topic=%s, group=%s)",
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
        order_id = event.get("data", {}).get("order_id")
        db = SessionLocal()
        try:
            process_order_created(db, event)
            consumer.commit()
        except (CircuitOpenError, InventoryApiError) as error:
            db.rollback()
            if isinstance(error, CircuitOpenError):
                retry_seconds = CIRCUIT_OPEN_RETRY_SECONDS
            else:
                retry_seconds = API_ERROR_RETRY_SECONDS
            logger.warning(
                "Order %s not processed (%s), retrying in %ss",
                order_id,
                error,
                retry_seconds,
            )
            # Skipping commit() alone is not enough: the "for record in
            # consumer" loop would still move on to the next message, and
            # that message's commit() would move our position past this one.
            # seek() rewinds this partition so the same message comes back.
            consumer.seek(TopicPartition(record.topic, record.partition), record.offset)
            time.sleep(retry_seconds)
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
