<?php
/**
 * ==============================================================================
 * INVENTARIO DE SERVIDORES MySQL / MariaDB
 * ==============================================================================
 * @project     Comun
 * @file        inventario_mysql.php
 * @version     1.0.0
 * @author      Alfonso Orozco Aguilar
 * @created     23 / Nov / 2026
 * @updated     23 / Nov / 2026
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
 * Reconocimiento inicial de varios servidores MySQL/MariaDB heredados cuando:
 *   - No se conoce qué tablas y vistas existen en cada servidor.
 *   - No se sabe el motor (InnoDB / MyISAM), el volumen de registros ni la
 *     collation de cada objeto.
 *   - No se dispone de un usuario administrador para comparar esquemas con
 *     herramientas específicas.
 *   - Se necesita conocer versión, tipo (MySQL vs MariaDB), modo estricto
 *     (sql_mode), charset/collation por defecto y lower_case_table_names, que son
 *     causas habituales de fallos en migraciones y comparaciones.
 *
 * Funcionamiento:
 *   1. Se ejecuta en local (php -S 127.0.0.1:8000) y se editan los hosts en
 *      $SERVERS.
 *   2. Se piden DOS claves: la del script (validada contra un hash) y la de
 *      MySQL (se pega en el formulario). La de MySQL se usa solo en esa petición:
 *      no se guarda en sesión, archivo ni log.
 *   3. Solo se consulta information_schema y variables del servidor (lectura), por
 *      lo que basta con un usuario sin privilegios especiales. Solo ve los objetos
 *      sobre los que ese usuario tiene algún privilegio.
 *   4. Muestra una ficha por servidor, el inventario en DataTables (exportable),
 *      una matriz de coincidencias de nombres entre servidores y un cuadro de
 *      texto para copiar únicamente los nombres detectados.
 *
 * Seguridad: usar solo en local o red de confianza, no dejarlo publicado y
 * borrarlo al terminar. El hash no sustituye una protección real de acceso.
 *
 * Generar el hash de la clave del script:
 *   php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
 * ==============================================================================
 */

// ============================================
// CONFIGURACIÓN
// ============================================
define('APP_VERSION', '1.0.0');
set_time_limit(0); // sin limite de tiempo, normal defaul es 60 o 300 = 1 o 5 minutos

// Hash de la clave del script (NO escribir la clave en texto plano).
define('PASS_HASH', 'REEMPLAZAR_CON_HASH');

// Servidores a inspeccionar. Se edita aquí.
$SERVERS = array(
    array('nombre' => 'SRV-150', 'host' => '10.10.1.150', 'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
    array('nombre' => 'SRV-163', 'host' => '10.10.1.163', 'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
    array('nombre' => 'SRV-51',  'host' => '10.10.1.51',  'port' => 3306, 'user' => 'CAMBIAR_USUARIO', 'ssl' => false),
);

// Esquemas del sistema que se omiten del inventario
$ESQUEMAS_SISTEMA = array('information_schema', 'mysql', 'performance_schema', 'sys');

// Variables del servidor a leer (solo lectura)
$VARIABLES = array(
    'version', 'version_comment', 'sql_mode', 'character_set_server', 'collation_server',
    'default_storage_engine', 'lower_case_table_names', 'time_zone', 'innodb_strict_mode',
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

function leerVariable($c, $name, $scope) {
    // $name proviene de la lista interna $VARIABLES, nunca del usuario
    $r = @$c->query('SELECT @@' . $scope . $name);
    if (!$r) return null;
    $row = $r->fetch_row();
    $r->free();
    return $row ? $row[0] : null;
}

function inspeccionar($c, $esquemasSistema, $variables, $conteoExacto) {
    $out = array('vars' => array(), 'global_sql_mode' => null, 'schemas' => array(), 'objetos' => array());

    foreach ($variables as $v) {
        $out['vars'][$v] = leerVariable($c, $v, '');
    }
    $out['global_sql_mode'] = leerVariable($c, 'sql_mode', 'GLOBAL.');

    $ver = (string)$out['vars']['version'] . ' ' . (string)$out['vars']['version_comment'];
    $out['es_maria'] = stripos($ver, 'mariadb') !== false;

    $modo = strtoupper((string)$out['global_sql_mode'] . ',' . (string)$out['vars']['sql_mode']);
    $out['estricto'] = (strpos($modo, 'STRICT_TRANS_TABLES') !== false || strpos($modo, 'STRICT_ALL_TABLES') !== false);

    $lista = "'" . implode("','", array_map(array($c, 'real_escape_string'), $esquemasSistema)) . "'";

    $r = @$c->query("SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                     FROM information_schema.SCHEMATA WHERE SCHEMA_NAME NOT IN ($lista) ORDER BY SCHEMA_NAME");
    if ($r) {
        while ($row = $r->fetch_assoc()) $out['schemas'][] = $row;
        $r->free();
    }

    $r = @$c->query("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS,
                            DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION, UPDATE_TIME
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA NOT IN ($lista)
                     ORDER BY TABLE_SCHEMA, TABLE_NAME");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $row['EXACTAS'] = null;
            if ($conteoExacto && $row['TABLE_TYPE'] === 'BASE TABLE') {
                $q = '`' . str_replace('`', '``', $row['TABLE_SCHEMA']) . '`.`' . str_replace('`', '``', $row['TABLE_NAME']) . '`';
                $rc = @$c->query('SELECT COUNT(*) FROM ' . $q);
                if ($rc) {
                    $x = $rc->fetch_row();
                    $row['EXACTAS'] = $x[0];
                    $rc->free();
                }
            }
            $out['objetos'][] = $row;
        }
        $r->free();
    }
    return $out;
}

function badgeMotor($motor, $tipo) {
    if ($tipo !== 'BASE TABLE') return '<span class="badge badge-secondary">—</span>';
    $m = strtoupper((string)$motor);
    if ($m === 'INNODB') return '<span class="badge badge-yes">InnoDB</span>';
    if ($m === 'MYISAM') return '<span class="badge badge-warn">MyISAM</span>';
    return '<span class="badge badge-secondary">' . h($motor) . '</span>';
}

function badgeTipo($tipo) {
    if ($tipo === 'BASE TABLE') return '<span class="badge badge-table"><i class="fas fa-table mr-1"></i>Tabla</span>';
    if ($tipo === 'VIEW') return '<span class="badge badge-view"><i class="fas fa-eye mr-1"></i>Vista</span>';
    return '<span class="badge badge-secondary">' . h($tipo) . '</span>';
}

// ============================================
// PROCESAMIENTO
// ============================================
$error_login = '';
$resultados = null;
$tiempo_ms = 0;
$conteoExacto = false;
$hashPendiente = (PASS_HASH === 'REEMPLAZAR_CON_HASH');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passScript = isset($_POST['pass_script']) ? (string)$_POST['pass_script'] : '';
    $passDb     = isset($_POST['pass_db']) ? (string)$_POST['pass_db'] : '';
    $conteoExacto = !empty($_POST['exacto']);

    if ($hashPendiente) {
        $error_login = 'Primero define PASS_HASH en el script (ver instrucciones abajo).';
    } elseif (!password_verify($passScript, PASS_HASH)) {
        sleep(1);
        $error_login = 'Clave del script incorrecta';
    } elseif ($passDb === '') {
        $error_login = 'Falta la contraseña de MySQL';
    } else {
        if ($conteoExacto) @set_time_limit(0);
        $inicio = microtime(true);
        $resultados = array();
        foreach ($SERVERS as $i => $s) {
            list($c, $err) = conectar($s, $passDb);
            $item = array('srv' => $s, 'error' => $err, 'info' => null);
            if ($c) {
                $item['info'] = inspeccionar($c, $ESQUEMAS_SISTEMA, $VARIABLES, $conteoExacto);
                $c->close();
            }
            $resultados[] = $item;
        }
        $passDb = null; // no se conserva
        $tiempo_ms = round((microtime(true) - $inicio) * 1000, 2);
    }
}

// Datos derivados para la vista
$inventario = array();   // para JSON (textbox)
$matriz = array();       // nombre => [idxServidor => [esquemas]]
if ($resultados !== null) {
    foreach ($resultados as $i => $item) {
        if (!$item['info']) continue;
        foreach ($item['info']['objetos'] as $o) {
            $inventario[] = array(
                'srv'    => $item['srv']['nombre'],
                'schema' => $o['TABLE_SCHEMA'],
                'name'   => $o['TABLE_NAME'],
                'type'   => ($o['TABLE_TYPE'] === 'VIEW') ? 'VIEW' : 'TABLE',
            );
            $matriz[$o['TABLE_NAME']][$i][] = $o['TABLE_SCHEMA'];
        }
    }
    ksort($matriz);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventario MySQL / MariaDB</title>
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
    .badge-srv   { background: #1e3c72; color: #fff; }
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
    .stat-box .value.small-mono { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.74rem; font-weight: 500; }
    .btn-primary-custom { background: #2a5298; border: none; padding: 10px 20px; font-weight: 600; }
    .btn-primary-custom:hover { background: #1e3c72; }
    .login-icon { font-size: 2.6rem; color: #2a5298; }
    .srv-head { background: #1e3c72; color: #fff; border-radius: 15px 15px 0 0; padding: 12px 16px; }
    .srv-head.err { background: #dc3545; }
    .mini-stat { text-align: center; padding: 10px 4px; border-radius: 10px; color: #fff; margin-bottom: 8px; }
    .mini-stat .num { font-size: 1.4rem; font-weight: 700; line-height: 1; }
    .mini-stat .lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px; opacity: .9; }
    .bg-m-tab  { background: linear-gradient(135deg, #2a5298, #1e3c72); }
    .bg-m-view { background: linear-gradient(135deg, #17a2b8, #11707f); }
    .bg-m-inno { background: linear-gradient(135deg, #28a745, #1e7e34); }
    .bg-m-myi  { background: linear-gradient(135deg, #ffc107, #d39e00); color: #212529 !important; }
    textarea.nombres { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; }
    .dataTables_wrapper .dt-buttons { margin-bottom: 8px; }
    td.cell-ok { text-align: center; }
</style>
</head>
<body>
<div class="container-fluid" style="max-width: 1400px;">
<div class="card card-custom">
<div class="card-body p-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">
            <i class="fas fa-database text-primary mr-2"></i>Inventario MySQL / MariaDB
            <small class="text-muted" style="font-size:.55em;">tablas · vistas · motor · modo SQL · collation</small>
        </h3>
        <?php if ($resultados !== null): ?>
            <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt mr-1"></i>Limpiar
            </a>
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
            <div class="col-md-4 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-shield-alt"></i></span></div>
                    <input type="password" name="pass_script" class="form-control" placeholder="Clave del script" required autofocus autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                    <input type="password" name="pass_db" class="form-control" placeholder="Contraseña de MySQL" required autocomplete="new-password">
                </div>
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-center">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="exacto" name="exacto" value="1"<?= $conteoExacto ? ' checked' : '' ?>>
                    <label class="custom-control-label" for="exacto" title="Ejecuta COUNT(*) en cada tabla; puede ser lento">Conteo exacto</label>
                </div>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-primary btn-block btn-primary-custom"><i class="fas fa-rocket mr-1"></i>Inspeccionar</button>
            </div>
        </div>
        <small class="text-muted">
            <i class="fas fa-server mr-1"></i>Servidores:
            <?php foreach ($SERVERS as $s): ?>
                <code class="path"><?= h($s['host']) ?>:<?= (int)$s['port'] ?></code>
            <?php endforeach; ?>
            · La contraseña de MySQL no se guarda en sesión ni en disco.
            · <i class="fas fa-info-circle"></i> Filas "est." = estimación de information_schema (InnoDB no es exacto).
        </small>
    </form>

<?php if ($resultados !== null): ?>

    <!-- FICHAS POR SERVIDOR -->
    <h5 class="section-title"><i class="fas fa-server text-primary mr-2"></i>1. Servidores</h5>
    <div class="row">
    <?php foreach ($resultados as $item):
        $s = $item['srv'];
        $info = $item['info'];
        $nTab = $nVis = $nInno = $nMyi = 0;
        if ($info) {
            foreach ($info['objetos'] as $o) {
                if ($o['TABLE_TYPE'] === 'VIEW') { $nVis++; continue; }
                if ($o['TABLE_TYPE'] === 'BASE TABLE') $nTab++;
                $m = strtoupper((string)$o['ENGINE']);
                if ($m === 'INNODB') $nInno++;
                if ($m === 'MYISAM') $nMyi++;
            }
        }
    ?>
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card card-custom h-100">
                <div class="srv-head<?= $info ? '' : ' err' ?>">
                    <strong><i class="fas fa-server mr-2"></i><?= h($s['nombre']) ?></strong>
                    <span class="float-right small"><?= h($s['host']) ?>:<?= (int)$s['port'] ?></span>
                </div>
                <div class="card-body">
                <?php if (!$info): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-plug mr-2"></i><strong>No se pudo conectar.</strong><br>
                        <small><?= h($item['error']) ?></small>
                        <br><small class="text-muted">Revisa red/VPN, que el usuario <code><?= h($s['user']) ?></code> pueda entrar desde tu IP, y la contraseña.</small>
                    </div>
                <?php else: $v = $info['vars']; ?>
                    <div class="row">
                        <div class="col-3"><div class="mini-stat bg-m-tab"><div class="num"><?= $nTab ?></div><div class="lbl">Tablas</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-view"><div class="num"><?= $nVis ?></div><div class="lbl">Vistas</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-inno"><div class="num"><?= $nInno ?></div><div class="lbl">InnoDB</div></div></div>
                        <div class="col-3"><div class="mini-stat bg-m-myi"><div class="num"><?= $nMyi ?></div><div class="lbl">MyISAM</div></div></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Versión</div>
                        <div class="value">
                            <?= h($v['version']) ?>
                            <span class="badge <?= $info['es_maria'] ? 'badge-warn' : 'badge-table' ?> ml-1"><?= $info['es_maria'] ? 'MariaDB' : 'MySQL' ?></span>
                        </div>
                        <div class="small text-muted"><?= h($v['version_comment']) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Modo estricto</div>
                        <div class="value">
                            <span class="badge <?= $info['estricto'] ? 'badge-yes' : 'badge-no' ?>"><?= $info['estricto'] ? 'SÍ' : 'NO' ?></span>
                        </div>
                        <div class="value small-mono mt-1"><strong>Global:</strong> <?= h($info['global_sql_mode'] === '' ? '(vacío)' : $info['global_sql_mode']) ?></div>
                        <div class="value small-mono"><strong>Sesión:</strong> <?= h($v['sql_mode'] === '' ? '(vacío)' : $v['sql_mode']) ?></div>
                    </div>
                    <div class="row">
                        <div class="col-6"><div class="stat-box"><div class="label">Charset servidor</div><div class="value"><?= h($v['character_set_server']) ?></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="label">Collation servidor</div><div class="value"><?= h($v['collation_server']) ?></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="label">Motor por defecto</div><div class="value"><?= h($v['default_storage_engine']) ?></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="label">lower_case_table_names</div><div class="value"><?= h($v['lower_case_table_names']) ?></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="label">Zona horaria</div><div class="value"><?= h($v['time_zone']) ?></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="label">innodb_strict_mode</div><div class="value"><?= $v['innodb_strict_mode'] === null ? '—' : ($v['innodb_strict_mode'] ? 'ON' : 'OFF') ?></div></div></div>
                    </div>
                    <?php if (count($info['schemas']) > 0): ?>
                        <details>
                            <summary class="text-primary font-weight-bold" style="cursor:pointer;">Bases de datos (<?= count($info['schemas']) ?>)</summary>
                            <div class="mt-2 small">
                            <?php foreach ($info['schemas'] as $sc): ?>
                                <div><code class="path"><?= h($sc['SCHEMA_NAME']) ?></code>
                                    <span class="text-muted"><?= h($sc['DEFAULT_CHARACTER_SET_NAME']) ?> / <?= h($sc['DEFAULT_COLLATION_NAME']) ?></span></div>
                            <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- INVENTARIO -->
    <h5 class="section-title"><i class="fas fa-table text-primary mr-2"></i>2. Inventario de tablas y vistas</h5>
    <div class="form-row mb-2">
        <div class="col-md-3 mb-2">
            <select id="fSrv" class="custom-select custom-select-sm">
                <option value="">Todos los servidores</option>
                <?php foreach ($SERVERS as $s): ?><option value="<?= h($s['nombre']) ?>"><?= h($s['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <select id="fTipo" class="custom-select custom-select-sm">
                <option value="">Tablas y vistas</option>
                <option value="Tabla">Solo tablas</option>
                <option value="Vista">Solo vistas</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <select id="fMotor" class="custom-select custom-select-sm">
                <option value="">Todos los motores</option>
                <option value="InnoDB">InnoDB</option>
                <option value="MyISAM">MyISAM</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table id="tblInv" class="table table-sm table-bordered table-custom" style="width:100%">
            <thead>
                <tr>
                    <th>Servidor</th><th>Esquema</th><th>Objeto</th><th>Tipo</th><th>Motor</th>
                    <th class="text-right">Filas (est.)</th><th class="text-right">Filas exactas</th>
                    <th class="text-right">Tamaño (MB)</th><th>Collation</th><th>Actualizado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resultados as $item):
                if (!$item['info']) continue;
                foreach ($item['info']['objetos'] as $o):
                    $esVista = ($o['TABLE_TYPE'] === 'VIEW');
                    $mb = $esVista ? null : round(((float)$o['DATA_LENGTH'] + (float)$o['INDEX_LENGTH']) / 1048576, 2);
            ?>
                <tr>
                    <td><span class="badge badge-srv"><?= h($item['srv']['nombre']) ?></span></td>
                    <td><code class="path"><?= h($o['TABLE_SCHEMA']) ?></code></td>
                    <td><?= h($o['TABLE_NAME']) ?></td>
                    <td><?= badgeTipo($o['TABLE_TYPE']) ?></td>
                    <td><?= badgeMotor($o['ENGINE'], $o['TABLE_TYPE']) ?></td>
                    <td class="text-right" data-order="<?= $esVista || $o['TABLE_ROWS'] === null ? -1 : (int)$o['TABLE_ROWS'] ?>"><?= $esVista || $o['TABLE_ROWS'] === null ? '—' : number_format((int)$o['TABLE_ROWS']) ?></td>
                    <td class="text-right" data-order="<?= $o['EXACTAS'] === null ? -1 : (int)$o['EXACTAS'] ?>"><?= $o['EXACTAS'] === null ? '—' : number_format((int)$o['EXACTAS']) ?></td>
                    <td class="text-right" data-order="<?= $mb === null ? -1 : $mb ?>"><?= $mb === null ? '—' : $mb ?></td>
                    <td><?= h($o['TABLE_COLLATION'] === null ? '—' : $o['TABLE_COLLATION']) ?></td>
                    <td><?= h($o['UPDATE_TIME'] === null ? '—' : $o['UPDATE_TIME']) ?></td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MATRIZ -->
    <h5 class="section-title"><i class="fas fa-columns text-primary mr-2"></i>3. Coincidencias de nombre entre servidores</h5>
    <p class="text-muted small mb-2">Compara por nombre de objeto (sin importar el esquema). Cada celda muestra en qué esquema(s) aparece.</p>
    <div class="table-responsive">
        <table id="tblMat" class="table table-sm table-bordered table-custom" style="width:100%">
            <thead>
                <tr>
                    <th>Objeto</th>
                    <?php foreach ($resultados as $item): ?><th class="text-center"><?= h($item['srv']['nombre']) ?></th><?php endforeach; ?>
                    <th class="text-center">En # servidores</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matriz as $nombre => $porSrv): ?>
                <tr>
                    <td><?= h($nombre) ?></td>
                    <?php foreach ($resultados as $i => $item): ?>
                        <?php if (isset($porSrv[$i])): ?>
                            <td class="cell-ok"><span class="badge badge-yes">SÍ</span> <small class="text-muted"><?= h(implode(', ', array_unique($porSrv[$i]))) ?></small></td>
                        <?php else: ?>
                            <td class="cell-ok"><span class="badge badge-no">NO</span></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="text-center"><?= count($porSrv) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TEXTBOX -->
    <h5 class="section-title"><i class="fas fa-clipboard-list text-primary mr-2"></i>4. Nombres para copiar</h5>
    <div class="form-row mb-2">
        <div class="col-md-3 mb-2">
            <select id="tSrv" class="custom-select custom-select-sm">
                <option value="">Todos los servidores</option>
                <?php foreach ($SERVERS as $s): ?><option value="<?= h($s['nombre']) ?>"><?= h($s['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-9 mb-2 d-flex align-items-center flex-wrap">
            <div class="custom-control custom-checkbox mr-3"><input type="checkbox" class="custom-control-input" id="tTablas" checked><label class="custom-control-label" for="tTablas">Tablas</label></div>
            <div class="custom-control custom-checkbox mr-3"><input type="checkbox" class="custom-control-input" id="tVistas"><label class="custom-control-label" for="tVistas">Vistas</label></div>
            <div class="custom-control custom-checkbox mr-3"><input type="checkbox" class="custom-control-input" id="tEsq"><label class="custom-control-label" for="tEsq">Incluir esquema (base.tabla)</label></div>
            <div class="custom-control custom-checkbox mr-3"><input type="checkbox" class="custom-control-input" id="tUniq" checked><label class="custom-control-label" for="tUniq">Sin duplicados</label></div>
            <button type="button" id="btnCopiar" class="btn btn-sm btn-outline-primary"><i class="fas fa-copy mr-1"></i>Copiar</button>
        </div>
    </div>
    <textarea id="txtNombres" class="form-control nombres" rows="10" readonly></textarea>
    <small id="txtCount" class="text-muted"></small>

    <!-- INFO -->
    <h5 class="section-title"><i class="fas fa-clock text-warning mr-2"></i>5. Información de la consulta</h5>
    <div class="row">
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-stopwatch mr-1"></i>Tiempo total</div><div class="value"><?= $tiempo_ms ?> ms</div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-code-branch mr-1"></i>Versión del script</div><div class="value">v<?= APP_VERSION ?></div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="label"><i class="fas fa-folder-open mr-1"></i>Ruta del script</div><div class="value" style="font-size:0.85rem;"><?= h(__FILE__) ?></div></div></div>
    </div>

<?php endif; ?>

</div>
</div>
<p class="text-center text-white-50 small mt-3">
    <i class="fas fa-database mr-1"></i>Inventario MySQL v<?= APP_VERSION ?> — PHP <?= PHP_VERSION ?> — Licencia MIT
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
var INV = <?= json_encode($inventario, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

$(function () {
    var idioma = {
        search: 'Buscar:', lengthMenu: 'Mostrar _MENU_', info: '_START_ a _END_ de _TOTAL_',
        infoEmpty: 'Sin registros', infoFiltered: '(filtrado de _MAX_)', zeroRecords: 'Sin coincidencias',
        paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    };
    var domCfg = "<'row'<'col-md-7'B><'col-md-5'f>>rt<'row'<'col-md-4'i><'col-md-4'l><'col-md-4'p>>";
    var btns = function (titulo) {
        return [
            { extend: 'copyHtml5',  text: '<i class="fas fa-copy"></i> Copiar', className: 'btn-sm btn-outline-primary', title: null },
            { extend: 'csvHtml5',   text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn-sm btn-outline-primary', title: titulo, bom: true },
            { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn-sm btn-outline-primary', title: titulo },
            { extend: 'colvis',     text: '<i class="fas fa-columns"></i> Columnas', className: 'btn-sm btn-outline-secondary' }
        ];
    };

    var t = $('#tblInv').DataTable({
        pageLength: 25, lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
        language: idioma, dom: domCfg, buttons: btns('inventario_mysql'), order: [[0, 'asc'], [1, 'asc'], [2, 'asc']]
    });
    $('#tblMat').DataTable({
        pageLength: 25, lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
        language: idioma, dom: domCfg, buttons: btns('coincidencias_mysql'), order: [[0, 'asc']]
    });

    function filtrar(sel, col) {
        $(sel).on('change', function () {
            var v = $(this).val();
            t.column(col).search(v ? '^' + v + '$' : '', true, false).draw();
        });
    }
    filtrar('#fSrv', 0); filtrar('#fTipo', 3); filtrar('#fMotor', 4);

    // ---- Textbox de nombres ----
    function generar() {
        var srv = $('#tSrv').val(), tabs = $('#tTablas').is(':checked'), vis = $('#tVistas').is(':checked');
        var esq = $('#tEsq').is(':checked'), uniq = $('#tUniq').is(':checked');
        var out = [], vistos = {};
        for (var i = 0; i < INV.length; i++) {
            var o = INV[i];
            if (srv && o.srv !== srv) continue;
            if (o.type === 'TABLE' && !tabs) continue;
            if (o.type === 'VIEW' && !vis) continue;
            var n = esq ? o.schema + '.' + o.name : o.name;
            if (uniq) { if (vistos[n]) continue; vistos[n] = true; }
            out.push(n);
        }
        out.sort();
        $('#txtNombres').val(out.join('\n'));
        $('#txtCount').text(out.length + ' nombre(s)');
    }
    $('#tSrv, #tTablas, #tVistas, #tEsq, #tUniq').on('change', generar);
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
