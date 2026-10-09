# Ordering

Event-driven order processing in Python (FastAPI, SQLAlchemy, Kafka), run with Docker Compose.

## Services

| Service | Folder | What it does |
|---|---|---|
| **api** | `app/` | `POST /orders` (requires an `Idempotency-Key` header). Saves the order as `PENDING` and an `OrderCreated` row in `outbox_messages` in one transaction. Redis guards against duplicate requests. |
| **publisher** | `publisher/` | Polls `outbox_messages` (batches of 100), publishes each row as a CloudEvents message to the Kafka topic `order-events`, and marks it `sent`. |
| **inventory** | `inventory/` | Kafka consumer (group `inventory-service`). Calls the fake Inventory API and stores the result in `order_step_results` (`step="inventory"`). The API call is protected by a Redis-backed circuit breaker. |
| **payment** | `payment/` | Kafka consumer (group `payment-service`). Calls the fake Payment API and stores the result in `order_step_results` (`step="payment"`). |
| **processor** | `status_processor/` | Polls orders that are `PENDING`. Both steps `success` → order moves on; any step `failed` → order `CANCELLED`. |

Infrastructure: **MySQL** (orders, outbox, step results), **Kafka** (events), **Redis** (idempotency keys, circuit breaker state).

## Communication

```
client ──POST /orders──▶ api ──(one transaction)──▶ MySQL: orders + outbox_messages
                                                            │
                                     publisher ◀──polls─────┘
                                         │
                                         ▼
                              Kafka topic "order-events"
                                 │                  │
                     OrderCreated│                  │OrderCreated
                                 ▼                  ▼
                            inventory            payment
                                 │                  │
                                 └──▶ MySQL: order_step_results ◀──┘
                                                │
                                   processor ◀──polls
                                       │
                                       ▼
                             MySQL: orders.status updated
```

- **api → publisher**: transactional outbox. The order and its event are saved together, so an event is never lost or sent for an order that doesn't exist.
- **publisher → inventory / payment**: Kafka. Each worker has its own consumer group, so both receive every `OrderCreated` event and run in parallel.
- **inventory / payment → processor**: through the shared `order_step_results` table, not Kafka.
- Every consumer is safe to re-run: workers upsert their result and commit the Kafka offset only after the DB write; the publisher and processor use `SELECT ... FOR UPDATE SKIP LOCKED`, so several replicas can run at once.

## Run

```bash
docker compose up --build
curl -X POST localhost:8000/orders \
  -H 'Content-Type: application/json' -H 'Idempotency-Key: abc-1' \
  -d '{"customer_id":"123","items":[{"product_id":"ABC","quantity":2}]}'
```

## Tests

Feature tests for the API live in `app/tests/`. They call the FastAPI app in-process (FastAPI's `TestClient`, like Laravel's `$this->post()`) against a separate MySQL database `orders_test` and Redis database 1. Tables are emptied after every test.

```bash
docker compose run --rm api python -m pytest app/tests -v
```
