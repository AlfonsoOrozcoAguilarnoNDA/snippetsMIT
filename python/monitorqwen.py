#!/usr/bin/env python3
"""
Dashboard de Información del Sistema (Ubuntu 24.04)
Autocontenido: Solo librería estándar de Python.
Ejecuta: python3 sys_dashboard.py
Accede: http://<TU_IP_VULTR>:8080
"""
# Licencia MIT
# Alfonso Orozco Aguilar 14 / 04 / 2026
# Este es una prueba de qwen 3.6 para realizar un simple monitor en python de recursos del sistema


import os
import sys
import platform
import socket
import shutil
import http.server
import socketserver

PORT = 8080

# ---------------------------------------------------------
# Funciones de recolección de datos
# ---------------------------------------------------------
def get_os_version():
    """Obtiene la versión de Ubuntu/Linux de forma compatible con Py3.10+"""
    if hasattr(platform, 'freedesktop_os_release'):
        info = platform.freedesktop_os_release()
        return f"{info.get('NAME', 'Linux')} {info.get('VERSION', '')}".strip()
    try:
        with open('/etc/os-release') as f:
            for line in f:
                if line.startswith('PRETTY_NAME='):
                    return line.split('=', 1)[1].strip().strip('"')
    except Exception:
        pass
    return platform.platform()

def get_memory():
    """Lee /proc/meminfo para calcular RAM total, usada y porcentaje"""
    meminfo = {}
    with open('/proc/meminfo') as f:
        for line in f:
            parts = line.split()
            meminfo[parts[0].rstrip(':')] = int(parts[1]) * 1024  # kB -> bytes
    total = meminfo.get('MemTotal', 1)
    available = meminfo.get('MemAvailable', 0)
    used = total - available
    percent = (used / total) * 100
    return total, used, percent

def get_disk():
    """Usa shutil para obtener espacio en disco de la raíz (/)"""
    total, used, free = shutil.disk_usage('/')
    percent = (used / total) * 100 if total > 0 else 0
    return total, used, percent

def format_bytes(b):
    """Convierte bytes a la unidad legible más apropiada"""
    for unit in ['B', 'KiB', 'MiB', 'GiB', 'TiB']:
        if b < 1024:
            return f"{b:.2f} {unit}"
        b /= 1024
    return f"{b:.2f} PiB"

def get_uptime():
    """Devuelve el tiempo activo del sistema en segundos"""
    try:
        with open('/proc/uptime') as f:
            return float(f.read().split()[0])
    except Exception:
        return 0.0

# ---------------------------------------------------------
# Servidor HTTP
# ---------------------------------------------------------
class DashboardHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        try:
            os_ver = get_os_version()
            py_ver = f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}"
            domain = socket.getfqdn()
            mem_total, mem_used, mem_pct = get_memory()
            disk_total, disk_used, disk_pct = get_disk()
            load_1, load_5, load_15 = os.getloadavg()
            uptime = get_uptime()
            cores = os.cpu_count() or 'N/A'

            html = """<!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Monitor de Sistema Vultr</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet">
                <style>
                    body {{ background-color: #f8f9fa; padding-top: 2rem; }}
                    .card {{ border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s; }}
                    .card:hover {{ transform: translateY(-4px); }}
                    .icon-box {{ width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: #e9ecef; font-size: 1.4rem; color: #495057; }}
                    .progress {{ height: 10px; }}
                    .stat-value {{ font-weight: 600; }}
                </style>
            </head>
            <body>
                <div class="container">
                    <h2 class="mb-4 text-center"><i class="fas fa-server me-2"></i>Información del Sistema</h2>
                    <div class="row g-4">
                        <!-- OS & Python & Domain -->
                        <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center">
                            <div class="icon-box me-3"><i class="fab fa-linux"></i></div>
                            <div><h6 class="text-muted mb-1">Sistema Operativo</h6><h5 class="mb-0 stat-value">{os_ver}</h5></div>
                        </div></div></div>
                        <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center">
                            <div class="icon-box me-3"><i class="fab fa-python"></i></div>
                            <div><h6 class="text-muted mb-1">Versión Python</h6><h5 class="mb-0 stat-value">{py_ver}</h5></div>
                        </div></div></div>
                        <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center">
                            <div class="icon-box me-3"><i class="fas fa-globe"></i></div>
                            <div><h6 class="text-muted mb-1">Dominio / Hostname</h6><h5 class="mb-0 stat-value text-break">{domain}</h5></div>
                        </div></div></div>

                        <!-- Memory & Disk -->
                        <div class="col-md-6"><div class="card h-100"><div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="icon-box me-3"><i class="fas fa-memory"></i></div>
                                <div><h6 class="text-muted mb-1">Memoria RAM</h6><h5 class="mb-0 stat-value">{mem_used} / {mem_total} ({mem_pct:.1f}%)</h5></div>
                            </div>
                            <div class="progress"><div class="progress-bar bg-primary" role="progressbar" style="width: {mem_pct:.1f}%"></div></div>
                        </div></div></div>
                        <div class="col-md-6"><div class="card h-100"><div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="icon-box me-3"><i class="fas fa-hdd"></i></div>
                                <div><h6 class="text-muted mb-1">Disco Duro (/)</h6><h5 class="mb-0 stat-value">{disk_used} / {disk_total} ({disk_pct:.1f}%)</h5></div>
                            </div>
                            <div class="progress"><div class="progress-bar bg-success" role="progressbar" style="width: {disk_pct:.1f}%"></div></div>
                        </div></div></div>

                        <!-- Load Avg, Uptime & CPU -->
                        <div class="col-12"><div class="card"><div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="icon-box mx-auto mb-2"><i class="fas fa-tachometer-alt"></i></div>
                                    <h6 class="text-muted">Carga Promedio (1/5/15 min)</h6>
                                    <h4 class="stat-value">{load_1:.2f} / {load_5:.2f} / {load_15:.2f}</h4>
                                    <small class="text-muted">Nº medio de procesos en ejecución</small>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="icon-box mx-auto mb-2"><i class="fas fa-clock"></i></div>
                                    <h6 class="text-muted">Tiempo Activo (Uptime)</h6>
                                    <h4 class="stat-value">{uptime:.2f} segundos</h4>
                                </div>
                                <div class="col-md-4">
                                    <div class="icon-box mx-auto mb-2"><i class="fas fa-microchip"></i></div>
                                    <h6 class="text-muted">Núcleos CPU</h6>
                                    <h4 class="stat-value">{cores}</h4>
                                </div>
                            </div>
                        </div></div></div>
                    </div>
                    <footer class="text-center mt-4 text-muted">
                        <small>Dashboard Python Autocontenido | <a href="javascript:location.reload()" class="text-decoration-none"><i class="fas fa-sync-alt"></i> Actualizar</a></small>
                    </footer>
                </div>
            </body>
            </html>"""

            response = html.format(
                os_ver=os_ver, py_ver=py_ver, domain=domain,
                mem_used=format_bytes(mem_used), mem_total=format_bytes(mem_total), mem_pct=mem_pct,
                disk_used=format_bytes(disk_used), disk_total=format_bytes(disk_total), disk_pct=disk_pct,
                load_1=load_1, load_5=load_5, load_15=load_15,
                uptime=uptime, cores=cores
            )
            self.send_response(200)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(response.encode('utf-8'))
        except Exception as e:
            self.send_response(500)
            self.send_header('Content-type', 'text/plain')
            self.end_headers()
            self.wfile.write(f"Error interno: {str(e)}".encode('utf-8'))

    def log_message(self, format, *args):
        # Suprime logs verbosos, deja solo info básica
        pass

# ---------------------------------------------------------
# Ejecución
# ---------------------------------------------------------
if __name__ == '__main__':
    with socketserver.TCPServer(("", PORT), DashboardHandler) as httpd:
        print(f"✅ Dashboard corriendo en http://0.0.0.0:{PORT}")
        print("🔗 Accede desde tu navegador: http://<IP_VULTR>:" + str(PORT))
        print("⛔ Presiona Ctrl+C para detener.")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\n👋 Servidor detenido correctamente.")
