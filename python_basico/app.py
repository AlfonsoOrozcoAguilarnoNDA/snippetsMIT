# Este script funciona en modo web: python app.py, para comprobar que el servicio web responde.
# Licencia MIT
# Alfonso Orozco Aguilar 24-nov-2026
#
# Prueba por HTTP en http://<tu-ip>:5000 o http://<tu-dominio.com>:5000

from flask import Flask

app = Flask(__name__)


@app.route("/")
def hello():
    return "<h1 style='color:blue'>Hello There!</h1>"


if __name__ == "__main__":
    app.run(host="0.0.0.0")
