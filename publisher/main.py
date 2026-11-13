import json
import logging
import os
import time

from kafka import KafkaProducer

from shared.database import SessionLocal
from shared.enums import OutboxStatus
from shared.messaging import EVENT_TYPE_HEADER, MESSAGE_ENCODING
from shared.models import OutboxMessage

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("outbox-publisher")

KAFKA_BROKER = os.environ.get("KAFKA_BROKER", "kafka:9092")
KAFKA_TOPIC = os.environ.get("KAFKA_TOPIC", "order-events")
BATCH_SIZE = 100
POLL_INTERVAL_SECONDS = 5

# CloudEvents envelope fields (https://cloudevents.io)
CLOUDEVENTS_SPEC_VERSION = "1.0"
EVENT_SOURCE = "orders-service"
DATA_CONTENT_TYPE = "application/json"
UTC_SUFFIX = "Z"


def build_kafka_message(outbox_row: OutboxMessage) -> dict:
    """Turn one outbox_messages row into the CloudEvents-style envelope from CLAUDE.md."""
    return {
        "specversion": CLOUDEVENTS_SPEC_VERSION,
        "id": outbox_row.id,
        "source": EVENT_SOURCE,
        "type": outbox_row.event_type,
        "subject": outbox_row.aggregate_ref_id,
        "time": outbox_row.created_at.isoformat() + UTC_SUFFIX,
        "datacontenttype": DATA_CONTENT_TYPE,
        "data": json.loads(outbox_row.payload),
    }


def publish_pending_batch(producer: KafkaProducer, db_session) -> int:
    pending_rows = (
        db_session.query(OutboxMessage)
        .filter(OutboxMessage.status == OutboxStatus.PENDING)
        .order_by(OutboxMessage.created_at)
        .limit(BATCH_SIZE)
        .with_for_update(skip_locked=True)
        .all()
    )

    for outbox_row in pending_rows:
        kafka_message = build_kafka_message(outbox_row)
        headers = [(EVENT_TYPE_HEADER, outbox_row.event_type.encode(MESSAGE_ENCODING))]
        producer.send(
            KAFKA_TOPIC,
            key=outbox_row.aggregate_ref_id.encode(MESSAGE_ENCODING),
            value=kafka_message,
            headers=headers,
        )
        logger.info(json.dumps(kafka_message))
        outbox_row.status = OutboxStatus.SENT

    producer.flush()
    db_session.commit()

    return len(pending_rows)


def main():
    logger.info(
        "Starting outbox publisher (broker=%s, topic=%s)", KAFKA_BROKER, KAFKA_TOPIC
    )
    producer = KafkaProducer(
        bootstrap_servers=KAFKA_BROKER,
        value_serializer=lambda value: json.dumps(value).encode(MESSAGE_ENCODING),
    )

    while True:
        db_session = SessionLocal()
        try:
            published_count = publish_pending_batch(producer, db_session)
            if published_count:
                logger.info("Published %d message(s)", published_count)
        except Exception:
            logger.exception("Failed to publish outbox batch")
            db_session.rollback()
        finally:
            db_session.close()

        time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    main()
