<?php
/**
 * SISTEMA: SNIPPET ideentificador / Bloqueador de Ips en base a archivo raw apache
 * https://vibecodingmexico.com/snippets-lector-de-raw-logs/
 * FECHA: 29 de abril de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Gemini 3 Flash (v.2026-04) & Alfonso Orozco Aguilar
 */
include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) { 
    header('HTTP/1.1 403 Forbidden');
    die("Acceso denegado."); 
}

// 2. Escaneo de archivos .txt en el directorio actual
$archivos_log = glob("*.txt");

if (isset($_POST['ejecutar_importacion'])) {
    $archivo = $_POST['nombre_archivo'];
    $dominio = $link->real_escape_string($_POST['dominio_nombre']);

    if (!file_exists($archivo)) {
        $error = "El archivo ya no existe o fue movido.";
    } else {
        set_time_limit(900); 
        $handle = fopen($archivo, "r");
        $contador = 0;

        // Optimización: Desactivar autocommit para mayor velocidad
        $link->autocommit(FALSE);

        while (($line = fgets($handle)) !== false) {
            // Regex estándar Apache Combined
            $pattern = '/^(\S+) \S+ \S+ \[(.*?)\] "(.*?) (.*?) (.*?)" (\d+) (\d+|-) "(.*?)" "(.*?)"$/';
            
            if (preg_match($pattern, $line, $matches)) {
                $ip = $matches[1];
                
                // --- CORRECCIÓN DE FECHA Y HORA (REDISEÑADA) ---
                // El formato de Apache suele ser: 29/Apr/2026:08:11:57 -0600
                $raw_date = explode(' ', $matches[2])[0]; // "29/Apr/2026:08:11:57"
                
                // Usamos la clase DateTime para parsear el formato exacto de Apache
                $dt = DateTime::createFromFormat('d/M/Y:H:i:s', $raw_date);
                
                if ($dt) {
                    $fecha = $dt->format('Y-m-d H:i:s');
                } else {
                    // Si falla el formato, usamos la hora actual del sistema para no perder el registro
                    $fecha = date('Y-m-d H:i:s');
                }
                // -----------------------------------------------

                $metodo = $matches[3];
                $recurso = $link->real_escape_string($matches[4]);
                $status = $matches[6];
                $bytes = ($matches[7] == '-') ? 0 : $matches[7];
                $ua = $link->real_escape_string($matches[9]);

                // Inserción inicial rápida
                $sql = "INSERT INTO log_indexado 
                        (dominio, ip_remota, fecha_acceso, metodo, recurso, http_status, bytes, user_agent, ipbanned) 
                        VALUES 
                        ('$dominio', '$ip', '$fecha', '$metodo', '$recurso', '$status', '$bytes', '$ua', 'NO')";
                
                if ($link->query($sql)) {
                    $contador++;
                }
            }
        }
        fclose($handle);
        
        // Guardar cambios en DB
        $link->commit();
        $link->autocommit(TRUE);

        // --- BANEO MASIVO POST-IMPORTACIÓN ---
        // Cruce final contra la tabla de baneos maestros
        $link->query("UPDATE log_indexado 
                      SET ipbanned = 'YES' 
                      WHERE ip_remota IN (SELECT ip FROM master_bans)
                      AND ipbanned = 'NO'");

        // 3. LIMPIEZA: Borrar archivo procesado
        unlink($archivo); 
        
        header("Location: index.php?msg=Importados_$contador");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Logs | ThulioNef</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 80px; background: #f0f2f5; font-family: sans-serif; }
        .card { border-radius: 12px; border: none; }
        .btn-success { background-color: #28a745; border: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark fixed-top shadow">
    <div class="container">
        <a class="navbar-brand" href="index.php">← Volver al Dashboard</a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-lg p-4">
                <h4 class="text-center mb-4">Carga Forense de Logs</h4>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group mb-4">
                        <label class="font-weight-bold">Seleccionar archivo .txt:</label>
                        <select name="nombre_archivo" class="form-control form-control-lg" required>
                            <option value="">-- Seleccione un archivo --</option>
                            <?php foreach($archivos_log as $file): ?>
                                <option value="<?php echo $file; ?>">
                                    <?php echo $file; ?> (<?php echo round(filesize($file)/1024, 2); ?> KB)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="font-weight-bold">Dominio / Etiqueta:</label>
                        <input type="text" name="dominio_nombre" class="form-control" placeholder="ej: foro.com" required>
                    </div>
                    
                    <div class="alert alert-info small">
                        <strong>Nota de Ingeniería:</strong> El sistema utiliza <code>DateTime::createFromFormat</code> para evitar el error 1970-01-01 y procesa los baneos masivamente al finalizar.
                    </div>

                    <button type="submit" name="ejecutar_importacion" class="btn btn-success btn-block btn-lg">
                        Procesar e Indexar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
