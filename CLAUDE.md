# Project context
Learning Python by building this. Please:
- Explain new syntax/concepts briefly when introduced
- Prefer explicit code over clever one-liners
- Ask before introducing a new library I haven't used yet

## Order payload example
POST /orders
```json
{
  "customer_id": "123",
  "items": [
    {
      "product_id": "ABC",
      "quantity": 2
    }
  ]
}
```

## Outbox messaging example
```json
{
  "id": "uuid",
  "aggregate_type": "Order",
  "aggregate_ref_id": "order-123",
  "event_type": "OrderCreated",
  "payload": "{}",
  "status": "pending"
}
```
## Kafka publisher 
Kafka publisher runs in a separate docker container
Kafka publisher reads from table `outbox_messages` max batch size 100 records
Published into Kafka server
Log in the message in the std out
_Kafka message example_
```json
{
  "specversion": "1.0",
  "id": "8f7c2d91-6b3a-4e12-9c5e-2a4f8b7d1234",
  "source": "orders-service",
  "type": "order.created",
  "subject": "ord-12345",
  "time": "2026-09-18T14:30:00Z",
  "datacontenttype": "application/json",
  "data": {
    "orderId": "order-12345",
    "customerId": "customer-987",
    "items": [
      {
        "productId": "product-001",
        "quantity": 2
      },
      {
        "productId": "product-002",
        "quantity": 1
      }
    ],
    "createdAt": "2026-09-18T14:30:00Z"
  }
}
```