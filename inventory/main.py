import json
import logging
import os
import random
import time

import redis
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

INVENTORY_API_FAILURE_RATE = 1 / 3
INVENTORY_API_FAILURE_STATUS = 503

# How long to wait before retrying the same message.
CIRCUIT_OPEN_RETRY_SECONDS = 5
API_ERROR_RETRY_SECONDS = 2

# Unexpected errors (DB error, bad payload, bug, ...): retry the same message
# with growing waits (2s, 4s, 8s, 16s), then give up - record the step as
# failed and commit, so one bad message can't block its partition forever.
MAX_ATTEMPTS = 5
ERROR_RETRY_BASE_SECONDS = 2
# Attempt counters live in Redis; the TTL cleans up counters of messages
# that later succeeded.
ATTEMPT_COUNTER_TTL_SECONDS = 60 * 60

inventory_api_breaker = CircuitBreaker(
    name="inventory-api",
    redis_client=redis_client,
    failure_threshold=5,
    failure_window_seconds=60,
    cooldown_seconds=30,
)


class InventoryApiError(Exception):
    """Failure talking to the Inventory API.

    status_code is the HTTP status, or None when there was no response
    (network error, timeout). Different from a business "not in stock"
    answer, which is a normal False result.

    Simplified handling for now:
      - None / 5xx: retried, counted by the circuit breaker
      - 4xx: the request is wrong, the API is healthy -> no retry, no count,
        the step is marked failed

    TODO: refine with a real HTTP client - 501 shouldn't be retried; 408 and
    429 should be retried and counted; 401/403 is a config problem
    (refresh credentials / alert); retries need a cap and backoff.
    """

    def __init__(self, message: str, status_code: int | None = None):
        # super().__init__ runs the parent class (Exception) constructor,
        # so str(error) still returns the message.
        super().__init__(message)
        self.status_code = status_code

    def is_client_error(self) -> bool:
        return self.status_code is not None and 400 <= self.status_code < 500


def call_inventory_api(order_id: str, items: list) -> bool:
    """Stand-in for a real HTTP call to an external Inventory service.

    No local stock tracking anymore - always "succeeds" after a simulated
    delay. Swap this for an actual request once that service exists.
    """
    time.sleep(random.choice([1, 2, 3]))
    # random.random() returns a float in [0.0, 1.0), so "< 1 / 3" is true
    if random.random() < INVENTORY_API_FAILURE_RATE:
        raise InventoryApiError(
            f"simulated Inventory API error {INVENTORY_API_FAILURE_STATUS}",
            status_code=INVENTORY_API_FAILURE_STATUS,
        )
    return True


def process_order_created(db, event: dict) -> None:
    order_id = event["data"]["order_id"]
    items = event["data"]["items"]
    message_id = event["id"]

    # Raises CircuitOpenError (API not called) or a 5xx/network
    # InventoryApiError - both propagate to main(), which retries the same
    # message later. A 4xx won't succeed on retry, so the step fails now.
    try:
        success = inventory_api_breaker.call(call_inventory_api, order_id, items)
    except InventoryApiError as error:
        if not error.is_client_error():
            raise
        logger.warning("Inventory API rejected order %s: %s", order_id, error)
        success = False
    status = "success" if success else "failed"

    record_step_result(db, order_id, status, message_id)


def record_step_result(db, order_id: str, status: str, message_id: str) -> None:
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


def retry_later(consumer, record, retry_seconds: int) -> None:
    # Skipping commit() alone is not enough: the "for record in consumer"
    # loop would still move on to the next message, and that message's
    # commit() would move our position past this one. seek() rewinds this
    # partition so the same message comes back.
    consumer.seek(TopicPartition(record.topic, record.partition), record.offset)
    time.sleep(retry_seconds)


def count_attempt(record) -> int:
    # topic + partition + offset identifies a Kafka message exactly, even
    # when its JSON can't be parsed.
    key = f"retry:inventory:{record.topic}:{record.partition}:{record.offset}"
    try:
        attempt = redis_client.incr(key)
        redis_client.expire(key, ATTEMPT_COUNTER_TTL_SECONDS)
        return attempt
    except redis.RedisError:
        # Can't count -> never give up while Redis is down, just keep retrying.
        logger.warning("Redis unavailable, attempt for %s not counted", key)
        return 1


def get_order_id(event) -> str | None:
    if not isinstance(event, dict):
        return None
    return event.get("data", {}).get("order_id")


def give_up(db, consumer, record, event) -> None:
    order_id = get_order_id(event)

    if order_id is None:
        # Unparseable message - no order to mark as failed. Skip it.
        logger.error(
            "Skipping unprocessable message (partition=%s, offset=%s)",
            record.partition,
            record.offset,
        )
        consumer.commit()
        return

    try:
        # A failed inventory step makes the status processor cancel the order.
        record_step_result(db, order_id, "failed", event.get("id"))
        consumer.commit()
        logger.error("Gave up on order %s after %d attempts", order_id, MAX_ATTEMPTS)
    except Exception:
        # Couldn't even record the failure (e.g. DB down). Don't skip the
        # message - that would leave the order PENDING forever. Keep retrying.
        db.rollback()
        logger.exception("Could not mark order %s as failed, retrying", order_id)
        retry_later(consumer, record, ERROR_RETRY_BASE_SECONDS)


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

        event = None
        db = SessionLocal()
        try:
            # Parsed inside the try, so a malformed message goes through the
            # capped retry below instead of crashing the whole consumer.
            event = json.loads(record.value.decode("utf-8"))
            process_order_created(db, event)
            consumer.commit()
        except (CircuitOpenError, InventoryApiError) as error:
            # API outage: retry without a cap - the circuit breaker keeps
            # these retries cheap, and an outage shouldn't cancel orders.
            db.rollback()
            if isinstance(error, CircuitOpenError):
                retry_seconds = CIRCUIT_OPEN_RETRY_SECONDS
            else:
                retry_seconds = API_ERROR_RETRY_SECONDS
            logger.warning(
                "Order %s not processed (%s), retrying in %ss",
                get_order_id(event),
                error,
                retry_seconds,
            )
            retry_later(consumer, record, retry_seconds)
        except Exception:
            db.rollback()
            attempt = count_attempt(record)
            if attempt < MAX_ATTEMPTS:
                # ** is "to the power of": 2 * 2**0, 2 * 2**1, ... = 2, 4, 8, 16
                retry_seconds = ERROR_RETRY_BASE_SECONDS * 2 ** (attempt - 1)
                logger.exception(
                    "Failed to process order %s (attempt %d/%d), retrying in %ss",
                    get_order_id(event),
                    attempt,
                    MAX_ATTEMPTS,
                    retry_seconds,
                )
                retry_later(consumer, record, retry_seconds)
            else:
                logger.exception(
                    "Failed to process order %s (attempt %d/%d)",
                    get_order_id(event),
                    attempt,
                    MAX_ATTEMPTS,
                )
                give_up(db, consumer, record, event)
        finally:
            db.close()


if __name__ == "__main__":
    main()
