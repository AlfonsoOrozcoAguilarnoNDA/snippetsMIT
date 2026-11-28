from sqlalchemy import create_engine
from sqlalchemy.engine import URL
from sqlalchemy.pool import QueuePool

try:
    from settings import DB_CONFIG
except ImportError as exc:
    raise RuntimeError(
        "Falta el archivo settings.py. Copia settings_sample.py a settings.py y configura tus credenciales."
    ) from exc

connection_url = URL.create(
    drivername="mysql+pymysql",
    username=DB_CONFIG["user"],
    password=DB_CONFIG["password"],
    host=DB_CONFIG["host"],
    port=DB_CONFIG["port"],
    database=DB_CONFIG["database"],
    query={"charset": "utf8mb4"},
)

engine = create_engine(
    connection_url,
    poolclass=QueuePool,
    pool_size=DB_CONFIG.get("pool_size", 5),
    max_overflow=DB_CONFIG.get("max_overflow", 2),
    pool_pre_ping=True,
    pool_recycle=1800,
    future=True,
)
