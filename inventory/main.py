import json
import logging
import os
import random
import time

import redis
from kafka import KafkaConsumer, TopicPartition
from sqlalchemy.dialects.mysql import insert as mysql_insert

from inventory.circuit_breaker import (
    SERVER_ERROR_MIN_STATUS,
    CircuitBreaker,
    CircuitOpenError,
)
from shared.database import SessionLocal
from shared.enums import EventType, StepName, StepStatus
from shared.messaging import EVENT_TYPE_HEADER, MESSAGE_ENCODING
from shared.models import OrderStepResult
from shared.redis_client import redis_client

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("inventory-service")

KAFKA_BROKER = os.environ.get("KAFKA_BROKER", "kafka:9092")
KAFKA_TOPIC = os.environ.get("KAFKA_TOPIC", "order-events")
CONSUMER_GROUP = "inventory-service"

INVENTORY_API_FAILURE_RATE = 1 / 3
INVENTORY_API_FAILURE_STATUS = 503
# Standing in for real network latency - a random one is picked per call.
SIMULATED_API_LATENCY_SECONDS = [1, 2, 3]
# HTTP statuses from 400 up to SERVER_ERROR_MIN_STATUS are client errors.
CLIENT_ERROR_MIN_STATUS = 400

BREAKER_FAILURE_THRESHOLD = 5
BREAKER_FAILURE_WINDOW_SECONDS = 60
BREAKER_COOLDOWN_SECONDS = 30

CIRCUIT_OPEN_RETRY_SECONDS = 5
API_ERROR_RETRY_SECONDS = 2

MAX_ATTEMPTS = 5
ERROR_RETRY_BASE_SECONDS = 2
ATTEMPT_COUNTER_TTL_SECONDS = 60 * 60
ATTEMPT_COUNTER_KEY_PREFIX = "retry:inventory:"

inventory_api_breaker = CircuitBreaker(
    name="inventory-api",
    redis_client=redis_client,
    failure_threshold=BREAKER_FAILURE_THRESHOLD,
    failure_window_seconds=BREAKER_FAILURE_WINDOW_SECONDS,
    cooldown_seconds=BREAKER_COOLDOWN_SECONDS,
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
        super().__init__(message)
        self.status_code = status_code

    def is_client_error(self) -> bool:
        if self.status_code is None:
            return False
        return CLIENT_ERROR_MIN_STATUS <= self.status_code < SERVER_ERROR_MIN_STATUS


def call_inventory_api(order_id: str, items: list) -> bool:
    """Stand-in for a real HTTP call to an external Inventory service.

    No local stock tracking anymore - always "succeeds" after a simulated
    delay. Swap this for an actual request once that service exists.
    """
    time.sleep(random.choice(SIMULATED_API_LATENCY_SECONDS))
    if random.random() < INVENTORY_API_FAILURE_RATE:
        raise InventoryApiError(
            f"simulated Inventory API error {INVENTORY_API_FAILURE_STATUS}",
            status_code=INVENTORY_API_FAILURE_STATUS,
        )
    return True


def process_order_created(db_session, event: dict) -> None:
    order_id = event["data"]["order_id"]
    items = event["data"]["items"]
    message_id = event["id"]

    # Raises CircuitOpenError (API not called) or a 5xx/network
    # InventoryApiError - both propagate to main(), which retries the same
    # message later. A 4xx won't succeed on retry, so the step fails now.
    try:
        is_successful = inventory_api_breaker.call(call_inventory_api, order_id, items)
    except InventoryApiError as error:
        if not error.is_client_error():
            raise
        logger.warning("Inventory API rejected order %s: %s", order_id, error)
        is_successful = False
    status = StepStatus.SUCCESS if is_successful else StepStatus.FAILED

    record_step_result(db_session, order_id, status, message_id)


def record_step_result(db_session, order_id: str, status: str, message_id: str) -> None:
    upsert_statement = mysql_insert(OrderStepResult).values(
        order_id=order_id, step=StepName.INVENTORY, status=status, message_id=message_id
    )
    upsert_statement = upsert_statement.on_duplicate_key_update(
        status=upsert_statement.inserted.status, message_id=upsert_statement.inserted.message_id
    )

    db_session.execute(upsert_statement)
    db_session.commit()

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


def increment_attempt_count(record) -> int:
    # topic + partition + offset identifies a Kafka message exactly, even
    # when its JSON can't be parsed.
    attempt_counter_key = (
        f"{ATTEMPT_COUNTER_KEY_PREFIX}{record.topic}:{record.partition}:{record.offset}"
    )
    try:
        attempt = redis_client.incr(attempt_counter_key)
        redis_client.expire(attempt_counter_key, ATTEMPT_COUNTER_TTL_SECONDS)
        return attempt
    except redis.RedisError:
        # Can't count -> never give up while Redis is down, just keep retrying.
        logger.warning("Redis unavailable, attempt for %s not counted", attempt_counter_key)
        return 1


def get_order_id(event) -> str | None:
    if not isinstance(event, dict):
        return None
    return event.get("data", {}).get("order_id")


def give_up(db_session, consumer, record, event) -> None:
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
        record_step_result(db_session, order_id, StepStatus.FAILED, event.get("id"))
        consumer.commit()
        logger.error("Gave up on order %s after %d attempts", order_id, MAX_ATTEMPTS)
    except Exception:
        # Couldn't even record the failure (e.g. DB down). Don't skip the
        # message - that would leave the order PENDING forever. Keep retrying.
        db_session.rollback()
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
        event_type = headers.get(EVENT_TYPE_HEADER, b"").decode(MESSAGE_ENCODING)
        if event_type != EventType.ORDER_CREATED:
            # Cheap filter: skip anything that isn't OrderCreated without
            # even parsing the JSON value.
            consumer.commit()
            continue

        event = None
        db_session = SessionLocal()
        try:
            # Parsed inside the try, so a malformed message goes through the
            # capped retry below instead of crashing the whole consumer.
            event = json.loads(record.value.decode(MESSAGE_ENCODING))
            process_order_created(db_session, event)
            consumer.commit()
        except (CircuitOpenError, InventoryApiError) as error:
            # API outage: retry without a cap - the circuit breaker keeps
            # these retries cheap, and an outage shouldn't cancel orders.
            db_session.rollback()
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
            db_session.rollback()
            attempt = increment_attempt_count(record)
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
                give_up(db_session, consumer, record, event)
        finally:
            db_session.close()


if __name__ == "__main__":
    main()
