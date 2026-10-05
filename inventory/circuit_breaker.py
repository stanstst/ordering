import logging

import redis

logger = logging.getLogger("inventory-service")

CLOSED = "closed"
OPEN = "open"
HALF_OPEN = "half_open"


class CircuitOpenError(Exception):
    """Raised instead of calling the protected function while the circuit is open."""


class CircuitBreaker:
    """Circuit breaker whose state lives in Redis, so all replicas share it.

    Redis keys (all prefixed with "circuit:<name>:"):
      failures - failure counter, expires after failure_window_seconds
      open     - exists while the circuit is OPEN, expires after cooldown_seconds
      tripped  - set together with "open", no expiry. "tripped" present but
                 "open" already expired means the cooldown is over -> HALF-OPEN
      probe    - lock so only one replica sends the HALF-OPEN test call
    """

    def __init__(
        self,
        name: str,
        redis_client: redis.Redis,
        failure_threshold: int,
        failure_window_seconds: int,
        cooldown_seconds: int,
        probe_timeout_seconds: int = 10,
    ):
        prefix = f"circuit:{name}:"
        self.failures_key = prefix + "failures"
        self.open_key = prefix + "open"
        self.tripped_key = prefix + "tripped"
        self.probe_key = prefix + "probe"

        self.name = name
        self.redis = redis_client
        self.failure_threshold = failure_threshold
        self.failure_window_seconds = failure_window_seconds
        self.cooldown_seconds = cooldown_seconds
        self.probe_timeout_seconds = probe_timeout_seconds

    def call(self, func, *args):
        # *args collects any positional arguments into a tuple, and func(*args)
        # unpacks them again - so call(f, a, b) ends up running f(a, b).
        state = self._current_state()

        if state == OPEN:
            raise CircuitOpenError(f"circuit {self.name} is open")

        if state == HALF_OPEN:
            if not self._claim_probe():
                # Another replica is already sending the test call.
                raise CircuitOpenError(f"circuit {self.name} is half-open, probe in progress")
            logger.info("Circuit %s HALF-OPEN: sending probe call", self.name)

        try:
            result = func(*args)
        except Exception:
            self._record_failure(state)
            # A bare "raise" re-raises the exception we just caught, so the
            # caller still sees the original error.
            raise

        self._record_success(state)
        return result

    def _current_state(self) -> str:
        try:
            if self.redis.exists(self.open_key):
                return OPEN
            if self.redis.exists(self.tripped_key):
                return HALF_OPEN
            return CLOSED
        except redis.RedisError:
            # Fail open: if Redis is down we can't know the state, so keep
            # calling the API rather than letting the breaker block everything.
            logger.warning("Circuit %s: Redis unavailable, allowing call", self.name)
            return CLOSED

    def _claim_probe(self) -> bool:
        try:
            # SET ... NX EX: only set the key if it does Not eXist, and let it
            # EXpire after N seconds. Returns True only for the one replica
            # that created it. The expiry frees the lock if that replica crashes.
            claimed = self.redis.set(
                self.probe_key, 1, nx=True, ex=self.probe_timeout_seconds
            )
            return bool(claimed)
        except redis.RedisError:
            logger.warning("Circuit %s: Redis unavailable, allowing probe", self.name)
            return True

    def _record_failure(self, state: str) -> None:
        try:
            if state == HALF_OPEN:
                self._open("probe call failed")
                return

            # Start a new counting window if there isn't one: create the key
            # with value 0 and an expiry, but only if it doesn't exist yet.
            self.redis.set(
                self.failures_key, 0, nx=True, ex=self.failure_window_seconds
            )
            # INCR adds 1 atomically, so replicas failing at the same moment
            # never lose a count. It keeps the key's existing expiry.
            failure_count = self.redis.incr(self.failures_key)
            logger.warning(
                "Circuit %s: failure %d/%d",
                self.name,
                failure_count,
                self.failure_threshold,
            )

            if failure_count >= self.failure_threshold:
                self._open(f"{failure_count} failures within {self.failure_window_seconds}s")
        except redis.RedisError:
            logger.warning("Circuit %s: Redis unavailable, failure not recorded", self.name)

    def _record_success(self, state: str) -> None:
        if state != HALF_OPEN:
            return

        try:
            self.redis.delete(self.failures_key, self.tripped_key, self.probe_key)
            logger.info("Circuit %s CLOSED: probe call succeeded", self.name)
        except redis.RedisError:
            logger.warning("Circuit %s: Redis unavailable, could not close", self.name)

    def _open(self, reason: str) -> None:
        self.redis.set(self.open_key, 1, ex=self.cooldown_seconds)
        self.redis.set(self.tripped_key, 1)
        self.redis.delete(self.failures_key, self.probe_key)
        logger.warning(
            "Circuit %s OPEN for %ss: %s", self.name, self.cooldown_seconds, reason
        )
