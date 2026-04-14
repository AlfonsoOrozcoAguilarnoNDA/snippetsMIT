/*
 * Server Monitor Dashboard - Soberanía Técnica
 * Desarrollado por: Gemini (AI Colaborador)
 * Licencia: MIT
 * Alfonso Orzoc Aguilar
 * Versión: 1.1 (Corrección de variables no utilizadas)
 */

package main

import (
	"fmt"
	"os"
	"runtime"
	"syscall"
)

func main() {
	// 1. Recopilación de datos del sistema
	var info syscall.Sysinfo_t
	err := syscall.Sysinfo(&info)
	if err != nil {
		fmt.Print("Content-Type: text/plain\n\nError al obtener datos del sistema.")
		return
	}

	// Escala de carga (Linux usa 65536 como base para load avg)
	const scale = 65536.0
	load1 := float64(info.Loads[0]) / scale
	load5 := float64(info.Loads[1]) / scale
	load15 := float64(info.Loads[2]) / scale

	// Memoria (ajustada por unidad de bloque del sistema)
	unit := uint64(info.Unit)
	if unit == 0 { unit = 1 }
	totalRAM := (info.Totalram * unit) / 1024 / 1024
	freeRAM := (info.Freeram * unit) / 1024 / 1024
	usedRAM := totalRAM - freeRAM

	// Red
	hostname, _ := os.Hostname()

	// 2. Salida HTML
	fmt.Print("Content-Type: text/html; charset=utf-8\n\n")
	fmt.Printf(`
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vibe Monitor - %s</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: sans-serif; }
        .card { background: #1e293b; border: 1px solid #334155; color: white; border-radius: 12px; }
        .header-custom { background: linear-gradient(135deg, #1e293b 0%%, #0f172a 100%%); padding: 3rem 0; border-bottom: 2px solid #3b82f6; }
        .stat-val { font-size: 1.8rem; font-weight: bold; color: #3b82f6; }
    </small></style>
</head>
<body>

<div class="header-custom text-center">
    <h1><i class="fas fa-microchip"></i> Panel de Control Go</h1>
    <p class="text-muted">Soberanía técnica | Sin dependencias externas</p>
</div>

<div class="container mt-4">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <div class="text-uppercase small text-muted mb-2">Nodo</div>
                <div class="stat-val">%s</div>
                <div class="mt-2 text-secondary">OS: %s</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-4 h-100">
                <div class="text-uppercase small text-muted mb-2">Carga (1/5/15 min)</div>
                <div class="stat-val">%.2f | %.2f | %.2f</div>
                <div class="mt-2 text-secondary">Nivel de procesos activos</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-4 h-100">
                <div class="text-uppercase small text-muted mb-2">Memoria RAM</div>
                <div class="stat-val">%d MB</div>
                <div class="mt-2 text-secondary">Usado de %d MB totales</div>
            </div>
        </div>
    </div>

    <div class="mt-5 p-3 bg-dark rounded border border-secondary">
        <code class="text-info">
            // Status: Binario compilado con Go %s ejecutándose en %s
        </code>
    </div>
</div>

</body>
</html>
`, hostname, hostname, runtime.GOOS, load1, load5, load15, usedRAM, totalRAM, runtime.Version(), hostname)
}
