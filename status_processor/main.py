import logging
import os
import time

from sqlalchemy import and_, exists, or_

from shared.database import SessionLocal
from shared.models import Order, OrderStepResult

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("order-processor")

BATCH_SIZE = 100
POLL_INTERVAL_SECONDS = int(os.environ.get("POLL_INTERVAL_SECONDS", "5"))


def step_has_status(step_name, status):
    # exists() builds an SQL "EXISTS (SELECT 1 FROM ... WHERE ...)" condition.
    # It is true when this order has a row for the given step with the given
    # status. Because the database does the matching, orders that can't
    # advance never take up a slot in the batch (no starvation).
    return exists().where(
        and_(
            OrderStepResult.order_id == Order.id,
            OrderStepResult.step == step_name,
            OrderStepResult.status == status,
        )
    )


def advance_ready_orders(db) -> int:
    # Same FOR UPDATE SKIP LOCKED batching as publisher/main.py - lets
    # multiple processor replicas run concurrently without two of them
    # grabbing (and double-processing) the same order.
    ready_orders = (
        db.query(Order)
        .filter(
            Order.status == "PENDING",
            step_has_status("inventory", "success"),
            step_has_status("payment", "success"),
        )
        .order_by(Order.created_at)
        .limit(BATCH_SIZE)
        .with_for_update(skip_locked=True)
        .all()
    )

    for order in ready_orders:
        order.status = "DELIVERY"
        logger.info("Order %s -> DELIVERY", order.id)

    db.commit()
    return len(ready_orders)


def cancel_failed_orders(db) -> int:
    # An order with any failed step can never become ready, so take it out
    # of PENDING instead of leaving it there forever.
    failed_orders = (
        db.query(Order)
        .filter(
            Order.status == "PENDING",
            or_(
                step_has_status("inventory", "failed"),
                step_has_status("payment", "failed"),
            ),
        )
        .order_by(Order.created_at)
        .limit(BATCH_SIZE)
        .with_for_update(skip_locked=True)
        .all()
    )

    for order in failed_orders:
        order.status = "CANCELLED"
        logger.info("Order %s -> CANCELLED (a step failed)", order.id)

    db.commit()
    return len(failed_orders)


def process_pending_batch(db) -> int:
    advanced_count = advance_ready_orders(db)
    cancelled_count = cancel_failed_orders(db)
    return advanced_count + cancelled_count


def main():
    logger.info(
        "Starting order processor (poll_interval=%ss, batch_size=%s)",
        POLL_INTERVAL_SECONDS,
        BATCH_SIZE,
    )

    while True:
        db = SessionLocal()
        try:
            updated_count = process_pending_batch(db)
            if updated_count:
                logger.info("Updated %d order(s)", updated_count)
        except Exception:
            logger.exception("Failed to process pending orders batch")
            db.rollback()
        finally:
            db.close()

        time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    main()
