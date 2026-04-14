#!/usr/bin/python3
# -*- coding: utf-8 -*-
# Licencia MIT
# Alfonso Orozco Aguilar 14 / 04 / 2026
# Este es una prueba de gemini para realizar un simple monitor en python de recursos del sistema

import os
import platform
import subprocess
import sys

def get_sys_info():
    # Load Average (1, 5, 15 min)
    load_1, load_5, load_15 = os.getloadavg()
    
    # Disco (Raíz)
    st = os.statvfs('/')
    total_disk = (st.f_blocks * st.f_frsize) / (1024**3)
    free_disk = (st.f_bavail * st.f_frsize) / (1024**3)
    used_disk = total_disk - free_disk
    
    # Memoria (Leyendo /proc/meminfo para no usar librerías externas)
    mem_info = {}
    with open('/proc/meminfo', 'r') as f:
        for line in f:
            parts = line.split(':')
            if len(parts) == 2:
                mem_info[parts[0].strip()] = int(parts[1].split()[0])
    
    total_mem = mem_info.get('MemTotal', 0) / 1024 / 1024  # GB
    free_mem = mem_info.get('MemAvailable', 0) / 1024 / 1024 # GB
    
    # Versión de Ubuntu
    try:
        ubuntu_ver = subprocess.check_output(['lsb_release', '-ds'], text=True).strip()
    except:
        ubuntu_ver = "No detectada"

    # Dominio (Variable de entorno de Apache)
    domain = os.environ.get('HTTP_HOST', 'Dominio no detectado (ejecución local)')

    return {
        "load": (load_1, load_5, load_15),
        "disk": (total_disk, used_disk),
        "mem": (total_mem, total_mem - free_mem),
        "ubuntu": ubuntu_ver,
        "python": platform.python_version(),
        "domain": domain
    }

data = get_sys_info()

# Salida HTML
print("Content-Type: text/html; charset=utf-8\n")
print(f"""
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viernes Social: System Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {{ background-color: #f8f9fa; font-family: sans-serif; }}
        .card {{ border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }}
        .icon-box {{ font-size: 2rem; color: #0d6efd; }}
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4 text-center">
            <div class="col">
                <h1 class="display-5 fw-bold"><i class="fas fa-server me-3"></i>Vibecoding Monitor</h1>
                <p class="lead text-muted">Prueba de servidor temporal en Vultr</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fab fa-ubuntu icon-box me-3"></i>
                        <div>
                            <h5 class="mb-0">Sistema Operativo</h5>
                            <small class="text-muted">{data['ubuntu']}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fab fa-python icon-box me-3 text-success"></i>
                        <div>
                            <h5 class="mb-0">Python Versión</h5>
                            <small class="text-muted">{data['python']}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 p-4 text-center">
                    <i class="fas fa-globe icon-box mb-2 text-info"></i>
                    <h5 class="mb-1">Nombre del Dominio</h5>
                    <p class="fw-bold text-primary">{data['domain']}</p>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card p-4">
                    <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-warning"></i>Load Average (Uso en Segundos)</h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="fw-bold">{data['load'][0]}</h3>
                            <span class="badge bg-secondary">1 Min</span>
                        </div>
                        <div class="col-4">
                            <h3 class="fw-bold">{data['load'][1]}</h3>
                            <span class="badge bg-secondary">5 Min</span>
                        </div>
                        <div class="col-4">
                            <h3 class="fw-bold">{data['load'][2]}</h3>
                            <span class="badge bg-secondary">15 Min</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card p-4">
                    <h5><i class="fas fa-memory me-2 text-danger"></i>Memoria RAM</h5>
                    <p class="mb-1 text-muted">Uso: {data['mem'][1]:.2f} GB / {data['mem'][0]:.2f} GB</p>
                    <div class="progress">
                        <div class="progress-bar bg-danger" style="width: {(data['mem'][1]/data['mem'][0])*100}%"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card p-4">
                    <h5><i class="fas fa-hdd me-2 text-secondary"></i>Disco Duro</h5>
                    <p class="mb-1 text-muted">Uso: {data['disk'][1]:.2f} GB / {data['disk'][0]:.2f} GB</p>
                    <div class="progress">
                        <div class="progress-bar bg-secondary" style="width: {(data['disk'][1]/data['disk'][0])*100}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
""")
