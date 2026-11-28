from flask import Flask
from sqlalchemy import text
from db import engine

app = Flask(__name__)

@app.get("/ping-db")
def ping_db():
    with engine.connect() as conn:
        result = conn.execute(text("SELECT 1")).scalar()
    return {"db_ok": result == 1}
