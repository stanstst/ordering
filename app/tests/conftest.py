import os

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine

TEST_DATABASE_NAME = "orders_test"
MYSQL_ADMIN_URL = os.environ.get(
    "TEST_MYSQL_ADMIN_URL", "mysql+pymysql://root:root_password@db:3306"
)

os.environ["DATABASE_URL"] = (
    f"mysql+pymysql://orders_user:orders_password@db:3306/{TEST_DATABASE_NAME}"
)
# Same Redis server, but logical database 1 instead of 0, so tests never
os.environ["REDIS_URL"] = "redis://redis:6379/1"


@pytest.fixture(scope="session")
def test_database():

    admin_engine = create_engine(MYSQL_ADMIN_URL)
    # begin() opens a transaction and commits it when the block ends.
    with admin_engine.begin() as connection:
        connection.exec_driver_sql(f"CREATE DATABASE IF NOT EXISTS {TEST_DATABASE_NAME}")
        connection.exec_driver_sql(
            f"GRANT ALL PRIVILEGES ON {TEST_DATABASE_NAME}.* TO 'orders_user'@'%%'"
        )
    admin_engine.dispose()

    from shared import models  # noqa: F401 - registers the tables on Base
    from shared.database import Base, engine

    assert engine.url.database == TEST_DATABASE_NAME
    Base.metadata.drop_all(engine)
    Base.metadata.create_all(engine)

    yield engine

    engine.dispose()


@pytest.fixture(autouse=True)
def refresh_database(test_database):
    yield

    from shared.database import Base
    from shared.redis_client import redis_client

    with test_database.begin() as connection:
        # sorted_tables is parents-first; delete children first so foreign
        # keys (order_items -> orders) don't block the delete.
        for table in reversed(Base.metadata.sorted_tables):
            connection.execute(table.delete())

    redis_client.flushdb()


@pytest.fixture
def client(test_database):
    from app.main import app

    with TestClient(app) as test_client:
        yield test_client


@pytest.fixture
def db_session(test_database):
    from shared.database import SessionLocal

    session = SessionLocal()
    yield session
    session.close()
