<?php
/**
 * Monitor de disco por cuenta (cPanel/WHM)
 * Datos: API de WHM (listaccts), consultada al cargar la página. F5 para actualizar.
 * Acceso: IP permitida + sesión de administrador de WordPress.
 * License MIT
 * Author : Alfonso Orozco Aguilar COn ayuda de Claude Sonnet Medio 5.5
 * 29 de Septiembre 2026
 * El objetivo es mostrar usando token WHM de cpanel, el costo real que deberías estar
 * cobrando a clientes. Desde el whm generael archivo toke y guardalo con permisos 600
 * Se usa datatables para facilitar integración y filtro al usarlo.
 *
 * Permite entrar solo a la dirección IP autorizada y debes estar logueado en el wordpress que tu quieras
 * asi que se tiene a la vez protección por IP y por password sin usar base de datos.
 */

// ---------- CONFIGURACIÓN ----------
const IP_PERMITIDA   = '187.170.8.99'; // pon la tuya
const WP_LOAD        = __DIR__ . '/wp-load.php';    // ruta a wp-load.php
const TOKEN_FILE     = '/home/USUARIO/.whm_token';  // fuera de public_html, chmod 600
const WHM_USER       = 'root';
const WHM_URL        = 'https://127.0.0.1:2087';
const PRECIO_MENSUAL = 100.00;                      // USD/mes del VPS
const CAPACIDAD_GB   = 200;                         // capacidad total del disco en GB
const DISCO_RUTA     = '/';
// -----------------------------------

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

// 1) IP (si hay proxy/CDN delante, REMOTE_ADDR no será tu IP real)
if (($_SERVER['REMOTE_ADDR'] ?? '') !== IP_PERMITIDA) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// 2) Sesión de administrador de WordPress
require_once WP_LOAD;
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    http_response_code(403);
    exit('Acceso denegado: inicia sesión como administrador de WordPress.');
}

// ---------- Funciones ----------
function mdc_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function mdc_gb($v): ?float {
    $v = trim((string)$v);
    if ($v === '' || stripos($v, 'unlimited') !== false) return null;
    if (!preg_match('/^([\d.]+)\s*([KMGT]?)/i', $v, $m)) return null;
    $n = (float)$m[1];
    switch (strtoupper($m[2])) {
        case 'K': return $n / 1048576;
        case 'G': return $n;
        case 'T': return $n * 1024;
        default:  return $n / 1024; // "M" o sin unidad = MB
    }
}

function mdc_bar(float $pct): string {
    $cls = $pct >= 85 ? 'bad' : ($pct >= 70 ? 'warn' : '');
    return '<span class="bar ' . $cls . '"><i style="width:' . max(0, min(100, $pct)) . '%"></i></span>';
}

function mdc_listaccts(string $token): array {
    if ($token === '') throw new RuntimeException('No se encontró el token de WHM en TOKEN_FILE.');
    $ch = curl_init(WHM_URL . '/json-api/listaccts?api.version=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: whm ' . WHM_USER . ':' . $token],
        CURLOPT_SSL_VERIFYPEER => false, // 127.0.0.1 usa el certificado propio del servidor
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('No se pudo conectar a WHM: ' . $err);
    if ($code !== 200)   throw new RuntimeException("WHM respondió HTTP $code. Revisa el token y sus permisos.");
    $j = json_decode($body, true);
    if ((int)($j['metadata']['result'] ?? 0) !== 1) {
        throw new RuntimeException('WHM: ' . ($j['metadata']['reason'] ?? 'respuesta inválida'));
    }
    return $j['data']['acct'] ?? [];
}

function mdc_mem(): ?array {
    $t = @file_get_contents('/proc/meminfo');
    if (!$t || !preg_match('/MemTotal:\s+(\d+)/', $t, $a) || !preg_match('/MemAvailable:\s+(\d+)/', $t, $b)) return null;
    $tot = $a[1] / 1048576;
    $uso = $tot - $b[1] / 1048576;
    return ['total' => $tot, 'usada' => $uso, 'pct' => $tot > 0 ? $uso / $tot * 100 : 0];
}

// ---------- Datos ----------
$precio_gb = PRECIO_MENSUAL / CAPACIDAD_GB;

$filas = [];
$err_api = '';
try {
    $token = is_readable(TOKEN_FILE) ? trim((string)file_get_contents(TOKEN_FILE)) : '';
    foreach (mdc_listaccts($token) as $a) {
        $usado = mdc_gb($a['diskused'] ?? '0') ?? 0.0;
        $mens  = $usado * $precio_gb;
        $filas[] = [
            'user'   => $a['user'] ?? '',
            'domain' => $a['domain'] ?? '',
            'email'  => (isset($a['email']) && $a['email'][0] !== '*') ? $a['email'] : '',
            'plan'   => $a['plan'] ?? '',
            'susp'   => !empty($a['suspended']),
            'usado'  => $usado,
            'limite' => mdc_gb($a['disklimit'] ?? ''),
            'pct'    => $usado / CAPACIDAD_GB * 100,
            'mens'   => $mens,
            'anual'  => $mens * 12,
        ];
    }
} catch (Throwable $e) {
    $err_api = $e->getMessage();
}

$suma_gb    = array_sum(array_column($filas, 'usado'));
$suma_anual = array_sum(array_column($filas, 'anual'));

$d_total = @disk_total_space(DISCO_RUTA);
$d_libre = @disk_free_space(DISCO_RUTA);
$d_ok    = $d_total && $d_libre !== false;
$d_total_gb = $d_ok ? $d_total / 1073741824 : (float)CAPACIDAD_GB;
$d_libre_gb = $d_ok ? $d_libre / 1073741824 : null;
$d_usado_gb = $d_ok ? $d_total_gb - $d_libre_gb : null;
$d_pct      = $d_ok ? $d_usado_gb / $d_total_gb * 100 : null;

$load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
$mem  = mdc_mem();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Disco por cuenta</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
:root{--bg:#f3f5f7;--ink:#18232e;--muted:#5b6b7a;--line:#d6dde3;--accent:#0f6b8a;--warn:#b7791f;--bad:#b42318;--paper:#fff}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-variant-numeric:tabular-nums}
main{max-width:1200px;margin:0 auto;padding:24px 16px 48px}
h1{font-size:1.5rem;margin:0 0 2px}
.sub{color:var(--muted);margin:0 0 20px}
.strip{display:flex;flex-wrap:wrap;background:var(--paper);border:1px solid var(--line);margin-bottom:20px}
.strip>div{flex:1 1 200px;padding:14px 18px;border-left:1px solid var(--line)}
.strip>div:first-child{border-left:0;flex-basis:280px}
.strip b{display:block;font-size:1.35rem;line-height:1.3}
.strip small{color:var(--muted)}
.bar{display:block;height:6px;background:#e4e9ed;margin-top:8px;min-width:60px}
.bar i{display:block;height:100%;background:var(--accent)}
.bar.warn i{background:var(--warn)}.bar.bad i{background:var(--bad)}
.err{background:#fdecea;border:1px solid #f1b5ae;color:var(--bad);padding:12px 16px;margin-bottom:20px}
.tabla{background:var(--paper);border:1px solid var(--line);padding:16px;overflow-x:auto}
td.num,th.num{text-align:right}
td .bar{margin-top:4px}
.tag{font-size:.8rem;padding:1px 8px;border:1px solid var(--line);color:var(--muted)}
.tag.susp{border-color:var(--bad);color:var(--bad)}
a:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
</style>
</head>
<body>
<main>
  <h1>Disco por cuenta</h1>
  <p class="sub"><?= mdc_h(gethostname()) ?>, consultado el <?= date('Y-m-d H:i:s') ?>. Costo base: <?= number_format($precio_gb, 2) ?> USD por GB al mes (<?= number_format(PRECIO_MENSUAL, 0) ?> USD entre <?= (int)CAPACIDAD_GB ?> GB). F5 para actualizar.</p>

  <?php if ($err_api): ?><div class="err" role="alert"><?= mdc_h($err_api) ?></div><?php endif; ?>

  <section class="strip" aria-label="Resumen del servidor">
    <div>
      <?php if ($d_ok): ?>
        <b><?= number_format($d_pct, 1) ?>% del disco</b>
        <small><?= number_format($d_usado_gb, 0) ?> GB usados de <?= number_format($d_total_gb, 0) ?> GB, libres <?= number_format($d_libre_gb, 0) ?> GB</small>
        <?= mdc_bar($d_pct) ?>
      <?php else: ?>
        <b>Disco no disponible</b><small>PHP no puede leer <?= mdc_h(DISCO_RUTA) ?> (open_basedir)</small>
      <?php endif; ?>
    </div>
    <div>
      <b><?= number_format($suma_gb, 1) ?> GB en cuentas</b>
      <small><?= count($filas) ?> cuentas, <?= number_format($suma_anual, 0) ?> USD/año de piso</small>
    </div>
    <div>
      <?php if ($d_ok): ?>
        <b><?= number_format($d_libre_gb * $precio_gb * 12, 0) ?> USD/año sin usar</b>
        <small><?= number_format($d_libre_gb, 0) ?> GB libres pagados</small>
      <?php else: ?>
        <b>n/d</b><small>Espacio libre</small>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($load): ?>
        <b><?= number_format($load[0], 2) ?> / <?= number_format($load[1], 2) ?> / <?= number_format($load[2], 2) ?></b>
      <?php else: ?><b>n/d</b><?php endif; ?>
      <small>Load average 1, 5 y 15 min</small>
    </div>
    <div>
      <?php if ($mem): ?>
        <b><?= number_format($mem['pct'], 0) ?>% de RAM</b>
        <small><?= number_format($mem['usada'], 1) ?> GB de <?= number_format($mem['total'], 1) ?> GB</small>
        <?= mdc_bar($mem['pct']) ?>
      <?php else: ?><b>n/d</b><small>Memoria</small><?php endif; ?>
    </div>
  </section>

  <div class="tabla">
    <table id="cuentas" class="display" style="width:100%">
      <thead>
        <tr>
          <th>Cuenta</th><th>Dominio</th><th>Correo de contacto</th><th>Plan</th>
          <th class="num">Usado (GB)</th><th class="num">Límite (GB)</th>
          <th class="num">% del VPS</th><th class="num">USD/mes mín.</th><th class="num">USD/año mín.</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr>
          <td><?= mdc_h($f['user']) ?></td>
          <td><?= mdc_h($f['domain']) ?></td>
          <td><?= mdc_h($f['email']) ?></td>
          <td><?= mdc_h($f['plan']) ?></td>
          <td class="num" data-order="<?= round($f['usado'], 4) ?>"><?= number_format($f['usado'], 2) ?></td>
          <td class="num" data-order="<?= $f['limite'] === null ? -1 : round($f['limite'], 4) ?>"><?= $f['limite'] === null ? 'ilimitado' : number_format($f['limite'], 2) ?></td>
          <td class="num" data-order="<?= round($f['pct'], 4) ?>"><?= number_format($f['pct'], 2) ?>%<?= mdc_bar($f['pct']) ?></td>
          <td class="num" data-order="<?= round($f['mens'], 4) ?>"><?= number_format($f['mens'], 2) ?></td>
          <td class="num" data-order="<?= round($f['anual'], 4) ?>"><?= number_format($f['anual'], 2) ?></td>
          <td><span class="tag<?= $f['susp'] ? ' susp' : '' ?>"><?= $f['susp'] ? 'Suspendida' : 'Activa' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
$('#cuentas').DataTable({
  pageLength: 50,
  lengthMenu: [25, 50, 100],
  order: [[4, 'desc']],
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
});
</script>
</body>
</html>
