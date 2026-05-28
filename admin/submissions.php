<?php
/**
 * Listagem de envios (submissões)
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db = Database::getInstance();

// Filtros
$filterFormId = (int) filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT);
$page         = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// Montagem da query com filtros
$whereSql    = '';
$whereParams = [];
if ($filterFormId) {
    $whereSql    = 'WHERE s.form_id = ?';
    $whereParams = [$filterFormId];
}

// Total para paginação
$totalRow   = $db->fetchOne(
    "SELECT COUNT(*) AS n FROM submissions s {$whereSql}",
    $whereParams
);
$totalItems = (int) ($totalRow['n'] ?? 0);
$totalPages = max(1, (int) ceil($totalItems / $perPage));

// Submissions com dados do formulário
$submissions = $db->fetchAll(
    "SELECT s.id, s.created_at, s.pdf_path, s.email_sent, s.ip_address,
            f.title AS form_title, f.slug AS form_slug, f.id AS form_id_val
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     {$whereSql}
     ORDER BY s.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $whereParams
);

// Lista de formulários para o filtro
$allForms = $db->fetchAll('SELECT id, title FROM forms ORDER BY title ASC');

$sysSettings = getAllSettings();
$appUrl      = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle   = 'Envios';
$activeMenu  = 'submissions';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h2>Envios de Formulários</h2>
        <p>Total: <strong><?= number_format($totalItems) ?></strong> registo(s)
            <?= $filterFormId ? '— filtrado por formulário' : '' ?></p>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" action="" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label class="form-label mb-0" style="white-space:nowrap;">Formulário:</label>
            <select name="form_id" class="form-control" style="max-width:280px;">
                <option value="">Todos os formulários</option>
                <?php foreach ($allForms as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= $filterFormId === (int) $f['id'] ? 'selected' : '' ?>>
                        <?= e($f['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if ($filterFormId): ?>
                <a href="<?= $appUrl ?>/admin/submissions.php" class="btn btn-secondary btn-sm">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <?php if (empty($submissions)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5"/></svg>
                <h3>Nenhum envio encontrado</h3>
                <p>Os envios aparecerão aqui após alguém preencher um formulário público.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Formulário</th>
                        <th>Data / Hora</th>
                        <th class="col-hide-sm">IP</th>
                        <th>PDF</th>
                        <th class="col-hide-sm">E-mail</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td class="text-muted text-sm"><?= (int) $sub['id'] ?></td>
                        <td>
                            <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $sub['form_id_val'] ?>"
                               style="font-weight:600;color:var(--primary);">
                                <?= e($sub['form_title']) ?>
                            </a>
                        </td>
                        <td class="text-sm"><?= formatDate($sub['created_at'], true) ?></td>
                        <td class="text-sm text-muted col-hide-sm"><?= e($sub['ip_address'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($sub['pdf_path'])): ?>
                                <a href="<?= $appUrl ?>/admin/submission-view.php?id=<?= (int) $sub['id'] ?>&download=1"
                                   class="badge badge-success" style="text-decoration:none;">
                                    ↓ Download
                                </a>
                            <?php else: ?>
                                <span class="badge badge-gray">Sem PDF</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-sm">
                            <?php if ($sub['email_sent']): ?>
                                <span class="badge badge-info">✓ Enviado</span>
                            <?php else: ?>
                                <span class="badge badge-gray">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <a href="<?= $appUrl ?>/admin/submission-view.php?id=<?= (int) $sub['id'] ?>"
                                   class="btn btn-secondary btn-sm">Ver</a>
                                <form method="POST" action="<?= $appUrl ?>/admin/submission-delete.php"
                                      data-confirm="Excluir este envio? O PDF associado também será removido.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginação -->
            <?php if ($totalPages > 1): ?>
            <div style="padding:14px 20px;display:flex;align-items:center;gap:8px;justify-content:center;border-top:1px solid var(--border);">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $filterFormId ? '&form_id=' . $filterFormId : '' ?>" class="btn btn-secondary btn-sm">← Anterior</a>
                <?php endif; ?>
                <span class="text-sm text-muted">Página <?= $page ?> de <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $filterFormId ? '&form_id=' . $filterFormId : '' ?>" class="btn btn-secondary btn-sm">Próxima →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
