import json
import logging
import os
import time

from kafka import KafkaProducer

from shared.database import SessionLocal
from shared.models import OutboxMessage

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("outbox-publisher")

KAFKA_BROKER = os.environ.get("KAFKA_BROKER", "kafka:9092")
KAFKA_TOPIC = os.environ.get("KAFKA_TOPIC", "order-events")
BATCH_SIZE = 100
POLL_INTERVAL_SECONDS = 5


def build_kafka_message(outbox_row: OutboxMessage) -> dict:
    """Turn one outbox_messages row into the CloudEvents-style envelope from CLAUDE.md."""
    return {
        "specversion": "1.0",
        "id": outbox_row.id,
        "source": "orders-service",
        "type": outbox_row.event_type,
        "subject": outbox_row.aggregate_ref_id,
        "time": outbox_row.created_at.isoformat() + "Z",
        "datacontenttype": "application/json",
        # payload is stored as a JSON string in the DB - decode it back into
        # an object so it lands in the Kafka message as real JSON, not a string.
        "data": json.loads(outbox_row.payload),
    }


def publish_pending_batch(producer: KafkaProducer, db) -> int:
    # .query(...).filter(...).order_by(...).limit(...) builds a SELECT
    # statement piece by piece; .all() is what actually runs it.
    pending_rows = (
        db.query(OutboxMessage)
        .filter(OutboxMessage.status == "pending")
        .order_by(OutboxMessage.created_at)
        .limit(BATCH_SIZE)
        .all()
    )

    for row in pending_rows:
        message = build_kafka_message(row)
        # Same key (aggregate_ref_id) always lands on the same Kafka
        # partition, so events for one order stay in order.
        producer.send(
            KAFKA_TOPIC, key=row.aggregate_ref_id.encode("utf-8"), value=message
        )
        logger.info(json.dumps(message))
        row.status = "sent"

    # send() only queues the message in memory; flush() blocks until the
    # broker has actually acknowledged every queued message.
    producer.flush()
    db.commit()

    return len(pending_rows)


def main():
    logger.info(
        "Starting outbox publisher (broker=%s, topic=%s)", KAFKA_BROKER, KAFKA_TOPIC
    )
    producer = KafkaProducer(
        bootstrap_servers=KAFKA_BROKER,
        value_serializer=lambda value: json.dumps(value).encode("utf-8"),
    )

    while True:
        db = SessionLocal()
        try:
            published_count = publish_pending_batch(producer, db)
            if published_count:
                logger.info("Published %d message(s)", published_count)
        except Exception:
            logger.exception("Failed to publish outbox batch")
            db.rollback()
        finally:
            db.close()

        time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    main()
