#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# Licencia MIT
# Alfonso Orozco Aguilar
# Monitor de sistema en Python + Flask (puerto 5000)
# NO ES PARA TERMINAL

import os
import platform
import subprocess
from flask import Flask, render_template_string, request

app = Flask(__name__)


def get_ubuntu_version():
    try:
        return subprocess.check_output(['lsb_release', '-ds'], text=True).strip().strip('"')
    except Exception:
        try:
            with open('/etc/os-release', 'r') as f:
                for line in f:
                    if line.startswith('PRETTY_NAME='):
                        return line.split('=', 1)[1].strip().strip('"')
        except Exception:
            pass
    return platform.platform()


STATIC_INFO = {
    "ubuntu": get_ubuntu_version(),
    "python": platform.python_version(),
}


def get_sys_info():
    # Load Average (1, 5, 15 min)
    load_1, load_5, load_15 = os.getloadavg()

    # Disco (Raíz)
    st = os.statvfs('/')
    total_disk = (st.f_blocks * st.f_frsize) / (1024 ** 3)
    free_disk = (st.f_bavail * st.f_frsize) / (1024 ** 3)
    used_disk = total_disk - free_disk
    disk_pct = (used_disk / total_disk) * 100 if total_disk > 0 else 0

    # Memoria (Leyendo /proc/meminfo para no usar librerías externas)
    mem_info = {}
    with open('/proc/meminfo', 'r') as f:
        for line in f:
            parts = line.split(':')
            if len(parts) == 2:
                mem_info[parts[0].strip()] = int(parts[1].split()[0])

    total_mem = mem_info.get('MemTotal', 0) / 1024 / 1024  # GB
    free_mem = mem_info.get('MemAvailable', mem_info.get('MemFree', 0)) / 1024 / 1024  # GB
    used_mem = total_mem - free_mem
    mem_pct = (used_mem / total_mem) * 100 if total_mem > 0 else 0

    # Dominio / Host recibido en la petición HTTP de Flask
    domain = request.host or 'Dominio no detectado'

    return {
        "load": (load_1, load_5, load_15),
        "disk": (total_disk, used_disk, disk_pct),
        "mem": (total_mem, used_mem, mem_pct),
        "ubuntu": STATIC_INFO["ubuntu"],
        "python": STATIC_INFO["python"],
        "domain": domain,
    }


HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viernes Social: System Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: sans-serif; }
        .card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .icon-box { font-size: 2rem; color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4 text-center">
            <div class="col">
                <h1 class="display-4 font-weight-bold"><i class="fas fa-server mr-3"></i>Vibecoding Monitor</h1>
                <p class="lead text-muted">Prueba de servidor temporal en Vultr</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fab fa-ubuntu icon-box mr-3"></i>
                        <div>
                            <h5 class="mb-0">Sistema Operativo</h5>
                            <small class="text-muted">{{ data.ubuntu }}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fab fa-python icon-box mr-3 text-success"></i>
                        <div>
                            <h5 class="mb-0">Python Versión</h5>
                            <small class="text-muted">{{ data.python }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100 p-4 text-center d-flex flex-column justify-content-center">
                    <i class="fas fa-globe icon-box mb-2 text-info"></i>
                    <h5 class="mb-1">Nombre del Dominio</h5>
                    <p class="font-weight-bold text-primary mb-0">{{ data.domain }}</p>
                </div>
            </div>

            <div class="col-md-12 mb-4">
                <div class="card p-4">
                    <h5 class="mb-4"><i class="fas fa-chart-line mr-2 text-warning"></i>Load Average (Uso en Segundos)</h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="font-weight-bold">{{ "%.2f"|format(data.load[0]) }}</h3>
                            <span class="badge badge-secondary">1 Min</span>
                        </div>
                        <div class="col-4">
                            <h3 class="font-weight-bold">{{ "%.2f"|format(data.load[1
