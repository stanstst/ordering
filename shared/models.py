import uuid

from sqlalchemy import Column, DateTime, ForeignKey, Integer, String, Text
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from shared.database import Base


class Order(Base):
    __tablename__ = "orders"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    customer_id = Column(String(64), nullable=False)
    status = Column(String(32), nullable=False, default="PENDING")
    created_at = Column(DateTime(timezone=True), server_default=func.now())

    items = relationship(
        "OrderItem", back_populates="order", cascade="all, delete-orphan"
    )


class OrderItem(Base):
    __tablename__ = "order_items"

    id = Column(Integer, primary_key=True, index=True)
    order_id = Column(String(36), ForeignKey("orders.id"), nullable=False)
    product_id = Column(String(64), nullable=False)
    quantity = Column(Integer, nullable=False)

    order = relationship("Order", back_populates="items")


class OutboxMessage(Base):
    __tablename__ = "outbox_messages"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    aggregate_type = Column(String(64), nullable=False)
    aggregate_ref_id = Column(String(64), nullable=False)
    event_type = Column(String(64), nullable=False)
    payload = Column(Text, nullable=False)
    status = Column(String(32), nullable=False, default="pending")
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class OrderStepResult(Base):
    __tablename__ = "order_step_results"

    # Composite primary key (order_id, step) - Inventory only ever writes
    # step="inventory" rows, Payment only ever writes step="payment" rows,
    # so the two never contend for the same row.
    order_id = Column(String(36), ForeignKey("orders.id"), primary_key=True)
    step = Column(String(16), primary_key=True)  # "inventory" | "payment"
    status = Column(String(16), nullable=False)  # "success" | "failed"
    # The CloudEvents "id" of the Kafka message that produced this result -
    # not a business field, just a breadcrumb back to the exact message for
    # tracing/debugging (e.g. "grep this id in the inventory service logs").
    message_id = Column(String(36), nullable=True)
    updated_at = Column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now()
    )
