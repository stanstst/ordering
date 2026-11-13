from enum import StrEnum


class OrderStatus(StrEnum):
    PENDING = "PENDING"
    DELIVERY = "DELIVERY"
    CANCELLED = "CANCELLED"


class OutboxStatus(StrEnum):
    PENDING = "pending"
    SENT = "sent"


class StepName(StrEnum):
    INVENTORY = "inventory"
    PAYMENT = "payment"


class StepStatus(StrEnum):
    SUCCESS = "success"
    FAILED = "failed"


class EventType(StrEnum):
    ORDER_CREATED = "OrderCreated"


class AggregateType(StrEnum):
    ORDER = "Order"
