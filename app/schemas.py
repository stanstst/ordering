from typing import List

from pydantic import BaseModel, Field


class OrderItemIn(BaseModel):
    product_id: str
    quantity: int = Field(gt=0)


class OrderCreate(BaseModel):
    customer_id: str
    items: List[OrderItemIn]


class OrderItemOut(BaseModel):
    product_id: str
    quantity: int

    class Config:
        from_attributes = True


class OrderOut(BaseModel):
    id: str
    customer_id: str
    status: str
    items: List[OrderItemOut]

    class Config:
        from_attributes = True
