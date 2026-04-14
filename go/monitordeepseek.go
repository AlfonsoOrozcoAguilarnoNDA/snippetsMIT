/*
 * Server Monitor Dashboard - Soberanía Técnica
 * Desarrollado por: DEEPSEEK (AI Colaborador)
 * Licencia: MIT
 * Alfonso Orzoc Aguilar 
 */

package main

import (
	"encoding/json"
	"fmt"
	"html/template"
	"net/http"
	"os"
	"runtime"
	"strconv"
	"strings"
	"time"
)

// SystemStats estructura para almacenar estadísticas del sistema
type SystemStats struct {
	UbuntuVersion   string    `json:"ubuntu_version"`
	GoVersion       string    `json:"go_version"`
	DomainName      string    `json:"domain_name"`
	UptimeSeconds   int64     `json:"uptime_seconds"`
	LoadAvg         LoadAvg   `json:"load_avg"`
	Memory          Memory    `json:"memory"`
	Disk            Disk      `json:"disk"`
	Timestamp       string    `json:"timestamp"`
}

type LoadAvg struct {
	OneMin     float64 `json:"1min"`
	FiveMin    float64 `json:"5min"`
	FifteenMin float64 `json:"15min"`
}

type Memory struct {
	Total     float64 `json:"total"`
	Used      float64 `json:"used"`
	Available float64 `json:"available"`
	Percent   float64 `json:"percent"`
}

type Disk struct {
	Total   float64 `json:"total"`
	Used    float64 `json:"used"`
	Free    float64 `json:"free"`
	Percent float64 `json:"percent"`
}

// getUbuntuVersion obtiene la versión de Ubuntu
func getUbuntuVersion() string {
	data, err := os.ReadFile("/etc/os-release")
	if err != nil {
		return "No disponible"
	}
	
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "PRETTY_NAME=") {
			version := strings.TrimPrefix(line, "PRETTY_NAME=")
			version = strings.Trim(version, "\"")
			return version
		}
	}
	return "Ubuntu 24.04 LTS"
}

// getDomainName obtiene el nombre del dominio
func getDomainName() string {
	hostname, err := os.Hostname()
	if err != nil {
		return "No disponible"
	}
	return hostname
}

// getUptime obtiene el tiempo de actividad en segundos
func getUptime() int64 {
	data, err := os.ReadFile("/proc/uptime")
	if err != nil {
		return 0
	}
	
	parts := strings.Fields(string(data))
	if len(parts) > 0 {
		uptimeFloat, err := strconv.ParseFloat(parts[0], 64)
		if err == nil {
			return int64(uptimeFloat)
		}
	}
	return 0
}

// getLoadAvg obtiene el load average
func getLoadAvg() LoadAvg {
	data, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return LoadAvg{OneMin: 0, FiveMin: 0, FifteenMin: 0}
	}
	
	parts := strings.Fields(string(data))
	if len(parts) >= 3 {
		oneMin, _ := strconv.ParseFloat(parts[0], 64)
		fiveMin, _ := strconv.ParseFloat(parts[1], 64)
		fifteenMin, _ := strconv.ParseFloat(parts[2], 64)
		return LoadAvg{OneMin: oneMin, FiveMin: fiveMin, FifteenMin: fifteenMin}
	}
	return LoadAvg{OneMin: 0, FiveMin: 0, FifteenMin: 0}
}

// getMemoryInfo obtiene información de la memoria RAM
func getMemoryInfo() Memory {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return Memory{Total: 0, Used: 0, Available: 0, Percent: 0}
	}
	
	var totalMem, availableMem float64
	lines := strings.Split(string(data), "\n")
	
	for _, line := range lines {
		if strings.HasPrefix(line, "MemTotal:") {
			parts := strings.Fields(line)
			if len(parts) >= 2 {
				val, _ := strconv.ParseFloat(parts[1], 64)
				totalMem = val / 1024 // Convertir a MB
			}
		} else if strings.HasPrefix(line, "MemAvailable:") {
			parts := strings.Fields(line)
			if len(parts) >= 2 {
				val, _ := strconv.ParseFloat(parts[1], 64)
				availableMem = val / 1024 // Convertir a MB
			}
		}
	}
	
	usedMem := totalMem - availableMem
	percent := (usedMem / totalMem) * 100
	
	return Memory{
		Total:     roundFloat(totalMem, 1),
		Used:      roundFloat(usedMem, 1),
		Available: roundFloat(availableMem, 1),
		Percent:   roundFloat(percent, 1),
	}
}

// getDiskInfo obtiene información del disco duro
func getDiskInfo() Disk {
	var stat syscallStatfs_t
	
	// Usar syscall.Statfs en lugar de unix.Statfs para compatibilidad
	err := syscallStatfs("/", &stat)
	if err != nil {
		return Disk{Total: 0, Used: 0, Free: 0, Percent: 0}
	}
	
	total := float64(stat.Blocks*uint64(stat.Bsize)) / (1024 * 1024 * 1024) // GB
	free := float64(stat.Bfree*uint64(stat.Bsize)) / (1024 * 1024 * 1024)   // GB
	used := total - free
	percent := (used / total) * 100
	
	return Disk{
		Total:   roundFloat(total, 1),
		Used:    roundFloat(used, 1),
		Free:    roundFloat(free, 1),
		Percent: roundFloat(percent, 1),
	}
}

// roundFloat redondea un float a n decimales
func roundFloat(val float64, precision int) float64 {
	ratio := mathPow10(precision)
	return float64(int(val*ratio+0.5)) / ratio
}

func mathPow10(n int) float64 {
	result := 1.0
	for i := 0; i < n; i++ {
		result *= 10
	}
	return result
}

// getSystemStats obtiene todas las estadísticas del sistema
func getSystemStats() SystemStats {
	return SystemStats{
		UbuntuVersion: getUbuntuVersion(),
		GoVersion:     runtime.Version()[2:], // Quita el 'go' del inicio
		DomainName:    getDomainName(),
		UptimeSeconds: getUptime(),
		LoadAvg:       getLoadAvg(),
		Memory:        getMemoryInfo(),
		Disk:          getDiskInfo(),
		Timestamp:     time.Now().Format("2006-01-02 15:04:05"),
	}
}

// HTML template como string constante
const htmlTemplate = `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Dashboard - Ubuntu LAMP 24.04</title>
    <!-- Bootstrap 4.6.x CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 5.4.15 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@5.4.15/css/fontawesome-all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
            margin-bottom: 20px;
            background: white;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: bold;
            font-size: 1.1em;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress {
            height: 25px;
            border-radius: 10px;
            background-color: #e9ecef;
        }
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50px;
            padding: 12px 24px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: white;
        }
        i {
            margin-right: 8px;
        }
        .info-row {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .badge-custom {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5em;
            }
            body {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1 class="text-white">
                    <i class="fas fa-tachometer-alt"></i> System Dashboard
                </h1>
                <p class="text-white-50">Ubuntu LAMP 24.04 Server Monitoring | Go + Bootstrap 4</p>
            </div>
        </div>

        <div class="row">
            <!-- Información General -->
            <div class="col-lg-6 col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle"></i> Información del Sistema
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="row">
                                <div class="col-5 stat-label">
                                    <i class="fab fa-ubuntu"></i> Ubuntu:
                                </div>
                                <div class="col-7" id="ubuntu-version">Cargando...</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="row">
                                <div class="col-5 stat-label">
                                    <i class="fab fa-golang"></i> Go:
                                </div>
                                <div class="col-7" id="go-version">Cargando...</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="row">
                                <div class="col-5 stat-label">
                                    <i class="fas fa-globe"></i> Dominio:
                                </div>
                                <div class="col-7" id="domain-name">Cargando...</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="row">
                                <div class="col-5 stat-label">
                                    <i class="fas fa-clock"></i> Uptime:
                                </div>
                                <div class="col-7" id="uptime">Cargando...</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="row">
                                <div class="col-5 stat-label">
                                    <i class="fas fa-calendar-alt"></i> Actualizado:
                                </div>
                                <div class="col-7" id="timestamp">Cargando...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Load Average -->
            <div class="col-lg-6 col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-chart-line"></i> Load Average
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value" id="load-1min">0.00</div>
                                <div class="stat-label">
                                    <i class="fas fa-hourglass-start"></i> 1 min
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value" id="load-5min">0.00</div>
                                <div class="stat-label">
                                    <i class="fas fa-hourglass-half"></i> 5 min
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value" id="load-15min">0.00</div>
                                <div class="stat-label">
                                    <i class="fas fa-hourglass-end"></i> 15 min
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Memoria RAM -->
            <div class="col-lg-6 col-md-12">
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
                                <small class="text-muted">
                                    <i class="fas fa-database"></i> Libre:
                                </small><br>
                                <strong id="memory-available">0 MB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-chart-pie"></i> Uso:
                                </small><br>
                                <strong id="memory-percent">0%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Disco Duro -->
            <div class="col-lg-6 col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-hdd"></i> Disco Duro (SSD)
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
                                <small class="text-muted">
                                    <i class="fas fa-database"></i> Libre:
                                </small><br>
                                <strong id="disk-free">0 GB</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-chart-pie"></i> Uso:
                                </small><br>
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
        <p>
            <i class="fas fa-chart-simple"></i> Dashboard actualizado cada 5 segundos | 
            <i class="fas fa-server"></i> Ubuntu LAMP 24.04 | 
            <i class="fab fa-golang"></i> Go
        </p>
    </div>

    <script>
        async function fetchStats() {
            try {
                const response = await fetch('/api/stats');
                const data = await response.json();
                
                // Información General
                document.getElementById('ubuntu-version').innerHTML = '<span class="badge badge-secondary badge-custom">' + data.ubuntu_version + '</span>';
                document.getElementById('go-version').innerHTML = '<span class="badge badge-info badge-custom">go' + data.go_version + '</span>';
                document.getElementById('domain-name').innerHTML = '<span class="badge badge-primary badge-custom">' + data.domain_name + '</span>';
                
                // Formatear Uptime
                const days = Math.floor(data.uptime_seconds / 86400);
                const hours = Math.floor((data.uptime_seconds % 86400) / 3600);
                const minutes = Math.floor((data.uptime_seconds % 3600) / 60);
                let uptimeStr = '';
                if (days > 0) uptimeStr += days + 'd ';
                if (hours > 0) uptimeStr += hours + 'h ';
                uptimeStr += minutes + 'm';
                document.getElementById('uptime').innerHTML = uptimeStr;
                
                document.getElementById('timestamp').innerHTML = data.timestamp;
                
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
                if (data.memory.percent > 90) {
                    memoryBar.className = 'progress-bar bg-danger';
                } else if (data.memory.percent > 70) {
                    memoryBar.className = 'progress-bar bg-warning';
                } else {
                    memoryBar.className = 'progress-bar bg-success';
                }
                
                // Disco
                document.getElementById('disk-used').innerHTML = data.disk.used + ' GB';
                document.getElementById('disk-total').innerHTML = data.disk.total + ' GB';
                document.getElementById('disk-free').innerHTML = data.disk.free + ' GB';
                document.getElementById('disk-percent').innerHTML = data.disk.percent + '%';
                document.getElementById('disk-bar').style.width = data.disk.percent + '%';
                
                // Cambiar color según uso de disco
                const diskBar = document.getElementById('disk-bar');
                if (data.disk.percent > 90) {
                    diskBar.className = 'progress-bar bg-danger';
                } else if (data.disk.percent > 70) {
                    diskBar.className = 'progress-bar bg-warning';
                } else {
                    diskBar.className = 'progress-bar bg-info';
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        }
        
        function refreshData() {
            const btn = document.querySelector('.refresh-btn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            fetchStats();
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 1000);
        }
        
        // Cargar datos iniciales y actualizar cada 5 segundos
        fetchStats();
        setInterval(fetchStats, 5000);
    </script>
</body>
</html>
`

// Handler para el dashboard
func dashboardHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	tmpl, err := template.New("dashboard").Parse(htmlTemplate)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	tmpl.Execute(w, nil)
}

// Handler para la API de estadísticas
func apiStatsHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")
	
	stats := getSystemStats()
	json.NewEncoder(w).Encode(stats)
}

func main() {
	// Configurar rutas
	http.HandleFunc("/", dashboardHandler)
	http.HandleFunc("/api/stats", apiStatsHandler)
	
	// Puerto por defecto 8080
	port := ":8080"
	
	fmt.Println("========================================")
	fmt.Println("🚀 System Dashboard - Go Version")
	fmt.Println("========================================")
	fmt.Printf("✅ Servidor iniciado en http://localhost%s\n", port)
	fmt.Println("🌐 Para acceder desde cualquier lugar: http://TU_DOMINIO_O_IP:8080")
	fmt.Println("📊 Dashboard de monitoreo del sistema activo")
	fmt.Println("🔄 Actualización automática cada 5 segundos")
	fmt.Println("⚙️  Versión de Go:", runtime.Version())
	fmt.Println("========================================")
	fmt.Println("⏹️  Presiona Ctrl+C para detener el servidor")
	
	// Iniciar servidor
	if err := http.ListenAndServe(port, nil); err != nil {
		fmt.Printf("❌ Error al iniciar el servidor: %v\n", err)
	}
}
