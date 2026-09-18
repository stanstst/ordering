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
