from flask import Flask
from sqlalchemy import text
from db import engine

app = Flask(__name__)


@app.get("/")
def hola_mundo():
    return {"mensaje": "Hola mundo desde Flask/Gunicorn"}


@app.get("/ping-db")
def ping_db():
    with engine.connect() as conn:
        result = conn.execute(text("SELECT 1")).scalar()
    return {"db_ok": result == 1}


if __name__ == "__main__":
    # Solo para pruebas locales directas con `python app.py`;
    # en despliegue Linux se invoca con `gunicorn app:app`.
    app.run(host="127.0.0.1", port=5000, debug=False)
