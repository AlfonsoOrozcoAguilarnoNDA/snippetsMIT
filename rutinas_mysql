<?php
/**
 * ==============================================================================
 * COMPARADOR DE RUTINAS Y TRIGGERS MySQL / MariaDB
 * ==============================================================================
 * @project     Comun
 * @file        rutinas_mysql.php
 * @version     1.0.0
 * @author      Alfonso Orozco
 * @created     24 / Nov / 2026
 * @updated     24 / Nov / 2026
 * @license     MIT
 *
 * MIT License
 * Copyright (c) 2026 Alfonso Orozco
 * Se concede permiso, libre de cargos, a cualquier persona que obtenga una copia
 * de este software y de los archivos de documentación asociados, para utilizarlo
 * sin restricción, incluyendo copiar, modificar, fusionar, publicar, distribuir,
 * sublicenciar y/o vender copias, siempre que se incluya este aviso de copyright
 * y de permiso en todas las copias o partes sustanciales del software.
 * EL SOFTWARE SE ENTREGA "TAL CUAL", SIN GARANTÍA DE NINGÚN TIPO.
 *
 * ------------------------------------------------------------------------------
 * CASO DE USO
 * ------------------------------------------------------------------------------
 * Complemento de inventario_mysql.php. Aquel responde "qué tablas hay y dónde";
 * este responde "qué hace el código almacenado y si es el mismo en cada servidor".
 *   - Solo considera PROCEDURE, FUNCTION y TRIGGER (no tablas ni vistas).
 *   - Usa el nombre del objeto como clave entre servidores (igual que con las
 *     tablas) y compara por sha1: hash del cuerpo y hash completo (cuerpo + firma
 *     y atributos, sin DEFINER).
 *   - Busca una palabra fija (PALABRA_BUSCAR) en el código, como palabra completa
 *     y sin distinguir mayúsculas, e indica cuántas veces y en qué líneas.
 *   - Solo lee information_schema. No modifica nada.
 *
 * Funcionamiento: igual que el inventario. Se piden DOS claves: la del script
 * (validada contra un hash) y la de MySQL (se pega en el formulario y no se
 * guarda). Ejecutar en local: php -S 127.0.0.1:8000
 *
 * Generar el hash de la clave del script:
 *   php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Ver al final de este archivo y de la página las ADVERTENCIAS de interpretación.
 * ==============================================================================
 */

// ============================================
// CONFIGURACIÓN
// ============================================
define('APP_VERSION', '1.0.0');
define('PASS_HASH', 'REEMPLAZAR_CON_HASH');

// Palabra a buscar dentro del código de rutinas y triggers (editar aquí)
define('PALABRA_BUSCAR', 'user'); // puede ser buena idea usar el nombre de la tabla de usuarios

$SERVERS = array(
    array('nombre' => 'SRV-150', 'host' => '192.168.15.150', 'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
    array('nombre' => 'SRV-163', 'host' => '192.168.15.163', 'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
    array('nombre' => 'SRV-51',  'host' => '192.168.14.51',  'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
);

$ESQUEMAS_SISTEMA = array('information_schema', 'mysql', 'performance_schema', 'sys');

$ESTADOS = array(
    'ident'       => 'Idéntica en todos',
    'ident_falta' => 'Idéntica (falta en algún servidor)',
    'attr'        => 'Mismo cuerpo, atributos distintos',
    'dist'        => 'Cuerpo distinto',
    'solo1'       => 'Solo en un servidor',
    'sinacc'      => 'Sin acceso a la definición',
);
$ESTADO_CLASE = array(
    'ident' => 'badge-yes', 'ident_falta' => 'badge-info', 'attr' => 'badge-warn',
    'dist' => 'badge-no', 'solo1' => 'badge-secondary', 'sinacc' => 'badge-dark',
);

// ============================================
// FUNCIONES
// ============================================
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function conectar($s, $pass) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $c = mysqli_init();
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) $c->options(MYSQLI_OPT_READ_TIMEOUT, 60);
    $flags = 0;
    if (!empty($s['ssl'])) {
        $c->ssl_set(null, null, null, null, null);
        $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    }
    if (!@$c->real_connect($s['host'], $s['user'], $pass, '', (int)$s['port'], null, $flags)) {
        return array(null, mysqli_connect_error());
    }
    @$c->set_charset('utf8mb4');
    return array($c, null);
}

/**
 * Recorre SQL respetando cadenas ('..', "..", `..`).
 * $modoCom: 'keep' conserva comentarios, 'strip' los elimina, 'mask' los cambia
 * por espacios conservando los saltos de línea (para ubicar líneas al buscar).
 * $colapsar: reduce cualquier secuencia de espacios/saltos fuera de cadenas a uno.
 * Los comentarios condicionales /*! ... */ y /*M! ... */ se conservan: son código.
 */
function recorrerSql($sql, $modoCom, $colapsar) {
    $n = strlen($sql);
    $out = '';
    $pend = false;
    $i = 0;
    while ($i < $n) {
        $ch = $sql[$i];
        $nx = ($i + 1 < $n) ? $sql[$i + 1] : '';

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $q = $ch;
            $j = $i + 1;
            while ($j < $n) {
                if ($sql[$j] === '\\' && $q !== '`') { $j += 2; continue; }
                if ($sql[$j] === $q) {
                    if ($j + 1 < $n && $sql[$j + 1] === $q) { $j += 2; continue; }
                    break;
                }
                $j++;
            }
            if ($pend) { $out .= ' '; $pend = false; }
            $out .= substr($sql, $i, $j - $i + 1);
            $i = $j + 1;
            continue;
        }

        $fin = -1;
        if (($ch === '-' && $nx === '-' && ($i + 2 >= $n || ctype_space($sql[$i + 2]))) || $ch === '#') {
            $p = strpos($sql, "\n", $i);
            $fin = ($p === false) ? $n : $p;
        } elseif ($ch === '/' && $nx === '*') {
            $c3 = ($i + 2 < $n) ? $sql[$i + 2] : '';
            $c4 = ($i + 3 < $n) ? $sql[$i + 3] : '';
            if ($c3 !== '!' && !($c3 === 'M' && $c4 === '!')) {
                $p = strpos($sql, '*/', $i + 2);
                $fin = ($p === false) ? $n : $p + 2;
            }
        }
        if ($fin >= 0) {
            $seg = substr($sql, $i, $fin - $i);
            if ($modoCom === 'keep') {
                if ($pend) { $out .= ' '; $pend = false; }
                $out .= $seg;
            } elseif ($modoCom === 'mask') {
                $out .= preg_replace('/[^\n]/', ' ', $seg);
            } else {
                if ($colapsar) $pend = true; else $out .= ' ';
            }
            $i = $fin;
            continue;
        }

        if ($colapsar && ctype_space($ch)) { $pend = true; $i++; continue; }
        if ($pend) { $out .= ' '; $pend = false; }
        $out .= $ch;
        $i++;
    }
    if ($modoCom !== 'mask') {
        if (!$colapsar) $out = preg_replace('/[ \t]+\n/', "\n", $out);
        $out = trim($out);
    }
    return $out;
}

function armarEntrada($schema, $tipo, $nombre, $cuerpo, $firma, $opt) {
    $e = array(
        'schema' => $schema, 'tipo' => $tipo, 'nombre' => $nombre, 'acceso' => true,
        'hb' => '', 'hc' => '', 'lineas' => 0, 'hits' => 0, 'hit_lines' => array(),
    );
    if ($cuerpo === null || trim($cuerpo) === '') {
        $e['acceso'] = false;   // NULL = sin privilegio; no se hashea para evitar falsos "iguales"
        return $e;
    }
    $cuerpo = str_replace(array("\r\n", "\r"), "\n", $cuerpo);
    $norm = recorrerSql($cuerpo, $opt['com_hash'] ? 'strip' : 'keep', $opt['colapsar']);
    $e['hb'] = sha1($norm);
    $e['hc'] = sha1($firma . "\n--\n" . $norm);
    $e['lineas'] = substr_count($cuerpo, "\n") + 1;

    if (PALABRA_BUSCAR !== '') {
        $buscar = $opt['com_busq'] ? recorrerSql($cuerpo, 'mask', false) : $cuerpo;
        $re = '/(?<![A-Za-z0-9_$])' . preg_quote(PALABRA_BUSCAR, '/') . '(?![A-Za-z0-9_$])/i';
        $e['hits'] = (int)preg_match_all($re, $buscar);
        if ($e['hits'] > 0) {
            $ls = explode("\n", $buscar);
            foreach ($ls as $n => $l) {
                if (preg_match($re, $l)) $e['hit_lines'][] = $n + 1;
            }
        }
    }
    return $e;
}

function cargarObjetos($c, $sistema, $esquemaFiltro, $opt) {
    $lista = "'" . implode("','", array_map(array($c, 'real_escape_string'), $sistema)) . "'";
    $fR = $fP = $fT = '';
    if ($esquemaFiltro !== '') {
        $e = $c->real_escape_string($esquemaFiltro);
        $fR = " AND ROUTINE_SCHEMA = '$e'";
        $fP = " AND SPECIFIC_SCHEMA = '$e'";
        $fT = " AND TRIGGER_SCHEMA = '$e'";
    }
    $res = array('objetos' => array(), 'errores' => array(), 'version' => '', 'es_maria' => false);

    $r = @$c->query('SELECT VERSION(), @@version_comment');
    if ($r) {
        $row = $r->fetch_row();
        $res['version'] = $row[0];
        $res['es_maria'] = stripos($row[0] . ' ' . $row[1], 'mariadb') !== false;
        $r->free();
    }

    // Parámetros por rutina
    $params = array();
    $r = @$c->query("SELECT SPECIFIC_SCHEMA, SPECIFIC_NAME, ROUTINE_TYPE, ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DTD_IDENTIFIER
                     FROM information_schema.PARAMETERS
                     WHERE SPECIFIC_SCHEMA NOT IN ($lista) AND ORDINAL_POSITION > 0 $fP
                     ORDER BY SPECIFIC_SCHEMA, SPECIFIC_NAME, ORDINAL_POSITION");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $k = $row['SPECIFIC_SCHEMA'] . '|' . $row['ROUTINE_TYPE'] . '|' . $row['SPECIFIC_NAME'];
            $modo = ($row['PARAMETER_MODE'] === null) ? 'IN' : strtoupper($row['PARAMETER_MODE']);
            $params[$k][] = $modo . ' ' . $row['PARAMETER_NAME'] . ' ' . strtolower(preg_replace('/\s+/', ' ', (string)$row['DTD_IDENTIFIER']));
        }
        $r->free();
    } else {
        $res['errores'][] = 'PARAMETERS: ' . $c->error;
    }

    // Rutinas
    $r = @$c->query("SELECT ROUTINE_SCHEMA, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER, ROUTINE_DEFINITION,
                            IS_DETERMINISTIC, SECURITY_TYPE, SQL_MODE
                     FROM information_schema.ROUTINES
                     WHERE ROUTINE_SCHEMA NOT IN ($lista) $fR
                     ORDER BY ROUTINE_SCHEMA, ROUTINE_TYPE, ROUTINE_NAME");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $k = $row['ROUTINE_SCHEMA'] . '|' . $row['ROUTINE_TYPE'] . '|' . $row['ROUTINE_NAME'];
            $firma = $row['ROUTINE_TYPE'] . '|' . (isset($params[$k]) ? implode(',', $params[$k]) : '')
                   . '|' . strtolower((string)$row['DTD_IDENTIFIER'])
                   . '|' . strtoupper((string)$row['IS_DETERMINISTIC'])
                   . '|' . strtoupper((string)$row['SECURITY_TYPE']);
            if ($opt['sqlmode']) $firma .= '|' . $row['SQL_MODE'];
            $res['objetos'][] = armarEntrada($row['ROUTINE_SCHEMA'], $row['ROUTINE_TYPE'], $row['ROUTINE_NAME'],
                                             $row['ROUTINE_DEFINITION'], $firma, $opt);
        }
        $r->free();
    } else {
        $res['errores'][] = 'ROUTINES: ' . $c->error;
    }

    // Triggers
    $r = @$c->query("SELECT TRIGGER_SCHEMA, TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE,
                            ACTION_TIMING, ACTION_ORIENTATION, ACTION_STATEMENT, SQL_MODE
                     FROM information_schema.TRIGGERS
                     WHERE TRIGGER_SCHEMA NOT IN ($lista) $fT
                     ORDER BY TRIGGER_SCHEMA, TRIGGER_NAME");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $firma = 'TRIGGER|' . strtoupper($row['ACTION_TIMING']) . '|' . strtoupper($row['EVENT_MANIPULATION'])
                   . '|' . $row['EVENT_OBJECT_TABLE'] . '|' . strtoupper($row['ACTION_ORIENTATION']);
            if ($opt['sqlmode']) $firma .= '|' . $row['SQL_MODE'];
            $res['objetos'][] = armarEntrada($row['TRIGGER_SCHEMA'], 'TRIGGER', $row['TRIGGER_NAME'],
                                             $row['ACTION_STATEMENT'], $firma, $opt);
        }
        $r->free();
    } else {
        $res['errores'][] = 'TRIGGERS: ' . $c->error;
    }
    return $res;
}

function badgeTipo($t) {
    if ($t === 'PROCEDURE') return '<span class="badge badge-table"><i class="fas fa-cogs mr-1"></i>Procedimiento</span>';
    if ($t === 'FUNCTION')  return '<span class="badge badge-view"><i class="fas fa-function mr-1"></i>Función</span>';
    return '<span class="badge badge-trig"><i class="fas fa-bolt mr-1"></i>Trigger</span>';
}

// ============================================
// PROCESAMIENTO
// ============================================
$error_login = '';
$resultados = null;
$tiempo_ms = 0;
$hashPendiente = (PASS_HASH === 'REEMPLAZAR_CON_HASH');
$post = ($_SERVER['REQUEST_METHOD'] === 'POST');

$opt = array(
    'com_hash' => $post ? !empty($_POST['com_hash']) : true,
    'colapsar' => $post ? !empty($_POST['colapsar']) : true,
    'com_busq' => $post ? !empty($_POST['com_busq']) : true,
    'sqlmode'  => $post ? !empty($_POST['sqlmode'])  : false,
);
$esquemaFiltro = $post && isset($_POST['esquema']) ? trim((string)$_POST['esquema']) : '';

if ($post) {
    $passScript = isset($_POST['pass_script']) ? (string)$_POST['pass_script'] : '';
    $passDb     = isset($_POST['pass_db']) ? (string)$_POST['pass_db'] : '';

    if ($hashPendiente) {
        $error_login = 'Primero define PASS_HASH en el script (ver instrucciones abajo).';
    } elseif (!password_verify($passScript, PASS_HASH)) {
        sleep(1);
        $error_login = 'Clave del script incorrecta';
    } elseif ($passDb === '') {
        $error_login = 'Falta la contraseña de MySQL';
    } else {
        $inicio = microtime(true);
        $resultados = array();
        foreach ($SERVERS as $s) {
            list($c, $err) = conectar($s, $passDb);
            $item = array('srv' => $s, 'error' => $err, 'info' => null);
            if ($c) {
                $item['info'] = cargarObjetos($c, $ESQUEMAS_SISTEMA, $esquemaFiltro, $opt);
                $c->close();
            }
            $resultados[] = $item;
        }
        $passDb = null;
        $tiempo_ms = round((microtime(true) - $inicio) * 1000, 2);
    }
}

// Matriz por clave "TIPO|nombre" y estado de comparación
$matriz = array();
$conectados = 0;
$conteoEstados = array();
$filasJs = array();
if ($resultados !== null) {
    foreach ($resultados as $i => $item) {
        if (!$item['info']) continue;
        $conectados++;
        foreach ($item['info']['objetos'] as $o) {
            $k = $o['tipo'] . '|' . $o['nombre'];
            $matriz[$k]['tipo'] = $o['tipo'];
            $matriz[$k]['nombre'] = $o['nombre'];
            $matriz[$k]['por'][$i][] = $o;
        }
    }
    ksort($matriz);
    foreach ($matriz as $k => &$m) {
        $hcs = array(); $hbs = array(); $sin = false; $pal = false;
        $presentes = count($m['por']);
        foreach ($m['por'] as $ents) {
            foreach ($ents as $e) {
                if (!$e['acceso']) $sin = true;
                else { $hcs[$e['hc']] = 1; $hbs[$e['hb']] = 1; }
                if ($e['hits'] > 0) $pal = true;
            }
        }
        if ($sin)                                   $cod = 'sinacc';
        elseif ($conectados >= 2 && $presentes == 1) $cod = 'solo1';
        elseif (count($hcs) == 1)                   $cod = ($presentes == $conectados) ? 'ident' : 'ident_falta';
        elseif (count($hbs) == 1)                   $cod = 'attr';
        else                                        $cod = 'dist';
        $m['cod'] = $cod;
        $m['pal'] = $pal;
        $m['color'] = array_flip(array_keys($hcs));
        if (!isset($conteoEstados[$cod])) $conteoEstados[$cod] = 0;
        $conteoEstados[$cod]++;
        $filasJs[] = array('tipo' => $m['tipo'], 'name' => $m['nombre'], 'cod' => $cod, 'pal' => $pal);
    }
    unset($m);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comparador de rutinas y triggers</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.1/css/buttons.bootstrap4.min.css">
<style>
    body {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Roboto, sans-serif;
        padding: 20px;
    }
    .card-custom { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .section-title {
        font-weight: 600; color: #1e3c72;
        border-left: 5px solid #2a5298;
        padding-left: 12px; margin: 28px 0 15px 0;
    }
    .table-custom thead { background: #1e3c72; color: white; }
    .table-custom tbody tr:hover { background: #eef3fb; }
    .badge-yes   { background: #28a745; color: #fff; }
    .badge-no    { background: #dc3545; color: #fff; }
    .badge-warn  { background: #ffc107; color: #212529; }
    .badge-table { background: #2a5298; color: #fff; }
    .badge-view  { background: #17a2b8; color: #fff; }
    .badge-trig  { background: #6f42c1; color: #fff; }
    .badge-srv   { background: #1e3c72; color: #fff; }
    .hc0 { background: #2a5298; color: #fff; }
    .hc1 { background: #e83e8c; color: #fff; }
    .hc2 { background: #fd7e14; color: #fff; }
    .hc3 { background: #6f42c1; color: #fff; }
    .hc4 { background: #20c997; color: #fff; }
    .hc5 { background: #6c757d; color: #fff; }
    .hash { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.78rem; letter-spacing: .3px; }
    code.path {
        background: #f4f6fb; padding: 2px 6px; border-radius: 4px;
        color: #2a5298; font-size: 0.88rem;
    }
    .stat-box {
        background: #f8f9fc; border-radius: 10px; padding: 10px 14px;
        border-left: 4px solid #2a5298; margin-bottom: 8px;
    }
    .stat-box .label { font-size: 0.72rem; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
    .stat-box .value { font-size: 0.95rem; font-weight: 600; color: #1e3c72; word-break: break-all; }
    .btn-primary-custom { background: #2a5298; border: none; padding: 10px 20px; font-weight: 600; }
    .btn-primary-custom:hover { background: #1e3c72; }
    .srv-head { background: #1e3c72; color: #fff; border-radius: 15px 15px 0 0; padding: 12px 16px; }
    .srv-head.err { background: #dc3545; }
    .mini-stat { text-align: center; padding: 10px 4px; border-radius: 10px; color: #fff; margin-bottom: 8px; }
    .mini-stat .num { font-size: 1.4rem; font-weight: 700; line-height: 1; }
    .mini-stat .lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px; opacity: .9; }
    .bg-m-proc { background: linear-gradient(135deg, #2a5298, #1e3c72); }
    .bg-m-func { background: linear-gradient(135deg, #17a2b8, #11707f); }
    .bg-m-trig { background: linear-gradient(135deg, #6f42c1, #4e2d8c); }
    .bg-m-pal  { background: linear-gradient(135deg, #ffc107, #d39e00); color: #212529 !important; }
    textarea.nombres { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; }
    .dataTables_wrapper .dt-buttons { margin-bottom: 8px; }
    td.cell-m { white-space: nowrap; }
    .adv li { margin-bottom: 8px; }
</style>
</head>
<body>
<div class="container-fluid" style="max-width: 1400px;">
<div class="card card-custom">
<div class="card-body p-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">
            <i class="fas fa-code-branch text-primary mr-2"></i>Rutinas y triggers
            <small class="text-muted" style="font-size:.55em;">comparación por hash · búsqueda de palabra</small>
        </h3>
        <?php if ($resultados !== null): ?>
            <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt mr-1"></i>Limpiar</a>
        <?php endif; ?>
    </div>

    <?php if ($hashPendiente): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i><strong>Configuración pendiente.</strong>
            Genera el hash de tu clave y pégalo en <code>PASS_HASH</code>:
            <br><code>php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"</code>
            <br>Revisa también el usuario en <code>$SERVERS</code>.
        </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <form method="POST" autocomplete="off" class="mb-2">
        <?php if ($error_login): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-triangle mr-2"></i><?= h($error_login) ?></div>
        <?php endif; ?>
        <div class="form-row">
            <div class="col-md-3 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-shield-alt"></i></span></div>
                    <input type="password" name="pass_script" class="form-control" placeholder="Clave del script" required autofocus autocomplete="off">
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                    <input type="password" name="pass_db" class="form-control" placeholder="Contraseña de MySQL" required autocomplete="new-password">
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-database"></i></span></div>
                    <input type="text" name="esquema" class="form-control" placeholder="Esquema (opcional, vacío = todos)" value="<?= h($esquemaFiltro) ?>">
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-primary btn-block btn-primary-custom"><i class="fas fa-rocket mr-1"></i>Analizar</button>
            </div>
        </div>
        <div class="d-flex flex-wrap mb-1">
            <div class="custom-control custom-checkbox mr-4"><input type="checkbox" class="custom-control-input" id="com_hash" name="com_hash" value="1"<?= $opt['com_hash'] ? ' checked' : '' ?>><label class="custom-control-label" for="com_hash">Ignorar comentarios en el hash</label></div>
            <div class="custom-control custom-checkbox mr-4"><input type="checkbox" class="custom-control-input" id="colapsar" name="colapsar" value="1"<?= $opt['colapsar'] ? ' checked' : '' ?>><label class="custom-control-label" for="colapsar">Ignorar espacios e indentación</label></div>
            <div class="custom-control custom-checkbox mr-4"><input type="checkbox" class="custom-control-input" id="com_busq" name="com_busq" value="1"<?= $opt['com_busq'] ? ' checked' : '' ?>><label class="custom-control-label" for="com_busq">Ignorar comentarios en la búsqueda</label></div>
            <div class="custom-control custom-checkbox mr-4"><input type="checkbox" class="custom-control-input" id="sqlmode" name="sqlmode" value="1"<?= $opt['sqlmode'] ? ' checked' : '' ?>><label class="custom-control-label" for="sqlmode">Incluir sql_mode en el hash completo</label></div>
        </div>
        <small class="text-muted">
            <i class="fas fa-server mr-1"></i>Servidores:
            <?php foreach ($SERVERS as $s): ?><code class="path"><?= h($s['host']) ?>:<?= (int)$s['port'] ?></code> <?php endforeach; ?>
            · Palabra buscada: <code class="path"><?= h(PALABRA_BUSCAR) ?></code> (palabra completa, sin distinguir mayúsculas)
            · La contraseña de MySQL no se guarda.
        </small>
    </form>

<?php if ($resultados !== null): ?>

    <!-- SERVIDORES -->
    <h5 class="section-title"><i class="fas fa-server text-primary mr-2"></i>1. Servidores</h5>
    <div class="row">
    <?php foreach ($resultados as $item):
        $s = $item['srv']; $info = $item['info'];
        $nP = $nF = $nT = $nSin = $nPal = 0;
        if ($info) {
            foreach ($info['objetos'] as $o) {
                if ($o['tipo'] === 'PROCEDURE') $nP++;
                elseif ($o['tipo'] === 'FUNCTION') $nF++;
                else $nT++;
                if (!$o['acceso']) $nSin++;
                if ($o['hits'] > 0) $nPal++;
            }
        }
    ?>
        <div class="col-lg-4 mb-3">
            <div class="card card-custom h-100">
                <div class="srv-head<?= $info ? '' : ' err' ?>">
                    <strong><i class="fas fa-server mr-2"></i><?= h($s['nombre']) ?></strong>
                    <span class="float-right small"><?= h($s['host']) ?>:<?= (int)$s['port'] ?></span>
                </div>
                <div class="card-body">
                <?php if (!$info): ?>
                    <div class="alert alert-danger mb-0"><i class="fas fa-plug mr-2"></i><strong>No se pudo conectar.</strong><br><small><?= h($item['error']) ?></small></div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-3"><div class="mini-stat bg-m-proc"><div class="num"><?= $nP ?></div><div class="lbl">Proc.</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-func"><div class="num"><?= $nF ?></div><div class="lbl">Func.</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-trig"><div class="num"><?= $nT ?></div><div class="lbl">Triggers</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-pal"><div class="num"><?= $nPal ?></div><div class="lbl">Con palabra</div></div></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Versión</div>
                        <div class="value"><?= h($info['version']) ?>
                            <span class="badge <?= $info['es_maria'] ? 'badge-warn' : 'badge-table' ?> ml-1"><?= $info['es_maria'] ? 'MariaDB' : 'MySQL' ?></span></div>
                    </div>
                    <?php if ($nSin > 0): ?>
                        <div class="alert alert-warning py-2 mb-2 small"><i class="fas fa-lock mr-1"></i><strong><?= $nSin ?></strong> objeto(s) sin acceso a la definición (faltan privilegios EXECUTE o TRIGGER). No se comparan.</div>
                    <?php endif; ?>
                    <?php foreach ($info['errores'] as $er): ?>
                        <div class="alert alert-danger py-2 mb-2 small"><?= h($er) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- RESUMEN -->
    <h5 class="section-title"><i class="fas fa-balance-scale text-primary mr-2"></i>2. Resumen de la comparación <small class="text-muted">(<?= count($matriz) ?> objetos distintos por nombre)</small></h5>
    <div class="row">
        <?php foreach ($ESTADOS as $cod => $lbl): ?>
            <div class="col-md-4 col-lg-2">
                <div class="stat-box">
                    <div class="label"><span class="badge <?= $ESTADO_CLASE[$cod] ?>">&nbsp;</span> <?= h($lbl) ?></div>
                    <div class="value"><?= isset($conteoEstados[$cod]) ? $conteoEstados[$cod] : 0 ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- MATRIZ -->
    <h5 class="section-title"><i class="fas fa-columns text-primary mr-2"></i>3. Comparación por nombre entre servidores</h5>
    <p class="text-muted small mb-2">Cada celda muestra los 8 primeros caracteres del hash completo. El mismo color en una fila significa el mismo hash. Pasa el ratón para ver el hash del cuerpo y el completo.</p>
    <div class="form-row mb-2">
        <div class="col-md-3 mb-2">
            <select id="mTipo" class="custom-select custom-select-sm">
                <option value="">Todos los tipos</option>
                <option value="Procedimiento">Procedimientos</option>
                <option value="Función">Funciones</option>
                <option value="Trigger">Triggers</option>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <select id="mEstado" class="custom-select custom-select-sm">
                <option value="">Todos los estados</option>
                <?php foreach ($ESTADOS as $cod => $lbl): ?><option value="<?= h($lbl) ?>"><?= h($lbl) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <select id="mPal" class="custom-select custom-select-sm">
                <option value="">Con y sin la palabra</option>
                <option value="SÍ">Contienen "<?= h(PALABRA_BUSCAR) ?>"</option>
                <option value="NO">No la contienen</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table id="tblMat" class="table table-sm table-bordered table-custom" style="width:100%">
            <thead>
                <tr>
                    <th>Tipo</th><th>Objeto</th><th>Estado</th><th class="text-center">Palabra</th>
                    <?php foreach ($resultados as $item): ?><th class="text-center"><?= h($item['srv']['nombre']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matriz as $k => $m): ?>
                <tr>
                    <td><?= badgeTipo($m['tipo']) ?></td>
                    <td><?= h($m['nombre']) ?></td>
                    <td><span class="badge <?= $ESTADO_CLASE[$m['cod']] ?>"><?= h($ESTADOS[$m['cod']]) ?></span></td>
                    <td class="text-center"><span class="badge <?= $m['pal'] ? 'badge-warn' : 'badge-secondary' ?>"><?= $m['pal'] ? 'SÍ' : 'NO' ?></span></td>
                    <?php foreach ($resultados as $i => $item): ?>
                        <td class="cell-m text-center">
                        <?php if (!isset($m['por'][$i])): ?>
                            <?php if ($item['info']): ?><span class="badge badge-no">NO</span><?php else: ?><span class="badge badge-secondary">—</span><?php endif; ?>
                        <?php else: foreach ($m['por'][$i] as $e): ?>
                            <?php if (!$e['acceso']): ?>
                                <span class="badge badge-dark" title="Sin privilegios para leer la definición">sin acceso</span>
                            <?php else: $cc = $m['color'][$e['hc']] % 6; ?>
                                <span class="badge hash hc<?= $cc ?>" title="cuerpo: <?= h($e['hb']) ?> | completo: <?= h($e['hc']) ?>"><?= h(substr($e['hc'], 0, 8)) ?></span>
                            <?php endif; ?>
                            <small class="text-muted"><?= h($e['schema']) ?></small>
                            <?php if ($e['hits'] > 0): ?><span class="badge badge-warn" title="Líneas: <?= h(implode(', ', $e['hit_lines'])) ?>"><?= h(PALABRA_BUSCAR) ?> ×<?= (int)$e['hits'] ?></span><?php endif; ?>
                            <br>
                        <?php endforeach; endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- DETALLE -->
    <h5 class="section-title"><i class="fas fa-list text-primary mr-2"></i>4. Detalle por servidor</h5>
    <div class="table-responsive">
        <table id="tblDet" class="table table-sm table-bordered table-custom" style="width:100%">
            <thead>
                <tr>
                    <th>Servidor</th><th>Esquema</th><th>Tipo</th><th>Objeto</th>
                    <th>Hash cuerpo</th><th>Hash completo</th>
                    <th class="text-right">Líneas</th><th class="text-center">Acceso</th>
                    <th class="text-right">Veces "<?= h(PALABRA_BUSCAR) ?>"</th><th>Líneas con la palabra</th>
                    <th>Hash cuerpo (40)</th><th>Hash completo (40)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resultados as $item):
                if (!$item['info']) continue;
                foreach ($item['info']['objetos'] as $o): ?>
                <tr>
                    <td><span class="badge badge-srv"><?= h($item['srv']['nombre']) ?></span></td>
                    <td><code class="path"><?= h($o['schema']) ?></code></td>
                    <td><?= badgeTipo($o['tipo']) ?></td>
                    <td><?= h($o['nombre']) ?></td>
                    <td class="hash"><?= $o['acceso'] ? h(substr($o['hb'], 0, 12)) : '—' ?></td>
                    <td class="hash"><?= $o['acceso'] ? h(substr($o['hc'], 0, 12)) : '—' ?></td>
                    <td class="text-right"><?= $o['acceso'] ? (int)$o['lineas'] : '—' ?></td>
                    <td class="text-center"><span class="badge <?= $o['acceso'] ? 'badge-yes' : 'badge-dark' ?>"><?= $o['acceso'] ? 'SÍ' : 'NO' ?></span></td>
                    <td class="text-right"><?= $o['hits'] > 0 ? (int)$o['hits'] : '—' ?></td>
                    <td><?= $o['hits'] > 0 ? h(implode(', ', array_slice($o['hit_lines'], 0, 30))) : '—' ?></td>
                    <td class="hash"><?= h($o['hb']) ?></td>
                    <td class="hash"><?= h($o['hc']) ?></td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TEXTBOX -->
    <h5 class="section-title"><i class="fas fa-clipboard-list text-primary mr-2"></i>5. Nombres para copiar</h5>
    <div class="form-row mb-2">
        <div class="col-md-4 mb-2">
            <select id="lSel" class="custom-select custom-select-sm">
                <option value="pal">Contienen "<?= h(PALABRA_BUSCAR) ?>"</option>
                <option value="dist">Distintas (cuerpo o atributos)</option>
                <option value="falta">Falta en algún servidor</option>
                <option value="sinacc">Sin acceso a la definición</option>
                <option value="ident">Idénticas en todos</option>
            </select>
        </div>
        <div class="col-md-8 mb-2 d-flex align-items-center">
            <div class="custom-control custom-checkbox mr-3"><input type="checkbox" class="custom-control-input" id="lTipo"><label class="custom-control-label" for="lTipo">Incluir tipo (PROCEDURE/FUNCTION/TRIGGER)</label></div>
            <button type="button" id="btnCopiar" class="btn btn-sm btn-outline-primary"><i class="fas fa-copy mr-1"></i>Copiar</button>
        </div>
    </div>
    <textarea id="txtNombres" class="form-control nombres" rows="8" readonly></textarea>
    <small id="txtCount" class="text-muted"></small>

    <!-- INFO -->
    <h5 class="section-title"><i class="fas fa-clock text-warning mr-2"></i>6. Información de la consulta</h5>
    <div class="row">
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-stopwatch mr-1"></i>Tiempo total</div><div class="value"><?= $tiempo_ms ?> ms</div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-code-branch mr-1"></i>Versión del script</div><div class="value">v<?= APP_VERSION ?></div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-folder-open mr-1"></i>Ruta del script</div><div class="value" style="font-size:0.85rem;"><?= h(__FILE__) ?></div></div></div>
    </div>

<?php endif; ?>

    <!-- ADVERTENCIAS -->
    <h5 class="section-title"><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Advertencias de interpretación</h5>
    <ol class="adv small text-muted pl-4">
        <li><strong>Permisos.</strong> <code>ROUTINE_DEFINITION</code> y <code>ACTION_STATEMENT</code> salen en <code>NULL</code> si el usuario no es el definer ni tiene <code>EXECUTE</code> (rutinas) o <code>TRIGGER</code> (triggers). Esos objetos se marcan "sin acceso" y no se comparan, porque hashear un <code>NULL</code> daría falsos "iguales". Es lo primero que hay que revisar: si hay muchos, el resultado es incompleto.</li>
        <li><strong>Iguales no es lo mismo que equivalentes.</strong> Hash distinto puede ser solo un comentario o la indentación; hash igual solo garantiza texto igual, no que el comportamiento sea el mismo (depende de tablas, datos y configuración). Las opciones de normalización reducen el ruido, pero no lo eliminan.</li>
        <li><strong>MySQL 8.4 contra MariaDB 11.8.</strong> El texto de la definición, los tipos de los parámetros y el <code>sql_mode</code> pueden diferir aunque la rutina sea la misma. Por eso hay dos hashes: "Mismo cuerpo, atributos distintos" suele ser esa diferencia de motor, no un cambio real. El <code>DEFINER</code> no entra en el hash.</li>
        <li><strong>La búsqueda detecta texto, no uso real.</strong> No ve SQL dinámico armado por partes (<code>PREPARE</code>/<code>CONCAT</code>), ni referencias hechas a través de otra rutina, vista o evento. También cuenta la palabra si aparece dentro de una cadena de texto, y no cuenta formas como <code>user_id</code> (palabra completa).</li>
        <li><strong>La clave es el nombre.</strong> Se compara por tipo y nombre, sin importar el esquema. Si un servidor tiene el mismo nombre en varios esquemas, se muestran todos y pueden marcar el estado como distinto.</li>
        <li><strong>Eventos, vistas y tablas quedan fuera.</strong> Solo se consideran procedimientos, funciones y triggers. Los eventos programados (<code>EVENTS</code>) también pueden contener código, y no se leen.</li>
        <li><strong>Uso.</strong> Script de lectura para entorno local o de confianza: no lo dejes publicado y bórralo al terminar.</li>
    </ol>

</div>
</div>
<p class="text-center text-white-50 small mt-3">
    <i class="fas fa-code-branch mr-1"></i>Rutinas y triggers v<?= APP_VERSION ?> — PHP <?= PHP_VERSION ?> — Licencia MIT
</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($resultados !== null): ?>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.1/js/buttons.colVis.min.js"></script>
<script>
var FILAS = <?= json_encode($filasJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

$(function () {
    var idioma = {
        search: 'Buscar:', lengthMenu: 'Mostrar _MENU_', info: '_START_ a _END_ de _TOTAL_',
        infoEmpty: 'Sin registros', infoFiltered: '(filtrado de _MAX_)', zeroRecords: 'Sin coincidencias',
        paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    };
    var domCfg = "<'row'<'col-md-7'B><'col-md-5'f>>rt<'row'<'col-md-4'i><'col-md-4'l><'col-md-4'p>>";
    var btns = function (titulo, cols) {
        var ex = cols ? { columns: cols } : {};
        return [
            { extend: 'copyHtml5',  text: '<i class="fas fa-copy"></i> Copiar', className: 'btn-sm btn-outline-primary', title: null, exportOptions: ex },
            { extend: 'csvHtml5',   text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn-sm btn-outline-primary', title: titulo, bom: true, exportOptions: ex },
            { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn-sm btn-outline-primary', title: titulo, exportOptions: ex },
            { extend: 'colvis',     text: '<i class="fas fa-columns"></i> Columnas', className: 'btn-sm btn-outline-secondary' }
        ];
    };
    var lengths = [[25, 50, 100, -1], [25, 50, 100, 'Todos']];

    var tm = $('#tblMat').DataTable({
        pageLength: 25, lengthMenu: lengths, language: idioma, dom: domCfg,
        buttons: btns('comparacion_rutinas'), order: [[0, 'asc'], [1, 'asc']]
    });
    $('#tblDet').DataTable({
        pageLength: 25, lengthMenu: lengths, language: idioma, dom: domCfg,
        columnDefs: [{ targets: [10, 11], visible: false }],
        buttons: btns('detalle_rutinas', [0, 1, 2, 3, 10, 11, 6, 7, 8, 9]),
        order: [[3, 'asc'], [0, 'asc']]
    });

    function filtrar(sel, col) {
        $(sel).on('change', function () {
            var v = $(this).val();
            tm.column(col).search(v ? '^' + $.fn.dataTable.util.escapeRegex(v) + '$' : '', true, false).draw();
        });
    }
    filtrar('#mTipo', 0); filtrar('#mEstado', 2); filtrar('#mPal', 3);

    function generar() {
        var sel = $('#lSel').val(), conTipo = $('#lTipo').is(':checked'), out = [], vistos = {};
        for (var i = 0; i < FILAS.length; i++) {
            var f = FILAS[i], ok = false;
            if (sel === 'pal') ok = f.pal;
            else if (sel === 'dist') ok = (f.cod === 'dist' || f.cod === 'attr');
            else if (sel === 'falta') ok = (f.cod === 'ident_falta' || f.cod === 'solo1');
            else if (sel === 'sinacc') ok = (f.cod === 'sinacc');
            else if (sel === 'ident') ok = (f.cod === 'ident');
            if (!ok) continue;
            var n = conTipo ? f.tipo + ' ' + f.name : f.name;
            if (vistos[n]) continue;
            vistos[n] = true;
            out.push(n);
        }
        out.sort();
        $('#txtNombres').val(out.join('\n'));
        $('#txtCount').text(out.length + ' nombre(s)');
    }
    $('#lSel, #lTipo').on('change', generar);
    $('#btnCopiar').on('click', function () {
        var ta = document.getElementById('txtNombres');
        ta.select();
        if (navigator.clipboard) { navigator.clipboard.writeText(ta.value); } else { document.execCommand('copy'); }
    });
    generar();
});
</script>
<?php endif; ?>
</body>
</html>
<?php
/*
 * ==============================================================================
 * ADVERTENCIAS (resumen, también visibles al final de la página)
 * ==============================================================================
 * 1. PERMISOS: ROUTINE_DEFINITION / ACTION_STATEMENT son NULL sin EXECUTE o
 *    TRIGGER. Se marcan "sin acceso" y no se comparan (un NULL hasheado daría
 *    falsos "iguales").
 * 2. IGUALES != EQUIVALENTES: hash distinto puede ser un comentario o la
 *    indentación; hash igual no garantiza el mismo comportamiento.
 * 3. MySQL 8.4 vs MariaDB 11.8: la definición, el tipo de los parámetros y el
 *    sql_mode pueden diferir aunque la rutina sea la misma. Por eso existen el
 *    hash del cuerpo y el hash completo. El DEFINER no entra en el hash.
 * 4. LA BÚSQUEDA DETECTA TEXTO, NO USO REAL: no ve SQL dinámico armado por partes
 *    ni referencias indirectas; cuenta coincidencias dentro de cadenas.
 * 5. LA CLAVE ES EL NOMBRE (tipo + nombre), sin importar el esquema.
 * 6. EVENTS, vistas y tablas no se leen.
 * 7. Uso local o en red de confianza; no dejarlo publicado y borrarlo al terminar.
 * ==============================================================================
 */
