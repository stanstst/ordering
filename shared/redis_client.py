import os

import redis

REDIS_URL = os.environ.get("REDIS_URL", "redis://redis:6379/0")

# decode_responses=True makes GET/SET work with str instead of raw bytes.
redis_client = redis.Redis.from_url(REDIS_URL, decode_responses=True)
