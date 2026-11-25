# Este script funciona en modo terminal: python monitor.py, para revisar si la terminal está funcionando.
# Licencia MIT
# Alfonso Orozco Aguilar 24-nov-2026

import shutil


def obtener_memoria_mb():
    info = {}
    with open("/proc/meminfo", "r", encoding="utf-8") as f:
        for linea in f:
            partes = linea.split(":")
            if len(partes) == 2:
                clave = partes[0].strip()
                # El valor viene en kB, por ejemplo: "2048123 kB"
                valor_kb = int(partes[1].strip().split()[0])
                info[clave] = valor_kb / 1024

    total_mb = info.get("MemTotal", 0)
    disponible_mb = info.get("MemAvailable", info.get("MemFree", 0))
    usada_mb = total_mb - disponible_mb
    return total_mb, usada_mb, disponible_mb


def obtener_disco_gb(ruta="/"):
    uso = shutil.disk_usage(ruta)
    gb = 1024**3
    total_gb = uso.total / gb
    usado_gb = uso.used / gb
    libre_gb = uso.free / gb
    return total_gb, usado_gb, libre_gb


if __name__ == "__main__":
    mem_total, mem_usada, mem_libre = obtener_memoria_mb()
    disco_total, disco_usado, disco_libre = obtener_disco_gb("/")

    print("=== ESTADO DEL VPS ===")
    print(
        f"RAM:   {mem_usada:.1f} MB usadas / {mem_libre:.1f} MB libres (Total: {mem_total:.1f} MB)"
    )
    print(
        f"Disco: {disco_usado:.2f} GB usados / {disco_libre:.2f} GB libres (Total: {disco_total:.2f} GB)"
    )

