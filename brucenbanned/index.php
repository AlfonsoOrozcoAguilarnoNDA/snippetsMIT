<?php
/**
 * SISTEMA: SNIPPET ideentificador / Bloqueador de Ips en base a archivo raw apache
 * https://vibecodingmexico.com/snippets-lector-de-raw-logs/
 * FECHA: 29 de abril de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Gemini 3 Flash (v.2026-04) & Alfonso Orozco Aguilar
 * DESCRIPCIÓN: Refactorización quirúrgica de cadenas en scripts locales con validación SHA.
 */
include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso no autorizado.");
}

// Acciones: Baneo
if (isset($_GET['ban_ip'])) {
    $ip = $link->real_escape_string($_GET['ban_ip']);
    $link->query("INSERT IGNORE INTO master_bans (ip, fecha_ban) VALUES ('$ip', NOW())");
    $link->query("UPDATE log_indexado SET ipbanned = 'YES' WHERE ip_remota = '$ip'");
    header("Location: index.php?msg=IP_Banneada");
    exit;
}

// Acción: Resolver Host Individual
if (isset($_GET['check_host'])) {
    $ip = $link->real_escape_string($_GET['check_host']);
    $host = gethostbyaddr($ip);
    $link->query("UPDATE log_indexado SET hostname = '$host' WHERE ip_remota = '$ip'");
    header("Location: index.php?ip_filter=$ip");
    exit;
}

// NUEVA ACCIÓN: Resolver todos los Hostnames vacíos (LIMIT 50 para no saturar)
if (isset($_GET['fix_hosts'])) {
    $res = $link->query("SELECT DISTINCT ip_remota FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO' LIMIT 50");
    while($r = $res->fetch_assoc()){
        $h = gethostbyaddr($r['ip_remota']);
        $link->query("UPDATE log_indexado SET hostname = '$h' WHERE ip_remota = '{$r['ip_remota']}'");
    }
    header("Location: index.php?msg=Hosts_Actualizados");
    exit;
}

// 4. CONSULTAS PARA FILTROS
$f_dominio = $_GET['dom_filter'] ?? '';
$f_ua = $_GET['ua_filter'] ?? '';
$f_ip = $_GET['ip_filter'] ?? '';

$where = "WHERE ipbanned = 'NO'";
if ($f_dominio) $where .= " AND dominio = '$f_dominio'";
if ($f_ua) $where .= " AND user_agent LIKE '%$f_ua%'";
if ($f_ip) $where .= " AND ip_remota = '$f_ip'";

$logs = $link->query("SELECT * FROM log_indexado $where ORDER BY fecha_acceso DESC, id DESC LIMIT 500");
$top10 = $link->query("SELECT ip_remota, COUNT(*) as hits FROM log_indexado WHERE ipbanned = 'NO' GROUP BY ip_remota ORDER BY hits DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VIBECODINGMEXICO.COM | Bot Block Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 60px; background: #f4f4f4; }
        /* Forzar el scroll horizontal */
        .table-responsive { 
            max-height: 75vh; 
            overflow-y: auto; 
            overflow-x: auto; /* Scroll horizontal habilitado */
            background: white; 
            white-space: nowrap; /* Evita que el texto salte de línea y fuerza el ancho */
        }
        .sticky-footer { position: fixed; bottom: 0; width: 100%; height: 40px; background: #343a40; color: white; line-height: 40px; font-size: 0.8rem; z-index: 1030; }
        .btn-xs { padding: 1px 5px; font-size: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="index.php">Gemini 3 Flash | VIBECODINGMEXICO.COM</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="menu" data-toggle="dropdown">Opciones</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="index.php">Dashboard</a>
                    <a class="dropdown-item" href="import.php">Importar Logs</a>
                    <a class="dropdown-item text-primary" href="?fix_hosts=1">🔍 Resolver Hostnames Vacíos</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item disabled">PHP: <?php echo phpversion(); ?></a>
                    <a class="dropdown-item disabled">DB: <?php echo $link->server_info; ?></a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card p-2 shadow-sm">
                <h6 class="border-bottom pb-1">Top 10 IPs (Activas)</h6>
                <div class="d-flex flex-wrap">
                    <?php while($t = $top10->fetch_assoc()): ?>
                        <span class="badge badge-danger m-1 p-2" style="cursor:pointer;" onclick="window.location='?ip_filter=<?php echo $t['ip_remota']; ?>'">
                            <?php echo $t['ip_remota']; ?> (<?php echo $t['hits']; ?>)
                        </span>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-2 shadow-sm mb-3">
        <form class="form-inline" method="GET">
            <input type="text" name="ip_filter" class="form-control form-control-sm mr-2" placeholder="IP" value="<?php echo $f_ip; ?>">
            <input type="text" name="ua_filter" class="form-control form-control-sm mr-2" placeholder="User Agent" value="<?php echo $f_ua; ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
        </form>
    </div>

    <div class="table-responsive shadow-sm border">
        <table class="table table-hover table-sm table-striped mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Fecha / Hora</th>
                    <th>IP / Hostname</th>
                    <th>Acción</th>
                    <th>Dominio</th>
                    <th>Status</th>
                    <th>Petición</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $logs->fetch_assoc()): ?>
                <tr>
                    <td><small><?php echo $row['fecha_acceso']; ?></small></td>
                    <td>
                        <strong><?php echo $row['ip_remota']; ?></strong><br>
                        <small class="text-info"><?php echo $row['hostname'] ?: '---'; ?></small>
                    </td>
                    <td>
                        <a href="?check_host=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-info">Check Host</a>
                        <a href="?ban_ip=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-danger">BAN</a>
                    </td>
                    <td><span class="badge badge-light border"><?php echo $row['dominio']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['http_status'] == 200) ? 'success' : 'warning'; ?>"><?php echo $row['http_status']; ?></span></td>
                    <td><small><strong><?php echo $row['metodo']; ?></strong> <?php echo $row['recurso']; ?></small></td>
                    <td><small class="text-muted"><?php echo $row['user_agent']; ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="sticky-footer text-center">
    <span>© 2026 Alfonso Orozco Aguilar | "Vibecoding" Engineering | Stack: PHP Procedural + MariaDB</span>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html><?php
include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso no autorizado.");
}

// Acciones: Baneo
if (isset($_GET['ban_ip'])) {
    $ip = $link->real_escape_string($_GET['ban_ip']);
    $link->query("INSERT IGNORE INTO master_bans (ip, fecha_ban) VALUES ('$ip', NOW())");
    $link->query("UPDATE log_indexado SET ipbanned = 'YES' WHERE ip_remota = '$ip'");
    header("Location: index.php?msg=IP_Banneada");
    exit;
}

// Acción: Resolver Host Individual
if (isset($_GET['check_host'])) {
    $ip = $link->real_escape_string($_GET['check_host']);
    $host = gethostbyaddr($ip);
    $link->query("UPDATE log_indexado SET hostname = '$host' WHERE ip_remota = '$ip'");
    header("Location: index.php?ip_filter=$ip");
    exit;
}

// NUEVA ACCIÓN: Resolver todos los Hostnames vacíos (LIMIT 50 para no saturar)
if (isset($_GET['fix_hosts'])) {
    $res = $link->query("SELECT DISTINCT ip_remota FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO' LIMIT 50");
    while($r = $res->fetch_assoc()){
        $h = gethostbyaddr($r['ip_remota']);
        $link->query("UPDATE log_indexado SET hostname = '$h' WHERE ip_remota = '{$r['ip_remota']}'");
    }
    header("Location: index.php?msg=Hosts_Actualizados");
    exit;
}

// 4. CONSULTAS PARA FILTROS
$f_dominio = $_GET['dom_filter'] ?? '';
$f_ua = $_GET['ua_filter'] ?? '';
$f_ip = $_GET['ip_filter'] ?? '';

$where = "WHERE ipbanned = 'NO'";
if ($f_dominio) $where .= " AND dominio = '$f_dominio'";
if ($f_ua) $where .= " AND user_agent LIKE '%$f_ua%'";
if ($f_ip) $where .= " AND ip_remota = '$f_ip'";

$logs = $link->query("SELECT * FROM log_indexado $where ORDER BY fecha_acceso DESC, id DESC LIMIT 500");
$top10 = $link->query("SELECT ip_remota, COUNT(*) as hits FROM log_indexado WHERE ipbanned = 'NO' GROUP BY ip_remota ORDER BY hits DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VIBECODINGMEXICO.COM | Bot Block Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 60px; background: #f4f4f4; }
        /* Forzar el scroll horizontal */
        .table-responsive { 
            max-height: 75vh; 
            overflow-y: auto; 
            overflow-x: auto; /* Scroll horizontal habilitado */
            background: white; 
            white-space: nowrap; /* Evita que el texto salte de línea y fuerza el ancho */
        }
        .sticky-footer { position: fixed; bottom: 0; width: 100%; height: 40px; background: #343a40; color: white; line-height: 40px; font-size: 0.8rem; z-index: 1030; }
        .btn-xs { padding: 1px 5px; font-size: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="index.php">Gemini 3 Flash | VIBECODINGMEXICO.COM</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="menu" data-toggle="dropdown">Opciones</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="index.php">Dashboard</a>
                    <a class="dropdown-item" href="import.php">Importar Logs</a>
                    <a class="dropdown-item text-primary" href="?fix_hosts=1">🔍 Resolver Hostnames Vacíos</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item disabled">PHP: <?php echo phpversion(); ?></a>
                    <a class="dropdown-item disabled">DB: <?php echo $link->server_info; ?></a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card p-2 shadow-sm">
                <h6 class="border-bottom pb-1">Top 10 IPs (Activas)</h6>
                <div class="d-flex flex-wrap">
                    <?php while($t = $top10->fetch_assoc()): ?>
                        <span class="badge badge-danger m-1 p-2" style="cursor:pointer;" onclick="window.location='?ip_filter=<?php echo $t['ip_remota']; ?>'">
                            <?php echo $t['ip_remota']; ?> (<?php echo $t['hits']; ?>)
                        </span>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-2 shadow-sm mb-3">
        <form class="form-inline" method="GET">
            <input type="text" name="ip_filter" class="form-control form-control-sm mr-2" placeholder="IP" value="<?php echo $f_ip; ?>">
            <input type="text" name="ua_filter" class="form-control form-control-sm mr-2" placeholder="User Agent" value="<?php echo $f_ua; ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
        </form>
    </div>

    <div class="table-responsive shadow-sm border">
        <table class="table table-hover table-sm table-striped mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Fecha / Hora</th>
                    <th>IP / Hostname</th>
                    <th>Acción</th>
                    <th>Dominio</th>
                    <th>Status</th>
                    <th>Petición</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $logs->fetch_assoc()): ?>
                <tr>
                    <td><small><?php echo $row['fecha_acceso']; ?></small></td>
                    <td>
                        <strong><?php echo $row['ip_remota']; ?></strong><br>
                        <small class="text-info"><?php echo $row['hostname'] ?: '---'; ?></small>
                    </td>
                    <td>
                        <a href="?check_host=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-info">Check Host</a>
                        <a href="?ban_ip=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-danger">BAN</a>
                    </td>
                    <td><span class="badge badge-light border"><?php echo $row['dominio']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['http_status'] == 200) ? 'success' : 'warning'; ?>"><?php echo $row['http_status']; ?></span></td>
                    <td><small><strong><?php echo $row['metodo']; ?></strong> <?php echo $row['recurso']; ?></small></td>
                    <td><small class="text-muted"><?php echo $row['user_agent']; ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="sticky-footer text-center">
    <span>© 2026 Alfonso Orozco Aguilar | "Vibecoding" Engineering | Stack: PHP Procedural + MariaDB</span>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html><?php
include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso no autorizado.");
}

// Acciones: Baneo
if (isset($_GET['ban_ip'])) {
    $ip = $link->real_escape_string($_GET['ban_ip']);
    $link->query("INSERT IGNORE INTO master_bans (ip, fecha_ban) VALUES ('$ip', NOW())");
    $link->query("UPDATE log_indexado SET ipbanned = 'YES' WHERE ip_remota = '$ip'");
    header("Location: index.php?msg=IP_Banneada");
    exit;
}

// Acción: Resolver Host Individual
if (isset($_GET['check_host'])) {
    $ip = $link->real_escape_string($_GET['check_host']);
    $host = gethostbyaddr($ip);
    $link->query("UPDATE log_indexado SET hostname = '$host' WHERE ip_remota = '$ip'");
    header("Location: index.php?ip_filter=$ip");
    exit;
}

// NUEVA ACCIÓN: Resolver todos los Hostnames vacíos (LIMIT 50 para no saturar)
if (isset($_GET['fix_hosts'])) {
    $res = $link->query("SELECT DISTINCT ip_remota FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO' LIMIT 50");
    while($r = $res->fetch_assoc()){
        $h = gethostbyaddr($r['ip_remota']);
        $link->query("UPDATE log_indexado SET hostname = '$h' WHERE ip_remota = '{$r['ip_remota']}'");
    }
    header("Location: index.php?msg=Hosts_Actualizados");
    exit;
}

// 4. CONSULTAS PARA FILTROS
$f_dominio = $_GET['dom_filter'] ?? '';
$f_ua = $_GET['ua_filter'] ?? '';
$f_ip = $_GET['ip_filter'] ?? '';

$where = "WHERE ipbanned = 'NO'";
if ($f_dominio) $where .= " AND dominio = '$f_dominio'";
if ($f_ua) $where .= " AND user_agent LIKE '%$f_ua%'";
if ($f_ip) $where .= " AND ip_remota = '$f_ip'";

$logs = $link->query("SELECT * FROM log_indexado $where ORDER BY fecha_acceso DESC, id DESC LIMIT 500");
$top10 = $link->query("SELECT ip_remota, COUNT(*) as hits FROM log_indexado WHERE ipbanned = 'NO' GROUP BY ip_remota ORDER BY hits DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VIBECODINGMEXICO.COM | Bot Block Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 60px; background: #f4f4f4; }
        /* Forzar el scroll horizontal */
        .table-responsive { 
            max-height: 75vh; 
            overflow-y: auto; 
            overflow-x: auto; /* Scroll horizontal habilitado */
            background: white; 
            white-space: nowrap; /* Evita que el texto salte de línea y fuerza el ancho */
        }
        .sticky-footer { position: fixed; bottom: 0; width: 100%; height: 40px; background: #343a40; color: white; line-height: 40px; font-size: 0.8rem; z-index: 1030; }
        .btn-xs { padding: 1px 5px; font-size: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="index.php">Gemini 3 Flash | VIBECODINGMEXICO.COM</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="menu" data-toggle="dropdown">Opciones</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="index.php">Dashboard</a>
                    <a class="dropdown-item" href="import.php">Importar Logs</a>
                    <a class="dropdown-item text-primary" href="?fix_hosts=1">🔍 Resolver Hostnames Vacíos</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item disabled">PHP: <?php echo phpversion(); ?></a>
                    <a class="dropdown-item disabled">DB: <?php echo $link->server_info; ?></a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card p-2 shadow-sm">
                <h6 class="border-bottom pb-1">Top 10 IPs (Activas)</h6>
                <div class="d-flex flex-wrap">
                    <?php while($t = $top10->fetch_assoc()): ?>
                        <span class="badge badge-danger m-1 p-2" style="cursor:pointer;" onclick="window.location='?ip_filter=<?php echo $t['ip_remota']; ?>'">
                            <?php echo $t['ip_remota']; ?> (<?php echo $t['hits']; ?>)
                        </span>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-2 shadow-sm mb-3">
        <form class="form-inline" method="GET">
            <input type="text" name="ip_filter" class="form-control form-control-sm mr-2" placeholder="IP" value="<?php echo $f_ip; ?>">
            <input type="text" name="ua_filter" class="form-control form-control-sm mr-2" placeholder="User Agent" value="<?php echo $f_ua; ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
        </form>
    </div>

    <div class="table-responsive shadow-sm border">
        <table class="table table-hover table-sm table-striped mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Fecha / Hora</th>
                    <th>IP / Hostname</th>
                    <th>Acción</th>
                    <th>Dominio</th>
                    <th>Status</th>
                    <th>Petición</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $logs->fetch_assoc()): ?>
                <tr>
                    <td><small><?php echo $row['fecha_acceso']; ?></small></td>
                    <td>
                        <strong><?php echo $row['ip_remota']; ?></strong><br>
                        <small class="text-info"><?php echo $row['hostname'] ?: '---'; ?></small>
                    </td>
                    <td>
                        <a href="?check_host=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-info">Check Host</a>
                        <a href="?ban_ip=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-danger">BAN</a>
                    </td>
                    <td><span class="badge badge-light border"><?php echo $row['dominio']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['http_status'] == 200) ? 'success' : 'warning'; ?>"><?php echo $row['http_status']; ?></span></td>
                    <td><small><strong><?php echo $row['metodo']; ?></strong> <?php echo $row['recurso']; ?></small></td>
                    <td><small class="text-muted"><?php echo $row['user_agent']; ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="sticky-footer text-center">
    <span>© 2026 Alfonso Orozco Aguilar | "Vibecoding" Engineering | Stack: PHP Procedural + MariaDB</span>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html><?php
include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso no autorizado.");
}

// Acciones: Baneo
if (isset($_GET['ban_ip'])) {
    $ip = $link->real_escape_string($_GET['ban_ip']);
    $link->query("INSERT IGNORE INTO master_bans (ip, fecha_ban) VALUES ('$ip', NOW())");
    $link->query("UPDATE log_indexado SET ipbanned = 'YES' WHERE ip_remota = '$ip'");
    header("Location: index.php?msg=IP_Banneada");
    exit;
}

// Acción: Resolver Host Individual
if (isset($_GET['check_host'])) {
    $ip = $link->real_escape_string($_GET['check_host']);
    $host = gethostbyaddr($ip);
    $link->query("UPDATE log_indexado SET hostname = '$host' WHERE ip_remota = '$ip'");
    header("Location: index.php?ip_filter=$ip");
    exit;
}

// NUEVA ACCIÓN: Resolver todos los Hostnames vacíos (LIMIT 50 para no saturar)
if (isset($_GET['fix_hosts'])) {
    $res = $link->query("SELECT DISTINCT ip_remota FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO' LIMIT 50");
    while($r = $res->fetch_assoc()){
        $h = gethostbyaddr($r['ip_remota']);
        $link->query("UPDATE log_indexado SET hostname = '$h' WHERE ip_remota = '{$r['ip_remota']}'");
    }
    header("Location: index.php?msg=Hosts_Actualizados");
    exit;
}

// 4. CONSULTAS PARA FILTROS
$f_dominio = $_GET['dom_filter'] ?? '';
$f_ua = $_GET['ua_filter'] ?? '';
$f_ip = $_GET['ip_filter'] ?? '';

$where = "WHERE ipbanned = 'NO'";
if ($f_dominio) $where .= " AND dominio = '$f_dominio'";
if ($f_ua) $where .= " AND user_agent LIKE '%$f_ua%'";
if ($f_ip) $where .= " AND ip_remota = '$f_ip'";

$logs = $link->query("SELECT * FROM log_indexado $where ORDER BY fecha_acceso DESC, id DESC LIMIT 500");
$top10 = $link->query("SELECT ip_remota, COUNT(*) as hits FROM log_indexado WHERE ipbanned = 'NO' GROUP BY ip_remota ORDER BY hits DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VIBECODINGMEXICO.COM | Bot Block Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 60px; background: #f4f4f4; }
        /* Forzar el scroll horizontal */
        .table-responsive { 
            max-height: 75vh; 
            overflow-y: auto; 
            overflow-x: auto; /* Scroll horizontal habilitado */
            background: white; 
            white-space: nowrap; /* Evita que el texto salte de línea y fuerza el ancho */
        }
        .sticky-footer { position: fixed; bottom: 0; width: 100%; height: 40px; background: #343a40; color: white; line-height: 40px; font-size: 0.8rem; z-index: 1030; }
        .btn-xs { padding: 1px 5px; font-size: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="index.php">Gemini 3 Flash | VIBECODINGMEXICO.COM</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="menu" data-toggle="dropdown">Opciones</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="index.php">Dashboard</a>
                    <a class="dropdown-item" href="import.php">Importar Logs</a>
                    <a class="dropdown-item text-primary" href="?fix_hosts=1">🔍 Resolver Hostnames Vacíos</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item disabled">PHP: <?php echo phpversion(); ?></a>
                    <a class="dropdown-item disabled">DB: <?php echo $link->server_info; ?></a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card p-2 shadow-sm">
                <h6 class="border-bottom pb-1">Top 10 IPs (Activas)</h6>
                <div class="d-flex flex-wrap">
                    <?php while($t = $top10->fetch_assoc()): ?>
                        <span class="badge badge-danger m-1 p-2" style="cursor:pointer;" onclick="window.location='?ip_filter=<?php echo $t['ip_remota']; ?>'">
                            <?php echo $t['ip_remota']; ?> (<?php echo $t['hits']; ?>)
                        </span>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-2 shadow-sm mb-3">
        <form class="form-inline" method="GET">
            <input type="text" name="ip_filter" class="form-control form-control-sm mr-2" placeholder="IP" value="<?php echo $f_ip; ?>">
            <input type="text" name="ua_filter" class="form-control form-control-sm mr-2" placeholder="User Agent" value="<?php echo $f_ua; ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
        </form>
    </div>

    <div class="table-responsive shadow-sm border">
        <table class="table table-hover table-sm table-striped mb-0">
            <thead class="thead-dark">
                <tr>
                    <th>Fecha / Hora</th>
                    <th>IP / Hostname</th>
                    <th>Acción</th>
                    <th>Dominio</th>
                    <th>Status</th>
                    <th>Petición</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $logs->fetch_assoc()): ?>
                <tr>
                    <td><small><?php echo $row['fecha_acceso']; ?></small></td>
                    <td>
                        <strong><?php echo $row['ip_remota']; ?></strong><br>
                        <small class="text-info"><?php echo $row['hostname'] ?: '---'; ?></small>
                    </td>
                    <td>
                        <a href="?check_host=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-info">Check Host</a>
                        <a href="?ban_ip=<?php echo $row['ip_remota']; ?>" class="btn btn-xs btn-danger">BAN</a>
                    </td>
                    <td><span class="badge badge-light border"><?php echo $row['dominio']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['http_status'] == 200) ? 'success' : 'warning'; ?>"><?php echo $row['http_status']; ?></span></td>
                    <td><small><strong><?php echo $row['metodo']; ?></strong> <?php echo $row['recurso']; ?></small></td>
                    <td><small class="text-muted"><?php echo $row['user_agent']; ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="sticky-footer text-center">
    <span>© 2026 Alfonso Orozco Aguilar | "Vibecodingmexico.com" Engineering | Stack: PHP Procedural + MariaDB</span>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
