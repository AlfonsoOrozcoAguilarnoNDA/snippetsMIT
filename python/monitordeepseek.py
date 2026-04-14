#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# Licencia MIT
# Alfonso Orozco Aguilar 14 / 04 / 2026
# Este es una prueba de deepseek para realizar un simple monitor en python de recursos del sistema

import os
import subprocess
import json
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler

class DashboardHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            self.send_response(200)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(self.get_dashboard_html().encode('utf-8'))
        elif self.path == '/api/stats':
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(self.get_system_stats()).encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()
    
    def get_system_stats(self):
        """Obtiene todas las estadísticas del sistema"""
        stats = {}
        
        # Versión de Ubuntu
        try:
            with open('/etc/os-release', 'r') as f:
                for line in f:
                    if line.startswith('PRETTY_NAME='):
                        stats['ubuntu_version'] = line.split('=')[1].strip().strip('"')
                        break
        except:
            stats['ubuntu_version'] = 'No disponible'
        
        # Versión de Python
        import sys
        stats['python_version'] = f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}"
        
        # Nombre del dominio
        try:
            hostname = subprocess.check_output(['hostname', '-f'], text=True).strip()
            stats['domain_name'] = hostname
        except:
            stats['domain_name'] = 'No disponible'
        
        # Memoria
        try:
            with open('/proc/meminfo', 'r') as f:
                mem_info = {}
                for line in f:
                    key = line.split(':')[0]
                    value = line.split(':')[1].strip().split()[0]
                    mem_info[key] = int(value)
            
            total_mem = mem_info['MemTotal'] / 1024  # MB
            available_mem = mem_info['MemAvailable'] / 1024  # MB
            used_mem = total_mem - available_mem
            mem_percent = (used_mem / total_mem) * 100
            
            stats['memory'] = {
                'total': round(total_mem, 1),
                'used': round(used_mem, 1),
                'available': round(available_mem, 1),
                'percent': round(mem_percent, 1)
            }
        except:
            stats['memory'] = {'total': 0, 'used': 0, 'available': 0, 'percent': 0}
        
        # Disco duro
        try:
            statvfs = os.statvfs('/')
            total_disk = (statvfs.f_blocks * statvfs.f_frsize) / (1024**3)  # GB
            free_disk = (statvfs.f_bfree * statvfs.f_frsize) / (1024**3)  # GB
            used_disk = total_disk - free_disk
            disk_percent = (used_disk / total_disk) * 100
            
            stats['disk'] = {
                'total': round(total_disk, 1),
                'used': round(used_disk, 1),
                'free': round(free_disk, 1),
                'percent': round(disk_percent, 1)
            }
        except:
            stats['disk'] = {'total': 0, 'used': 0, 'free': 0, 'percent': 0}
        
        # Load average (1, 5, 15 minutos)
        try:
            with open('/proc/loadavg', 'r') as f:
                load = f.read().strip().split()
                stats['load_avg'] = {
                    '1min': float(load[0]),
                    '5min': float(load[1]),
                    '15min': float(load[2])
                }
        except:
            stats['load_avg'] = {'1min': 0, '5min': 0, '15min': 0}
        
        # Uptime en segundos
        try:
            with open('/proc/uptime', 'r') as f:
                uptime_seconds = float(f.read().split()[0])
                stats['uptime_seconds'] = round(uptime_seconds)
        except:
            stats['uptime_seconds'] = 0
        
        stats['timestamp'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        return stats
    
    def get_dashboard_html(self):
        """Genera el HTML del dashboard con Bootstrap y Font Awesome"""
        return f"""
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Dashboard - Ubuntu LAMP</title>
    <!-- Bootstrap 5.6.x CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 5.4.15 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@5.4.15/css/fontawesome-all.min.css">
    <style>
        body {{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }}
        .dashboard-card {{
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }}
        .dashboard-card:hover {{
            transform: translateY(-5px);
        }}
        .card-header {{
            border-radius: 15px 15px 0 0 !important;
            font-weight: bold;
        }}
        .stat-value {{
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }}
        .stat-label {{
            color: #666;
            font-size: 0.9em;
        }}
        .progress {{
            height: 25px;
            border-radius: 10px;
        }}
        .refresh-btn {{
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50px;
            padding: 10px 20px;
        }}
        .footer {{
            text-align: center;
            margin-top: 30px;
            color: white;
        }}
        i {{
            margin-right: 8px;
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1 class="text-white">
                    <i class="fas fa-tachometer-alt"></i> System Dashboard
                </h1>
                <p class="text-white-50">Ubuntu LAMP 24.04 Server Monitoring</p>
            </div>
        </div>

        <div class="row">
            <!-- Información General -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle"></i> Información del Sistema
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-4 stat-label">Ubuntu:</div>
                            <div class="col-8 stat-value-small" id="ubuntu-version">Cargando...</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 stat-label">Python:</div>
                            <div class="col-8 stat-value-small" id="python-version">Cargando...</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 stat-label">Dominio:</div>
                            <div class="col-8 stat-value-small" id="domain-name">Cargando...</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 stat-label">Uptime:</div>
                            <div class="col-8 stat-value-small" id="uptime">Cargando...</div>
                        </div>
                        <div class="row">
                            <div class="col-4 stat-label">Última actualización:</div>
                            <div class="col-8 stat-value-small" id="timestamp">Cargando...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Load Average -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-chart-line"></i> Load Average
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value" id="load-1min">0.00</div>
                                <div class="stat-label">1 minuto</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value" id="load-5min">0.00</div>
                                <div class="stat-label">5 minutos</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value" id="load-15min">0.00</div>
                                <div class="stat-label">15 minutos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Memoria -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-microchip"></i> Memoria RAM
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="stat-value" id="memory-used">0 MB</div>
                            <div class="stat-label">de <span id="memory-total">0 MB</span></div>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" id="memory-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <small class="text-muted">Libre:</small><br>
                                <strong id="memory-available">0 MB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Uso:</small><br>
                                <strong id="memory-percent">0%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Disco Duro -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-hdd"></i> Disco Duro
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="stat-value" id="disk-used">0 GB</div>
                            <div class="stat-label">de <span id="disk-total">0 GB</span></div>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-info" id="disk-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <small class="text-muted">Libre:</small><br>
                                <strong id="disk-free">0 GB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Uso:</small><br>
                                <strong id="disk-percent">0%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-primary refresh-btn" onclick="refreshData()">
        <i class="fas fa-sync-alt"></i> Actualizar
    </button>

    <div class="footer">
        <p><i class="fas fa-chart-simple"></i> Dashboard actualizado en tiempo real | <i class="fas fa-server"></i> Ubuntu LAMP 24.04</p>
    </div>

    <script>
        async function fetchStats() {{
            try {{
                const response = await fetch('/api/stats');
                const data = await response.json();
                
                // Información General
                document.getElementById('ubuntu-version').innerHTML = '<i class="fas fa-ubuntu"></i> ' + data.ubuntu_version;
                document.getElementById('python-version').innerHTML = '<i class="fab fa-python"></i> ' + data.python_version;
                document.getElementById('domain-name').innerHTML = '<i class="fas fa-globe"></i> ' + data.domain_name;
                
                // Uptime formateado
                const days = Math.floor(data.uptime_seconds / 86400);
                const hours = Math.floor((data.uptime_seconds % 86400) / 3600);
                const minutes = Math.floor((data.uptime_seconds % 3600) / 60);
                document.getElementById('uptime').innerHTML = `<i class="fas fa-clock"></i> ${{days}}d ${{hours}}h ${{minutes}}m`;
                
                document.getElementById('timestamp').innerHTML = '<i class="fas fa-calendar"></i> ' + data.timestamp;
                
                // Load Average
                document.getElementById('load-1min').innerHTML = data.load_avg['1min'].toFixed(2);
                document.getElementById('load-5min').innerHTML = data.load_avg['5min'].toFixed(2);
                document.getElementById('load-15min').innerHTML = data.load_avg['15min'].toFixed(2);
                
                // Memoria
                document.getElementById('memory-used').innerHTML = data.memory.used + ' MB';
                document.getElementById('memory-total').innerHTML = data.memory.total + ' MB';
                document.getElementById('memory-available').innerHTML = data.memory.available + ' MB';
                document.getElementById('memory-percent').innerHTML = data.memory.percent + '%';
                document.getElementById('memory-bar').style.width = data.memory.percent + '%';
                
                // Cambiar color según uso de memoria
                const memoryBar = document.getElementById('memory-bar');
                if (data.memory.percent > 90) memoryBar.className = 'progress-bar bg-danger';
                else if (data.memory.percent > 70) memoryBar.className = 'progress-bar bg-warning';
                else memoryBar.className = 'progress-bar bg-success';
                
                // Disco
                document.getElementById('disk-used').innerHTML = data.disk.used + ' GB';
                document.getElementById('disk-total').innerHTML = data.disk.total + ' GB';
                document.getElementById('disk-free').innerHTML = data.disk.free + ' GB';
                document.getElementById('disk-percent').innerHTML = data.disk.percent + '%';
                document.getElementById('disk-bar').style.width = data.disk.percent + '%';
                
                // Cambiar color según uso de disco
                const diskBar = document.getElementById('disk-bar');
                if (data.disk.percent > 90) diskBar.className = 'progress-bar bg-danger';
                else if (data.disk.percent > 70) diskBar.className = 'progress-bar bg-warning';
                else diskBar.className = 'progress-bar bg-info';
            }} catch (error) {{
                console.error('Error fetching data:', error);
            }}
        }}
        
        function refreshData() {{
            fetchStats();
            const btn = document.querySelector('.refresh-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            setTimeout(() => {{
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar';
            }}, 1000);
        }}
        
        // Cargar datos iniciales y actualizar cada 5 segundos
        fetchStats();
        setInterval(fetchStats, 5000);
    </script>
</body>
</html>
        """

def run_server(port=8080):
    server_address = ('', port)
    httpd = HTTPServer(server_address, DashboardHandler)
    print(f"✅ Servidor iniciado en http://localhost:{port}")
    print(f"🌐 Para acceder desde cualquier lugar: http://TU_DOMINIO_O_IP:{port}")
    print("📊 Dashboard de monitoreo del sistema activo")
    print("🔄 Actualización automática cada 5 segundos")
    print("⏹️  Presiona Ctrl+C para detener el servidor")
    
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\n🛑 Servidor detenido")
        httpd.server_close()

if __name__ == '__main__':
    run_server()
