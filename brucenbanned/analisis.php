<?php
/**
 * SISTEMA: SNIPPET identificador / Bloqueador de Ips - Módulo de Análisis
 * https://vibecodingmexico.com/snippets-lector-de-raw-logs/
 * FECHA: 30 de abril de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Claude Sonnet 4.6 & Alfonso Orozco Aguilar
 * Dectar algunas incidencias y hacer autobaneo en primera ronda
 */

include 'config.php';

// 1. SEGURIDAD: Whitelist de IPs
$whitelist = ['TU_IP_AQUÍ', '187.170.217.123'];
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso no autorizado.");
}

// ─────────────────────────────────────────────
// ACCIÓN: Eliminar registros de IP webmaster
// ─────────────────────────────────────────────
$msg = '';
if (isset($_GET['delete_webmaster'])) {
    $ip = $link->real_escape_string($_GET['delete_webmaster']);
    $link->query("DELETE FROM log_indexado WHERE ip_remota = '$ip'");
    $affected = $link->affected_rows;
    $msg = "✅ Se eliminaron <strong>$affected registros</strong> de la IP $ip (webmaster).";
}

// ─────────────────────────────────────────────
// ACCIÓN: Autobaneo de IPs que solo tienen wp-login.php
// Se ejecuta al cargar la página, solo informativo
// ─────────────────────────────────────────────
$autobaned_count  = 0;
$autobaned_ips    = 0;

// IPs donde TODOS sus recursos son wp-login.php (fuerza bruta pura)
$brute_query = "
    SELECT ip_remota
    FROM log_indexado
    WHERE ipbanned = 'NO'
    GROUP BY ip_remota
    HAVING SUM(recurso NOT LIKE '%wp-login.php%') = 0
       AND COUNT(*) > 1
";
$brute_result = $link->query($brute_query);
$brute_ips = [];
if ($brute_result) {
    while ($r = $brute_result->fetch_assoc()) {
        $brute_ips[] = $r['ip_remota'];
    }
}

if (!empty($brute_ips)) {
    foreach ($brute_ips as $bip) {
        $bip_esc = $link->real_escape_string($bip);
        $link->query("INSERT IGNORE INTO master_bans (ip, fecha_ban) VALUES ('$bip_esc', NOW())");
        $link->query("UPDATE log_indexado SET ipbanned = 'YES' WHERE ip_remota = '$bip_esc'");
        $autobaned_count += $link->affected_rows;
        $autobaned_ips++;
    }
}

// ─────────────────────────────────────────────
// ACCIÓN AJAX: Resolver UN hostname por llamada
// Llamado desde JS en lotes, devuelve JSON
// ─────────────────────────────────────────────
if (isset($_GET['ajax_resolve_one'])) {
    header('Content-Type: application/json');
    // Tomar la siguiente IP sin hostname
    $res = $link->query("SELECT DISTINCT ip_remota FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $r   = $res->fetch_assoc();
        $ip  = $r['ip_remota'];
        $h   = @gethostbyaddr($ip);
        if (!$h || $h === $ip) $h = $ip; // si no resuelve, guarda la misma IP para no repetir
        $h_esc  = $link->real_escape_string($h);
        $ip_esc = $link->real_escape_string($ip);
        $link->query("UPDATE log_indexado SET hostname = '$h_esc' WHERE ip_remota = '$ip_esc'");
        echo json_encode(['done' => false, 'ip' => $ip, 'hostname' => $h]);
    } else {
        echo json_encode(['done' => true]);
    }
    exit;
}

// ─────────────────────────────────────────────
// Contar pendientes para AJAX
// ─────────────────────────────────────────────
if (isset($_GET['ajax_pending_count'])) {
    header('Content-Type: application/json');
    $r = $link->query("SELECT COUNT(DISTINCT ip_remota) as c FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO'");
    $row = $r->fetch_assoc();
    echo json_encode(['pending' => (int)$row['c']]);
    exit;
}

if (isset($_GET['msg'])) {
    $msg = htmlspecialchars(urldecode($_GET['msg']));
}

// ─────────────────────────────────────────────
// CONSULTAS DE ANÁLISIS
// ─────────────────────────────────────────────

// Total de registros activos
$total_res    = $link->query("SELECT COUNT(*) as t FROM log_indexado WHERE ipbanned = 'NO'");
$total_row    = $total_res->fetch_assoc();
$total_active = (int)$total_row['t'];

// Dominios analizados
$dom_res  = $link->query("SELECT DISTINCT dominio FROM log_indexado WHERE ipbanned = 'NO' ORDER BY dominio");
$dominios = [];
while ($d = $dom_res->fetch_assoc()) {
    $dominios[] = $d['dominio'];
}
$num_dominios = count($dominios);

// Top 5 IPs (posible webmaster)
$top5_res = $link->query("
    SELECT ip_remota, COUNT(*) as hits, hostname
    FROM log_indexado
    WHERE ipbanned = 'NO'
    GROUP BY ip_remota
    ORDER BY hits DESC
    LIMIT 5
");
$top5 = [];
while ($r = $top5_res->fetch_assoc()) {
    $top5[] = $r;
}

// Hostnames vacíos pendientes
$pending_hosts_res = $link->query("SELECT COUNT(DISTINCT ip_remota) as c FROM log_indexado WHERE (hostname IS NULL OR hostname = '') AND ipbanned = 'NO'");
$pending_hosts_row = $pending_hosts_res->fetch_assoc();
$pending_hosts     = (int)$pending_hosts_row['c'];

// Fuerza bruta WP (resumen post-autobaneo)
$brute_info_res = $link->query("
    SELECT COUNT(DISTINCT ip_remota) as ips, COUNT(*) as hits
    FROM log_indexado
    WHERE ipbanned = 'YES'
      AND ip_remota IN (SELECT ip FROM master_bans WHERE fecha_ban >= CURDATE())
");
$brute_info = $brute_info_res ? $brute_info_res->fetch_assoc() : ['ips' => $autobaned_ips, 'hits' => $autobaned_count];

// Estadísticas de bots conocidos
$bots = [
    'Amazonbot'      => "user_agent LIKE '%Amazonbot/%'",
    'Amzn-SearchBot' => "user_agent LIKE '%Amzn-SearchBot/%'",
    'Amzn-User'      => "user_agent LIKE '%Amzn-User/%'",
    'GPTBot'       => "user_agent LIKE '%GPTBot%'",
    'Googlebot'    => "user_agent LIKE '%Googlebot%' AND user_agent NOT LIKE '%AdsBot%'",
    'PetalBot'       => "user_agent LIKE '%PetalBot%'",
];
$bot_stats = [];
foreach ($bots as $nombre => $cond) {
    $r = $link->query("SELECT COUNT(*) as c FROM log_indexado WHERE ($cond)");
    $row = $r->fetch_assoc();
    $bot_stats[$nombre] = (int)$row['c'];
}
$total_all = $link->query("SELECT COUNT(*) as t FROM log_indexado")->fetch_assoc()['t'];

// Top 50 sospechosos sin "bot" en el agente (no baneados), con días distintos
$suspects_res = $link->query("
    SELECT
        ip_remota,
        hostname,
        COUNT(*) as hits,
        COUNT(DISTINCT DATE(fecha_acceso)) as dias_distintos,
        MAX(fecha_acceso) as ultima_visita,
        GROUP_CONCAT(DISTINCT dominio ORDER BY dominio SEPARATOR ', ') as dominios_vistos
    FROM log_indexado
    WHERE ipbanned = 'NO'
      AND (user_agent NOT LIKE '%bot%' AND user_agent NOT LIKE '%Bot%' AND user_agent NOT LIKE '%spider%' AND user_agent NOT LIKE '%Spider%' AND user_agent NOT LIKE '%crawler%')
    GROUP BY ip_remota
    ORDER BY hits DESC
    LIMIT 50
");
$suspects = [];
while ($r = $suspects_res->fetch_assoc()) {
    $suspects[] = $r;
}


// Multi-browser: IPs no baneadas que usan más de 1 user-agent distinto
$multibrowser_res = $link->query("
    SELECT
        ip_remota,
        hostname,
        COUNT(DISTINCT user_agent)          AS browsers_distintos,
        COUNT(*)                             AS hits,
        COUNT(DISTINCT DATE(fecha_acceso))   AS dias_distintos,
        COUNT(DISTINCT recurso)              AS recursos_distintos,
        MIN(fecha_acceso)                    AS primera_visita,
        MAX(fecha_acceso)                    AS ultima_visita,
        GROUP_CONCAT(DISTINCT
            SUBSTRING(user_agent, 1, 80)
            ORDER BY user_agent
            SEPARATOR '|||'
        )                                    AS agentes_muestra
    FROM log_indexado
    WHERE ipbanned = 'NO'
    GROUP BY ip_remota
    HAVING browsers_distintos > 1
    ORDER BY browsers_distintos DESC, hits DESC
    LIMIT 100
");
$multibrowser = [];
while ($r = $multibrowser_res->fetch_assoc()) {
    $multibrowser[] = $r;
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BruceNBanned | Análisis de Tráfico</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
:root {
    --bg:        #0d1117;
    --surface:   #161b22;
    --border:    #30363d;
    --accent:    #f0883e;
    --accent2:   #388bfd;
    --danger:    #da3633;
    --success:   #2ea043;
    --warning:   #d29922;
    --text:      #c9d1d9;
    --muted:     #8b949e;
    --header-h:  54px;
    --footer-h:  36px;
}

* { box-sizing: border-box; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 13px;
    padding-top: var(--header-h);
    padding-bottom: var(--footer-h);
    min-height: 100vh;
}

/* ── NAVBAR ── */
.navbar {
    background: var(--surface) !important;
    border-bottom: 1px solid var(--border);
    height: var(--header-h);
    padding: 0 1rem;
}
.navbar-brand {
    color: var(--accent) !important;
    font-weight: 700;
    letter-spacing: .5px;
}
.nav-link { color: var(--text) !important; }
.nav-link:hover { color: var(--accent) !important; }

/* ── FOOTER ── */
.sticky-footer {
    position: fixed; bottom: 0; width: 100%;
    height: var(--footer-h);
    background: var(--surface);
    border-top: 1px solid var(--border);
    color: var(--muted);
    line-height: var(--footer-h);
    font-size: .75rem;
    z-index: 1030;
}

/* ── CARDS ── */
.card-dark {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 1rem;
}
.card-dark .card-header {
    background: rgba(255,255,255,.04);
    border-bottom: 1px solid var(--border);
    padding: .5rem 1rem;
    font-weight: 700;
    letter-spacing: .5px;
    color: var(--accent);
    font-size: .8rem;
    text-transform: uppercase;
}
.card-dark .card-body { padding: .75rem 1rem; }

/* ── ALERT AUTOBANEO ── */
.alert-autoban {
    background: rgba(218,54,51,.12);
    border: 1px solid var(--danger);
    border-radius: 6px;
    color: #ff7b72;
    padding: .6rem 1rem;
    margin-bottom: 1rem;
}
.alert-ok {
    background: rgba(46,160,67,.12);
    border: 1px solid var(--success);
    border-radius: 6px;
    color: #7ee787;
    padding: .6rem 1rem;
    margin-bottom: 1rem;
}

/* ── STAT BADGES ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .75rem;
    margin-bottom: .5rem;
}
.stat-box {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .75rem;
    text-align: center;
}
.stat-box .num {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: .25rem;
}
.stat-box .lbl { font-size: .7rem; color: var(--muted); text-transform: uppercase; }
.stat-box.orange .num { color: var(--accent); }
.stat-box.blue .num   { color: var(--accent2); }
.stat-box.red .num    { color: var(--danger); }
.stat-box.green .num  { color: var(--success); }
.stat-box.yellow .num { color: var(--warning); }

/* ── BOT BARS ── */
.bot-row {
    display: flex;
    align-items: center;
    margin-bottom: .5rem;
    gap: .75rem;
}
.bot-label { width: 130px; color: var(--text); font-size: .8rem; flex-shrink: 0; }
.bot-bar-wrap { flex: 1; background: rgba(255,255,255,.06); border-radius: 3px; height: 14px; overflow: hidden; }
.bot-bar-fill { height: 100%; border-radius: 3px; transition: width .4s ease; }
.bot-count { width: 80px; text-align: right; color: var(--muted); font-size: .75rem; flex-shrink: 0; }
.bot-pct   { width: 45px; text-align: right; font-weight: 700; font-size: .8rem; flex-shrink: 0; }

/* ── WEBMASTER CARDS ── */
.wm-card {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .6rem .9rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .5rem;
}
.wm-rank {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--warning);
    width: 24px;
    text-align: center;
    flex-shrink: 0;
}
.wm-ip   { font-weight: 700; color: var(--accent2); }
.wm-host { font-size: .75rem; color: var(--muted); }
.wm-hits { margin-left: auto; font-weight: 700; color: var(--accent); font-size: 1rem; flex-shrink: 0; }
.btn-wm {
    background: var(--danger);
    border: none;
    color: #fff;
    border-radius: 4px;
    padding: 3px 10px;
    font-size: .72rem;
    cursor: pointer;
    text-decoration: none;
    flex-shrink: 0;
    white-space: nowrap;
}
.btn-wm:hover { background: #b52f2d; color: #fff; text-decoration: none; }

/* ── SUSPECTS TABLE ── */
.suspects-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .78rem;
}
.suspects-table thead th {
    background: rgba(255,255,255,.05);
    border-bottom: 1px solid var(--border);
    padding: .4rem .6rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: .68rem;
    letter-spacing: .5px;
    white-space: nowrap;
}
.suspects-table tbody tr {
    border-bottom: 1px solid rgba(48,54,61,.6);
    transition: background .15s;
}
.suspects-table tbody tr:hover { background: rgba(255,255,255,.04); }
.suspects-table td { padding: .35rem .6rem; vertical-align: middle; }
.suspects-table .ip-cell { color: var(--accent2); font-weight: 600; white-space: nowrap; }
.suspects-table .hits-cell { color: var(--accent); font-weight: 700; text-align: right; white-space: nowrap; }
.suspects-table .days-cell { text-align: center; }
.days-badge {
    display: inline-block;
    background: rgba(56,139,253,.15);
    border: 1px solid rgba(56,139,253,.4);
    border-radius: 10px;
    padding: 1px 8px;
    color: var(--accent2);
    font-size: .7rem;
    font-weight: 600;
}
.btn-analyze {
    background: rgba(56,139,253,.15);
    border: 1px solid rgba(56,139,253,.4);
    color: var(--accent2);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: .7rem;
    white-space: nowrap;
    text-decoration: none;
}
.btn-analyze:hover { background: rgba(56,139,253,.3); color: var(--accent2); text-decoration: none; }

/* ── SCROLL TABLE ── */
.table-scroll {
    max-height: 60vh;
    overflow-y: auto;
    overflow-x: auto;
}
.table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
.table-scroll::-webkit-scrollbar-track { background: var(--bg); }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

/* ── DOMAINS LIST ── */
.domain-tag {
    display: inline-block;
    background: rgba(240,136,62,.1);
    border: 1px solid rgba(240,136,62,.35);
    color: var(--accent);
    border-radius: 12px;
    padding: 2px 10px;
    font-size: .75rem;
    margin: 2px;
}

/* ── SECTION TITLES ── */
.section-title {
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--muted);
    margin-bottom: .75rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid var(--border);
}

.container-fluid { max-width: 1400px; padding: 1rem 1.25rem; }

/* ── DROPDOWN ── */
.dropdown-menu {
    background: var(--surface);
    border: 1px solid var(--border);
}
.dropdown-item { color: var(--text); font-size: .82rem; }
.dropdown-item:hover { background: rgba(255,255,255,.06); color: var(--accent); }
.dropdown-item.disabled { color: var(--muted) !important; }
.dropdown-divider { border-color: var(--border); }
</style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar navbar-expand-md fixed-top">
    <a class="navbar-brand" href="index.php">
        <i class="fas fa-shield-alt mr-1"></i> BruceNBanned
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                    <i class="fas fa-bars mr-1"></i> Módulos
                </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="index.php"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
                    <a class="dropdown-item" href="import.php"><i class="fas fa-file-import mr-1"></i> Importar Logs</a>
                    <a class="dropdown-item active" href="analisis.php"><i class="fas fa-microscope mr-1"></i> Análisis</a>
                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item disabled">PHP: <?php echo phpversion(); ?></span>
                    <span class="dropdown-item disabled">DB: <?php echo $link->server_info; ?></span>
                </div>
            </li>
        </ul>
        <span class="navbar-text" style="color:var(--muted); font-size:.75rem;">
            <i class="fas fa-database mr-1"></i>
            <?php echo number_format($total_all); ?> registros totales &nbsp;|&nbsp;
            <i class="fas fa-globe mr-1"></i>
            <?php echo $num_dominios; ?> dominio<?php echo $num_dominios != 1 ? 's' : ''; ?>
        </span>
    </div>
</nav>

<!-- ── CONTENIDO ── -->
<div class="container-fluid">

    <!-- Mensaje de acción -->
    <?php if ($msg): ?>
    <div class="alert-ok"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- Autobaneo informativo -->
    <?php if ($autobaned_ips > 0): ?>
    <div class="alert-autoban">
        <i class="fas fa-robot mr-1"></i>
        <strong>Autobaneo automático:</strong>
        Se detectaron y banearon <strong><?php echo $autobaned_ips; ?> IPs</strong>
        (<strong><?php echo number_format($autobaned_count); ?> registros</strong>) que solo tenían
        <code>wp-login.php</code> como recurso &mdash; fuerza bruta confirmada.
    </div>
    <?php else: ?>
    <div style="background:rgba(46,160,67,.08); border:1px solid rgba(46,160,67,.25); border-radius:6px; padding:.5rem 1rem; margin-bottom:1rem; color:var(--muted); font-size:.78rem;">
        <i class="fas fa-check-circle mr-1" style="color:var(--success);"></i>
        No se detectaron IPs nuevas de fuerza bruta pura (wp-login.php) en esta carga.
    </div>
    <?php endif; ?>

    <!-- ── FILA 1: Stats generales + Dominios ── -->
    <div class="row">
        <div class="col-md-8">
            <div class="card-dark">
                <div class="card-header"><i class="fas fa-chart-bar mr-1"></i> Resumen general</div>
                <div class="card-body">
                    <div class="stat-grid">
                        <div class="stat-box orange">
                            <div class="num"><?php echo number_format($total_active); ?></div>
                            <div class="lbl">Registros activos</div>
                        </div>
                        <div class="stat-box blue">
                            <div class="num"><?php echo number_format($total_all); ?></div>
                            <div class="lbl">Total histórico</div>
                        </div>
                        <div class="stat-box red">
                            <div class="num"><?php echo number_format($autobaned_ips); ?></div>
                            <div class="lbl">IPs autobanneadas hoy</div>
                        </div>
                        <div class="stat-box yellow">
                            <div class="num"><?php echo number_format($pending_hosts); ?></div>
                            <div class="lbl">Hosts sin resolver</div>
                        </div>
                        <div class="stat-box green">
                            <div class="num"><?php echo $num_dominios; ?></div>
                            <div class="lbl">Dominios en análisis</div>
                        </div>
                    </div>
                    <!-- Resolver hostnames: AJAX con barra de progreso -->
                    <div id="hosts-section" style="margin-top:.75rem;">
                        <?php if ($pending_hosts > 0): ?>
                        <div id="hosts-idle">
                            <button id="btn-start-resolve" class="btn btn-sm"
                                style="background:rgba(56,139,253,.15); border:1px solid rgba(56,139,253,.4); color:var(--accent2);">
                                <i class="fas fa-search-location mr-1"></i>
                                Resolver hostnames vacíos
                                <span id="pending-count" class="badge badge-secondary ml-1"><?php echo $pending_hosts; ?> IPs</span>
                            </button>
                        </div>
                        <div id="hosts-progress" style="display:none; margin-top:.5rem;">
                            <div style="font-size:.75rem; color:var(--muted); margin-bottom:.35rem;">
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                Resolviendo: <span id="resolve-current-ip" style="color:var(--accent2);">—</span>
                                &rarr; <span id="resolve-current-host" style="color:var(--text);">—</span>
                            </div>
                            <div style="background:rgba(255,255,255,.06); border-radius:3px; height:10px; overflow:hidden; margin-bottom:.35rem;">
                                <div id="resolve-bar" style="height:100%; background:var(--accent2); border-radius:3px; width:0%; transition:width .3s;"></div>
                            </div>
                            <div style="font-size:.72rem; color:var(--muted);">
                                <span id="resolve-done">0</span> resueltas de <span id="resolve-total"><?php echo $pending_hosts; ?></span>
                                &nbsp;|&nbsp;
                                <button id="btn-stop-resolve" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:.72rem; padding:0;">
                                    <i class="fas fa-stop-circle mr-1"></i>Detener
                                </button>
                            </div>
                        </div>
                        <div id="hosts-done" style="display:none; font-size:.75rem; color:var(--success); margin-top:.5rem;">
                            <i class="fas fa-check-circle mr-1"></i>
                            <span id="resolve-summary"></span>
                        </div>
                        <?php else: ?>
                        <span style="font-size:.75rem; color:var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Todos los hostnames están resueltos.
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark">
                <div class="card-header"><i class="fas fa-globe mr-1"></i> Dominios analizados</div>
                <div class="card-body">
                    <?php foreach ($dominios as $d): ?>
                    <span class="domain-tag"><i class="fas fa-server mr-1"></i><?php echo htmlspecialchars($d); ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($dominios)): ?>
                    <span style="color:var(--muted);">Sin dominios registrados.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── FILA 2: Posibles webmasters (Top 5) ── -->
    <div class="card-dark">
        <div class="card-header">
            <i class="fas fa-user-shield mr-1"></i>
            Top 5 IPs con más visitas &mdash; ¿Eres tú? (Posible Webmaster)
        </div>
        <div class="card-body">
            <p style="color:var(--muted); font-size:.77rem; margin-bottom:.75rem;">
                Si reconoces tu IP, haz clic en <strong style="color:var(--danger);">"Soy yo / Borrar"</strong>
                para eliminar todos sus registros. Esto no genera un ban, solo limpia el log.
            </p>
            <?php foreach ($top5 as $i => $wm): ?>
            <div class="wm-card">
                <div class="wm-rank">#<?php echo $i + 1; ?></div>
                <div>
                    <div class="wm-ip"><?php echo htmlspecialchars($wm['ip_remota']); ?></div>
                    <div class="wm-host"><?php echo htmlspecialchars($wm['hostname'] ?: '— hostname no resuelto —'); ?></div>
                </div>
                <div class="wm-hits"><?php echo number_format($wm['hits']); ?> hits</div>
                <a href="analisis.php?delete_webmaster=<?php echo urlencode($wm['ip_remota']); ?>"
                   class="btn-wm"
                   onclick="return confirm('¿Eliminar todos los registros de <?php echo htmlspecialchars($wm['ip_remota']); ?>? Esta acción no se puede deshacer.')">
                    <i class="fas fa-trash-alt mr-1"></i> Soy yo / Borrar
                </a>
                <a href="index.php?ip_filter=<?php echo urlencode($wm['ip_remota']); ?>"
                   class="btn-analyze ml-1">
                    <i class="fas fa-eye mr-1"></i> Ver en dashboard
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── FILA 3: Bots conocidos ── -->
    <div class="card-dark">
        <div class="card-header">
            <i class="fas fa-robot mr-1"></i>
            Estadísticas de bots conocidos vs total de registros
        </div>
        <div class="card-body">
            <p style="color:var(--muted); font-size:.75rem; margin-bottom:1rem;">
                Total histórico de registros: <strong style="color:var(--text);"><?php echo number_format($total_all); ?></strong>
                &nbsp;(incluye ya baneados)
            </p>
            <?php
            $colors = ['Amazonbot'=>'#f0883e','Amzn-SearchBot'=>'#e3b341','Amzn-User'=>'#f5a623','GPTBot'=>'#79c0ff','Googlebot'=>'#56d364','PetalBot'=>'#d2a8ff'];
            foreach ($bot_stats as $nombre => $cnt):
                $pct = $total_all > 0 ? round(($cnt / $total_all) * 100, 1) : 0;
                $color = $colors[$nombre] ?? '#8b949e';
            ?>
            <div class="bot-row">
                <div class="bot-label"><?php echo $nombre; ?></div>
                <div class="bot-bar-wrap">
                    <div class="bot-bar-fill" style="width:<?php echo min($pct * 3, 100); ?>%; background:<?php echo $color; ?>;"></div>
                </div>
                <div class="bot-count"><?php echo number_format($cnt); ?> regs</div>
                <div class="bot-pct" style="color:<?php echo $color; ?>;"><?php echo $pct; ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── FILA 4: Top 50 sospechosos sin "bot" ── -->
    <div class="card-dark">
        <div class="card-header">
            <i class="fas fa-user-secret mr-1"></i>
            Top 50 IPs sospechosas sin "bot" en su agente &mdash; no baneadas
        </div>
        <div class="card-body" style="padding:.5rem;">
            <p style="color:var(--muted); font-size:.75rem; padding:.25rem .5rem .5rem;">
                IPs con alto volumen que <em>no declaran ser bots</em> en su User-Agent.
                <strong style="color:var(--accent);">Días distintos</strong> indica en cuántos días diferentes apareció la IP.
                Más días = más persistente.
            </p>
            <div class="table-scroll">
                <table class="suspects-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>IP / Hostname</th>
                            <th style="text-align:right;">Hits</th>
                            <th style="text-align:center;">Días distintos</th>
                            <th>Última visita</th>
                            <th>Dominios</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suspects as $idx => $s): ?>
                        <tr>
                            <td style="color:var(--muted);"><?php echo $idx + 1; ?></td>
                            <td>
                                <div class="ip-cell"><?php echo htmlspecialchars($s['ip_remota']); ?></div>
                                <?php if ($s['hostname'] && $s['hostname'] !== $s['ip_remota']): ?>
                                <div style="font-size:.7rem; color:var(--muted);"><?php echo htmlspecialchars($s['hostname']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="hits-cell"><?php echo number_format($s['hits']); ?></td>
                            <td class="days-cell">
                                <span class="days-badge"><?php echo $s['dias_distintos']; ?> d</span>
                            </td>
                            <td style="color:var(--muted); white-space:nowrap; font-size:.72rem;"><?php echo $s['ultima_visita']; ?></td>
                            <td style="font-size:.7rem; color:var(--muted);"><?php echo htmlspecialchars($s['dominios_vistos']); ?></td>
                            <td style="white-space:nowrap;">
                                <a href="index.php?ip_filter=<?php echo urlencode($s['ip_remota']); ?>"
                                   class="btn-analyze mr-1">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="index.php?ban_ip=<?php echo urlencode($s['ip_remota']); ?>"
                                   class="btn btn-xs"
                                   style="background:rgba(218,54,51,.2); border:1px solid rgba(218,54,51,.4); color:#ff7b72; border-radius:4px; padding:2px 8px; font-size:.7rem;"
                                   onclick="return confirm('¿Banear <?php echo htmlspecialchars($s['ip_remota']); ?>?')">
                                    <i class="fas fa-ban"></i> Ban
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($suspects)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">
                            No hay IPs sospechosas sin agente de bot.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- ── FILA 5: Multi-browser sospechosos ── -->
    <div class="card-dark">
        <div class="card-header">
            <i class="fas fa-mask mr-1"></i>
            IPs que rotan User-Agent &mdash; usan más de 1 browser distinto (no baneadas)
            <span class="badge ml-2" style="background:rgba(218,54,51,.25); color:#ff7b72; font-size:.7rem;">
                <?php echo count($multibrowser); ?> IPs detectadas
            </span>
        </div>
        <div class="card-body" style="padding:.5rem;">
            <p style="color:var(--muted); font-size:.75rem; padding:.25rem .5rem .5rem;">
                Un humano normal usa 1 browser. Rotar agentes es señal clásica de bot o scraper.
                <strong style="color:var(--warning);">Browsers</strong> = cuántos user-agents distintos usó.
                <strong style="color:var(--accent2);">Días</strong> = en cuántos días diferentes apareció.
            </p>
            <div class="table-scroll">
                <table class="suspects-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>IP / Hostname</th>
                            <th style="text-align:center;">Browsers</th>
                            <th style="text-align:right;">Hits</th>
                            <th style="text-align:center;">Días</th>
                            <th style="text-align:center;">Recursos únicos</th>
                            <th>Primera → Última visita</th>
                            <th>User-Agents detectados</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($multibrowser as $idx => $mb):
                        $agentes = explode('|||', $mb['agentes_muestra']);
                        $riesgo_color = $mb['browsers_distintos'] >= 5 ? 'var(--danger)' : ($mb['browsers_distintos'] >= 3 ? 'var(--warning)' : 'var(--accent2)');
                    ?>
                        <tr>
                            <td style="color:var(--muted);"><?php echo $idx + 1; ?></td>
                            <td>
                                <div class="ip-cell"><?php echo htmlspecialchars($mb['ip_remota']); ?></div>
                                <?php if ($mb['hostname'] && $mb['hostname'] !== $mb['ip_remota']): ?>
                                <div style="font-size:.7rem; color:var(--muted);"><?php echo htmlspecialchars($mb['hostname']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="
                                    display:inline-block;
                                    background:rgba(255,255,255,.06);
                                    border:1px solid <?php echo $riesgo_color; ?>;
                                    border-radius:10px;
                                    padding:1px 10px;
                                    color:<?php echo $riesgo_color; ?>;
                                    font-weight:700;
                                    font-size:.85rem;
                                ">
                                    <?php echo $mb['browsers_distintos']; ?>
                                    <?php if ($mb['browsers_distintos'] >= 5): ?>
                                    <i class="fas fa-exclamation-triangle ml-1" style="font-size:.65rem;"></i>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="hits-cell"><?php echo number_format($mb['hits']); ?></td>
                            <td style="text-align:center;">
                                <span class="days-badge"><?php echo $mb['dias_distintos']; ?> d</span>
                            </td>
                            <td style="text-align:center; color:var(--muted); font-size:.78rem;">
                                <?php echo number_format($mb['recursos_distintos']); ?>
                            </td>
                            <td style="font-size:.7rem; color:var(--muted); white-space:nowrap;">
                                <?php echo substr($mb['primera_visita'], 0, 10); ?>
                                <span style="color:var(--border);">→</span>
                                <?php echo substr($mb['ultima_visita'], 0, 10); ?>
                            </td>
                            <td style="max-width:260px;">
                                <?php foreach ($agentes as $i => $ag): ?>
                                <div style="
                                    font-size:.65rem;
                                    color:var(--muted);
                                    background:rgba(255,255,255,.03);
                                    border:1px solid var(--border);
                                    border-radius:3px;
                                    padding:1px 5px;
                                    margin-bottom:2px;
                                    white-space:nowrap;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                    max-width:255px;
                                    title='<?php echo htmlspecialchars($ag); ?>'
                                ">
                                    <?php echo htmlspecialchars($ag); ?>
                                </div>
                                <?php if ($i >= 2 && count($agentes) > 3): ?>
                                <div style="font-size:.63rem; color:var(--muted); font-style:italic;">
                                    + <?php echo count($agentes) - 3; ?> más...
                                </div>
                                <?php break; endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="index.php?ip_filter=<?php echo urlencode($mb['ip_remota']); ?>"
                                   class="btn-analyze mr-1">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="index.php?ban_ip=<?php echo urlencode($mb['ip_remota']); ?>"
                                   class="btn btn-xs"
                                   style="background:rgba(218,54,51,.2); border:1px solid rgba(218,54,51,.4); color:#ff7b72; border-radius:4px; padding:2px 8px; font-size:.7rem;"
                                   onclick="return confirm('¿Banear <?php echo htmlspecialchars($mb['ip_remota']); ?>?')">
                                    <i class="fas fa-ban"></i> Ban
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($multibrowser)): ?>
                        <tr><td colspan="9" style="text-align:center; color:var(--muted); padding:2rem;">
                            No se detectaron IPs con múltiples user-agents.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container -->

<!-- ── FOOTER ── -->
<footer class="sticky-footer text-center">
    <span>© 2026 Alfonso Orozco Aguilar | Claude Sonnet 4.6 | BruceNBanned Analytics | PHP Procedural + MariaDB</span>
</footer>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var btn      = document.getElementById('btn-start-resolve');
    var btnStop  = document.getElementById('btn-stop-resolve');
    if (!btn) return;

    var idleDiv    = document.getElementById('hosts-idle');
    var progressDiv= document.getElementById('hosts-progress');
    var doneDiv    = document.getElementById('hosts-done');
    var barFill    = document.getElementById('resolve-bar');
    var curIp      = document.getElementById('resolve-current-ip');
    var curHost    = document.getElementById('resolve-current-host');
    var doneCount  = document.getElementById('resolve-done');
    var totalCount = document.getElementById('resolve-total');
    var summary    = document.getElementById('resolve-summary');

    var total    = parseInt(totalCount.textContent, 10);
    var resolved = 0;
    var running  = false;
    var stopped  = false;

    btn.addEventListener('click', function() {
        if (running) return;
        running = true;
        stopped = false;
        idleDiv.style.display     = 'none';
        progressDiv.style.display = 'block';
        doneDiv.style.display     = 'none';
        resolveNext();
    });

    btnStop.addEventListener('click', function() {
        stopped = true;
        running = false;
    });

    function resolveNext() {
        if (stopped) {
            showDone('Detenido manualmente. Se resolvieron ' + resolved + ' hostnames.');
            return;
        }
        fetch('analisis.php?ajax_resolve_one=1')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.done) {
                    showDone('¡Listo! Se resolvieron ' + resolved + ' hostnames correctamente.');
                    return;
                }
                resolved++;
                curIp.textContent     = data.ip;
                curHost.textContent   = data.hostname;
                doneCount.textContent = resolved;

                if (resolved % 5 === 0) {
                    fetch('analisis.php?ajax_pending_count=1')
                        .then(function(r) { return r.json(); })
                        .then(function(pd) {
                            var done = total - pd.pending;
                            if (done < 0) done = resolved;
                            barFill.style.width   = Math.min((done / total) * 100, 100) + '%';
                            doneCount.textContent = done;
                        }).catch(function(){});
                } else {
                    barFill.style.width = Math.min((resolved / total) * 100, 100) + '%';
                }
                // 300ms entre peticiones para no saturar DNS
                setTimeout(resolveNext, 300);
            })
            .catch(function() {
                showDone('Error de red tras ' + resolved + ' resueltos. Puedes intentar de nuevo recargando.');
                running = false;
            });
    }

    function showDone(msg) {
        progressDiv.style.display = 'none';
        doneDiv.style.display     = 'block';
        summary.textContent       = msg;
        running = false;
    }
})();
</script>
</body>
</html>
