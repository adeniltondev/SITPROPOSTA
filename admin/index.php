<?php
/**
 * Dashboard administrativo – página inicial do painel
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db = Database::getInstance();

// ── Totais principais ────────────────────────────────────────
$totalForms       = $db->fetchOne('SELECT COUNT(*) AS n FROM forms')['n'] ?? 0;
$totalActiveForms = $db->fetchOne('SELECT COUNT(*) AS n FROM forms WHERE is_active = 1')['n'] ?? 0;
$totalSubmissions = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions')['n'] ?? 0;
$totalPDFs        = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE pdf_path IS NOT NULL AND pdf_path != ""')['n'] ?? 0;
$todaySubmissions = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE DATE(created_at) = CURDATE()')['n'] ?? 0;
$weekSubmissions  = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['n'] ?? 0;
$monthSubmissions = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())')['n'] ?? 0;
$emailsSent       = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE email_sent = 1')['n'] ?? 0;
$uniqueIPs        = $db->fetchOne('SELECT COUNT(DISTINCT ip_address) AS n FROM submissions WHERE ip_address IS NOT NULL AND ip_address != ""')['n'] ?? 0;

$emailRate = $totalSubmissions > 0 ? round(($emailsSent / $totalSubmissions) * 100) : 0;
$pdfRate   = $totalSubmissions > 0 ? round(($totalPDFs   / $totalSubmissions) * 100) : 0;

// ── Envios por dia – últimos 14 dias ────────────────────────
$submissionsByDay = $db->fetchAll(
    'SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM submissions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC'
);
$dayLabels = [];
$dayTotals = [];
$dayMap    = [];
foreach ($submissionsByDay as $row) { $dayMap[$row['day']] = (int) $row['total']; }
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dayLabels[] = date('d/m', strtotime($d));
    $dayTotals[] = $dayMap[$d] ?? 0;
}

// ── Envios por formulário ────────────────────────────────────
$submissionsByForm = $db->fetchAll(
    'SELECT f.title, COUNT(s.id) AS total
     FROM forms f LEFT JOIN submissions s ON s.form_id = f.id
     GROUP BY f.id ORDER BY total DESC LIMIT 8'
);
$formLabels = json_encode(array_column($submissionsByForm, 'title'));
$formTotals = json_encode(array_map('intval', array_column($submissionsByForm, 'total')));

// ── Hora de pico ─────────────────────────────────────────────
$peakRow  = $db->fetchOne('SELECT HOUR(created_at) AS hr, COUNT(*) AS n FROM submissions GROUP BY HOUR(created_at) ORDER BY n DESC LIMIT 1');
$peakHour = $peakRow ? sprintf('%02d:00–%02d:59', $peakRow['hr'], $peakRow['hr']) : '—';

// ── Top IPs ──────────────────────────────────────────────────
$topIPs = $db->fetchAll(
    'SELECT ip_address,
            MAX(NULLIF(city,"")) AS city,
            MAX(NULLIF(state,"")) AS state,
            COUNT(*) AS total
     FROM submissions
     WHERE ip_address IS NOT NULL AND ip_address != ""
     GROUP BY ip_address ORDER BY total DESC LIMIT 8'
);

// ── Top cidades ───────────────────────────────────────────────
$topCities = $db->fetchAll(
    'SELECT city, state, COUNT(*) AS total
     FROM submissions
     WHERE city IS NOT NULL AND city != ""
     GROUP BY city, state ORDER BY total DESC LIMIT 8'
);

// ── Últimos 10 envios ────────────────────────────────────────
$recentSubmissions = $db->fetchAll(
    'SELECT s.id, s.created_at, s.pdf_path, s.email_sent, s.ip_address, s.city, s.state, f.title AS form_title
     FROM submissions s JOIN forms f ON f.id = s.form_id
     ORDER BY s.created_at DESC LIMIT 10'
);

// ── Formulários mais ativos ───────────────────────────────────
$topForms = $db->fetchAll(
    'SELECT f.id, f.title, f.slug, f.is_active, COUNT(s.id) AS total
     FROM forms f LEFT JOIN submissions s ON s.form_id = f.id
     GROUP BY f.id ORDER BY total DESC LIMIT 5'
);

$sysSettings = getAllSettings();
$pageTitle   = 'Dashboard';
$activeMenu  = 'dashboard';
require_once __DIR__ . '/layout/header.php';
?>

<style>
.progress-bar-wrap { background:var(--border); border-radius:99px; height:6px; overflow:hidden; margin-top:8px; }
.progress-bar-fill { height:100%; border-radius:99px; background:var(--primary); transition:width .5s ease; }
.progress-bar-fill.green  { background:var(--success); }
.progress-bar-fill.yellow { background:var(--warning); }
.section-title { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin:28px 0 14px; }
.mini-badge { display:inline-block; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
.mini-badge.active   { background:var(--success-light); color:var(--success); }
.mini-badge.inactive { background:var(--border); color:var(--muted); }
.chart-wrap { position:relative; height:220px; }
.ip-list { list-style:none; padding:0; margin:0; }
.ip-list li { display:flex; align-items:center; justify-content:space-between; padding:9px 18px; border-bottom:1px solid var(--border); font-size:13px; gap:10px; }
.ip-list li:last-child { border-bottom:none; }
.ip-badge { font-family:monospace; font-size:12px; background:var(--primary-light); color:var(--primary); padding:2px 8px; border-radius:4px; }
</style>

<!-- STATS linha 1 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= (int) $totalForms ?></div>
            <div class="stat-label">Formulários (<?= (int) $totalActiveForms ?> ativos)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293L8.586 13.293A1 1 0 007.879 13H4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalSubmissions) ?></div>
            <div class="stat-label">Envios Totais</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $todaySubmissions) ?></div>
            <div class="stat-label">Envios Hoje</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $uniqueIPs) ?></div>
            <div class="stat-label">Visitantes Únicos</div>
        </div>
    </div>
</div>

<!-- STATS linha 2 -->
<div class="stats-grid" style="margin-top:-14px;">
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $weekSubmissions) ?></div>
            <div class="stat-label">Esta Semana</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $monthSubmissions) ?></div>
            <div class="stat-label">Este Mês</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalPDFs) ?></div>
            <div class="stat-label">PDFs Gerados <span style="font-size:11px;color:var(--muted);">(<?= $pdfRate ?>%)</span></div>
            <div class="progress-bar-wrap"><div class="progress-bar-fill yellow" style="width:<?= $pdfRate ?>%"></div></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $emailsSent) ?></div>
            <div class="stat-label">E-mails Enviados <span style="font-size:11px;color:var(--muted);">(<?= $emailRate ?>%)</span></div>
            <div class="progress-bar-wrap"><div class="progress-bar-fill green" style="width:<?= $emailRate ?>%"></div></div>
        </div>
    </div>
</div>

<!-- GRÁFICOS -->
<p class="section-title">Análise de Acessos</p>
<div class="grid-main-wide" style="margin-bottom:28px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Envios por Dia — últimos 14 dias</h2>
            <span style="font-size:12px;color:var(--muted);">Hora de pico: <strong><?= e($peakHour) ?></strong></span>
        </div>
        <div class="card-body" style="padding:18px;">
            <div class="chart-wrap"><canvas id="chartByDay"></canvas></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Por Formulário</h2></div>
        <div class="card-body" style="padding:18px;">
            <div class="chart-wrap" style="height:200px;"><canvas id="chartByForm"></canvas></div>
        </div>
    </div>
</div>

<!-- ATIVIDADE RECENTE -->
<p class="section-title">Atividade Recente</p>
<div class="grid-main-side" style="margin-bottom:28px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Últimos Envios</h2>
            <a href="<?= $appUrl ?>/admin/submissions.php" class="btn btn-secondary btn-sm">Ver todos</a>
        </div>
        <div class="table-responsive">
            <?php if (empty($recentSubmissions)): ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5"/></svg>
                    <h3>Nenhum envio ainda</h3>
                    <p>Os envios aparecerão aqui quando alguém preencher um formulário.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Formulário</th><th class="col-hide-sm">IP</th><th class="col-hide-sm">Cidade / Estado</th><th>Data</th><th>PDF</th><th class="col-hide-sm">E-mail</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSubmissions as $sub): ?>
                        <tr>
                            <td class="text-muted text-sm"><?= (int) $sub['id'] ?></td>
                            <td><?= e($sub['form_title']) ?></td>
                            <td class="text-sm text-muted col-hide-sm" style="font-family:monospace;"><?= $sub['ip_address'] ? e($sub['ip_address']) : '—' ?></td>
                            <td class="text-sm col-hide-sm" style="white-space:nowrap;">
                                <?php if ($sub['city'] || $sub['state']): ?>
                                    <?= e(implode(' / ', array_filter([$sub['city'], $sub['state']]))) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm text-muted"><?= formatDate($sub['created_at'], true) ?></td>
                            <td><?= $sub['pdf_path'] ? '<span class="badge badge-success">✓</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                            <td class="col-hide-sm"><?= $sub['email_sent'] ? '<span class="badge badge-success">✓</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                            <td><a href="<?= $appUrl ?>/admin/submission-view.php?id=<?= (int) $sub['id'] ?>" class="btn btn-ghost btn-sm">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Formulários Ativos</h2></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($topForms)): ?>
                <div class="empty-state" style="padding:30px 16px;">
                    <p style="font-size:13px;color:var(--muted);">Nenhum formulário criado.</p>
                </div>
            <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php foreach ($topForms as $f): ?>
                    <li style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <div style="min-width:0;">
                            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($f['title']) ?></div>
                            <div class="text-muted text-sm" style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                                <?= (int) $f['total'] ?> envio(s)
                                <span class="mini-badge <?= $f['is_active'] ? 'active' : 'inactive' ?>"><?= $f['is_active'] ? 'Ativo' : 'Inativo' ?></span>
                            </div>
                        </div>
                        <a href="<?= $appUrl ?>/form.php?slug=<?= e($f['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">Abrir</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TOP IPs + TOP CIDADES -->
<p class="section-title">Localização dos Acessos</p>
<div class="grid-2col" style="margin-bottom:28px;">

    <?php if (!empty($topIPs)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Top IPs</h2>
            <span style="font-size:12px;color:var(--muted);"><?= number_format((int) $uniqueIPs) ?> único(s)</span>
        </div>
        <ul class="ip-list">
            <?php foreach ($topIPs as $ip): ?>
            <li>
                <div>
                    <span class="ip-badge"><?= e($ip['ip_address']) ?></span>
                    <?php if ($ip['city'] || $ip['state']): ?>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                            <?= e(implode(' / ', array_filter([$ip['city'], $ip['state']]))) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span style="font-weight:600;font-size:13px;"><?= (int) $ip['total'] ?> envio(s)</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($topCities)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Top Cidades</h2>
        </div>
        <ul class="ip-list">
            <?php foreach ($topCities as $c): ?>
            <li>
                <div>
                    <span style="font-weight:600;font-size:13px;"><?= e($c['city']) ?></span>
                    <?php if ($c['state']): ?>
                        <div style="font-size:11px;color:var(--muted);"><?= e($c['state']) ?></div>
                    <?php endif; ?>
                </div>
                <span style="font-size:13px;color:var(--muted);"><?= (int) $c['total'] ?> envio(s)</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#2563EB';
    const border  = '#e2e8f0';
    const muted   = '#94a3b8';

    new Chart(document.getElementById('chartByDay'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dayLabels) ?>,
            datasets: [{ label:'Envios', data:<?= json_encode($dayTotals) ?>,
                backgroundColor: primary+'33', borderColor: primary, borderWidth:2,
                borderRadius:4, hoverBackgroundColor: primary+'66' }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{
                x:{ grid:{color:border}, ticks:{color:muted,font:{size:11}} },
                y:{ grid:{color:border}, ticks:{color:muted,font:{size:11},precision:0}, beginAtZero:true }
            }
        }
    });

    const palette = ['#2563EB','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899'];
    new Chart(document.getElementById('chartByForm'), {
        type: 'doughnut',
        data: {
            labels: <?= $formLabels ?>,
            datasets:[{ data:<?= $formTotals ?>, backgroundColor:palette, borderWidth:2, borderColor:'#fff', hoverOffset:6 }]
        },
        options: {
            responsive:true, maintainAspectRatio:false, cutout:'65%',
            plugins:{ legend:{ position:'bottom', labels:{font:{size:11},color:muted,boxWidth:12,padding:10} } }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>