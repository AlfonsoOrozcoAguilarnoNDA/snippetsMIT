<?php
/**
 * 🕵️ Sincronizador de Alta Seguridad para Backend Python
 * Autodetector de Dominio, Ruta y Doble Validación Estricta
 * Licencia MIT
 * Autor: Alfonso Orozco Aguilar
 * Fecha: 24/11/2026
 * Actualización: Protección de .env y reinicio de servicio Python
 * Pendiente: Verificar IP antes de proceder (Recomendado)
 * BORRADOR
 */

// ⚠️ SEGURIDAD BÁSICA: Descomenta y ajusta la IP permitida para evitar accesos no autorizados
// $allowed_ips = ['127.0.0.1', 'TU_IP_DE_OFICINA'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
//     die("🚫 Acceso denegado: IP no autorizada.");
// }

$current_path = getcwd();
$dominio = $_SERVER['HTTP_HOST'];
$confirmado_check = isset($_POST['confirmar_check']);
$confirmado_texto = isset($_POST['confirmar_texto']) ? trim($_POST['confirmar_texto']) : '';

echo "<h2 style='font-family:sans-serif;'>🌐 Dominio Detectado: <span style='color:red;'>" . htmlspecialchars($dominio) . "</span></h2>";
echo "<h3 style='font-family:sans-serif; color:gray;'>📁 Ruta Local: " . htmlspecialchars($current_path) . "</h3>";

echo "<div style='background:#e7f3ff; border-left:5px solid #2196F3; padding:15px; margin-bottom:20px; font-family:sans-serif;'>";
echo "<b>ℹ️ AVISO DE PROTOCOLO:</b> Se asume que el administrador ya configuró el <code>remote origin</code> ";
echo "utilizando un <b>Personal Access Token (PAT)</b> de GitHub/Gitea. El archivo <code>.env</code> está protegido.";
echo "</div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmado_check && $confirmado_texto === "SI") {

    echo "<pre style='background:#000; color:#0f0; padding:15px; border-radius:5px; font-family:monospace;'>";
    echo "🚨 VALIDACIÓN ACEPTADA EN [" . htmlspecialchars($dominio) . "]\n";
    echo "Iniciando sincronización del Backend Python...\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // A. Registrar el directorio actual como seguro en la configuración global
    // ─────────────────────────────────────────────────────────────────────────
    echo "🔐 [SAFE DIR] Registrando directorio como seguro para Git...\n";
    exec("git config --global --add safe.directory " . escapeshellarg($current_path));
    echo "   ✔ Directorio registrado: " . htmlspecialchars($current_path) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // B. Fetch: Descarga todos los cambios del repositorio remoto
    // ─────────────────────────────────────────────────────────────────────────
    echo "📡 [FETCH] Conectando con el origen remoto y descargando cambios...\n";
    exec("cd " . escapeshellarg($current_path) . " && git fetch --all 2>&1", $out_fetch);
    echo implode("\n", $out_fetch) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // C. Reset Hard: Fuerza que el estado local sea idéntico a origin/main
    // ─────────────────────────────────────────────────────────────────────────
    echo "🔄 [RESET] Forzando espejo exacto del estado de 'origin/main'...\n";
    exec("cd " . escapeshellarg($current_path) . " && git reset --hard origin/main 2>&1", $out_reset);
    echo implode("\n", $out_reset) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // D. Clean: Elimina archivos huérfanos, PROTEGIENDO .env y carpetas Python
    // ─────────────────────────────────────────────────────────────────────────
    $borrar_basura = isset($_POST['borrar_basura']) && $_POST['borrar_basura'] === "SI";
    if ($borrar_basura) {
        echo "🧹 [CLEAN] Eliminando archivos huérfanos no trackeados por Git...\n";
        echo "   🛡️ .env, venv/ y __pycache__/ están EXCLUIDOS de la limpieza.\n";
        // Nota: Usamos múltiples --exclude para proteger el entorno Python
        exec(
            "cd " . escapeshellarg($current_path) . " && git clean -fd --exclude=.env --exclude=venv/ --exclude=__pycache__/ 2>&1",
            $out_clean
        );
        echo implode("\n", $out_clean) . "\n";
    } else {
        echo "⏭️  [CLEAN] Omitido por el usuario (no se borrarán archivos huérfanos).\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E. Reinicio del Servicio Python (CRÍTICO)
    // Ajusta el nombre del servicio (ej. gunicorn, miapp-python, docker)
    // ─────────────────────────────────────────────────────────────────────────
    echo "\n🔄 [RESTART] Reiniciando el servicio de Python para aplicar cambios...\n";
    $servicio_python = "gunicorn"; // <--- CAMBIA ESTO por el nombre real de tu servicio (ej. 'miapp-backend')
    
    // Nota: El usuario www-data/apache necesita permisos de sudo sin contraseña para este comando específico
    exec("sudo systemctl restart " . escapeshellarg($servicio_python) . " 2>&1", $out_restart);
    echo implode("\n", $out_restart) . "\n";

    // Verificación básica del estado
    exec("systemctl is-active " . escapeshellarg($servicio_python), $status_out);
    if (trim(implode("", $status_out)) === "active") {
        echo "   ✔ El servicio de Python está corriendo correctamente.\n";
    } else {
        echo "   ⚠️ ADVERTENCIA: El servicio no reporta estado 'active'. Revisar logs.\n";
    }

    echo "\n✅ OPERACIÓN TERMINADA. El backend Python está sincronizado y reiniciado.";
    echo "</pre>";
    echo '<br><a href="?">⬅️ Volver al Panel</a>';

} else {
    // ─────────────────────────────────────────────────────────────────────────
    // Formulario de doble validación
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST" style="background:#fff3cd; padding:25px; border:2px solid #ffeeba; border-radius:10px; font-family:sans-serif; max-width:650px;">';
    echo '<h3 style="margin-top:0; color:#856404;">⚠️ Confirmación de Sincronización (Backend Python)</h3>';
    echo '<p>Estás a punto de resetear el código en <b>' . htmlspecialchars($dominio) . '</b>.</p>';

    echo '<label style="display:block; margin-bottom:15px; cursor:pointer;">';
    echo '<input type="checkbox" name="confirmar_check"> 1. Acepto que los archivos locales serán sobreescritos.';
    echo '</label>';

    echo '<div style="margin-bottom:20px;">';
    echo '2. Escribe <b>SI</b> para autorizar el Reset Hard:<br>';
    echo '<input type="text" name="confirmar_texto" placeholder="Escribe SI" style="padding:8px; width:100px; margin-top:5px; text-align:center;">';
    echo '</div>';

    echo '<div style="background:#f8d7da; padding:15px; border-radius:5px;">';
    echo '<b>🔥 OPCIONAL: ¿Borrar archivos que no están en Git?</b><br>';
    echo '<small style="color:#555;">🛡️ <code>.env</code>, <code>venv/</code> y <code>__pycache__/</code> están <b>protegidos</b> y nunca serán borrados.</small><br><br>';
    echo 'Escribe <b>SI</b> para confirmar <code>git clean</code>: ';
    echo '<input type="text" name="borrar_basura" value="NO" placeholder="SI o NO" style="padding:5px; width:80px; text-align:center;">';
    echo '</div>';

    echo '<br><button type="submit" style="padding:15px; cursor:pointer; background:#dc3545; color:#fff; border:none; border-radius:5px; font-weight:bold; width:100%;">🚀 SINCRONIZAR BACKEND AHORA</button>';
    echo '</form>';
}
?>
