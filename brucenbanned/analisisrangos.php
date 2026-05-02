<?php
/**
 * Auditoría Forense V2 - Subredes y User Agents
 * Desarrollado para PHP 8.x Procedural
 * Licencia MIT
 * Alfonso Orozco AGuilar / Gemini Gemini 3 Flash
 * 1 de mayo 2026
 */
require_once 'config.php'; // Usa tu variable $link existente

// 1. Resumen general
$sql_total = "SELECT COUNT(*) as total, COUNT(DISTINCT ip_remota) as ips_unicas FROM log_indexado";
$res_total = mysqli_query($link, $sql_total);
$stats = mysqli_fetch_assoc($res_total);

// 2. Agrupación por Clase A
$sql_clase_a = "SELECT 
                    SUBSTRING_INDEX(ip_remota, '.', 1) as subred,
                    COUNT(*) as total_registros,
                    COUNT(DISTINCT ip_remota) as ips_distintas
                FROM log_indexado 
                GROUP BY subred 
                ORDER BY total_registros DESC";
$res_clase_a = mysqli_query($link, $sql_clase_a);

// 3. TOP 50 User Agents más frecuentes
$sql_agents = "SELECT 
                    user_agent, 
                    COUNT(*) as total,
                    COUNT(DISTINCT ip_remota) as ips_usando_este
                FROM log_indexado 
                GROUP BY user_agent 
                ORDER BY total DESC 
                LIMIT 50";
$res_agents = mysqli_query($link, $sql_agents);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría Maestra | Watchwolf</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.9rem; }
        .stats-card { border-left: 5px solid #007bff; border-radius: 8px; }
        .table-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .ua-text { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 0.8rem; }
        .ua-text:hover { white-space: normal; word-break: break-all; }
    </style>
</head>
<body>

<div class="container-fluid px-5 mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-dark"><i class="fas fa-microscope text-primary"></i> Auditoría Forense: Tráfico y Agentes</h2>
            <p class="text-muted small">Análisis basado en <?php echo number_format($stats['total']); ?> registros indexados.</p>
            <hr>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="card stats-card shadow-sm border-primary">
                <div class="card-body">
                    <h6 class="text-muted small uppercase">Total Registros</h6>
                    <h3 class="font-weight-bold mb-0"><?php echo number_format($stats['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card shadow-sm border-info">
                <div class="card-body">
                    <h6 class="text-muted small">IPs Únicas (Botnet Scale)</h6>
                    <h3 class="font-weight-bold text-info mb-0"><?php echo number_format($stats['ips_unicas']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-warning border-0 shadow-sm">
                <i class="fas fa-exclamation-triangle"></i> <strong>Nota de Campo:</strong> 
                Si el número de IPs Únicas es cercano al Total de Registros, estás ante un ataque de proxies rotativos.
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna Subredes -->
        <div class="col-lg-5 mb-4">
            <div class="table-container p-0">
                <div class="card-header bg-dark text-white font-weight-bold">
                    <i class="fas fa-network-wired"></i> Distribución por Clase A
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Subred</th>
                            <th>Registros</th>
                            <th>Carga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($res_clase_a)): 
                            $pct = ($row['total_registros'] / $stats['total']) * 100;
                        ?>
                        <tr>
                            <td class="font-weight-bold"><?php echo htmlspecialchars($row['subred']); ?>.x</td>
                            <td><?php echo number_format($row['total_registros']); ?></td>
                            <td>
                                <div class="progress" style="height: 12px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                                <small><?php echo round($pct, 2); ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Columna User Agents -->
        <div class="col-lg-7 mb-4">
            <div class="table-container p-0">
                <div class="card-header bg-secondary text-white font-weight-bold">
                    <i class="fas fa-user-secret"></i> Top 50 Agentes más Frecuentes
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Agente / Browser String</th>
                            <th class="text-center">IPs</th>
                            <th class="text-center">Hits</th>
                            <th>% Tráfico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($ua = mysqli_fetch_assoc($res_agents)): 
                            $pct_ua = ($ua['total'] / $stats['total']) * 100;
                            $bar_color = ($pct_ua > 5) ? 'bg-danger' : 'bg-success';
                        ?>
                        <tr>
                            <td>
                                <div class="ua-text" title="<?php echo htmlspecialchars($ua['user_agent']); ?>">
                                    <?php echo htmlspecialchars($ua['user_agent'] ?: 'Empty Agent'); ?>
                                </div>
                            </td>
                            <td class="text-center font-weight-bold"><?php echo number_format($ua['ips_usando_este']); ?></td>
                            <td class="text-center"><?php echo number_format($ua['total']); ?></td>
                            <td style="min-width: 120px;">
                                <div class="progress" style="height: 12px;">
                                    <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $pct_ua; ?>%"></div>
                                </div>
                                <small class="font-weight-bold"><?php echo round($pct_ua, 2); ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="mt-5 mb-4 text-center text-muted">
        <small>Auditoría Forense Procedural PHP 8.x | Vibecoding Style | KnownHost Performance</small>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
