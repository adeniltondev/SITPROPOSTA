<?php
/**
 * Listagem de formulários
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db = Database::getInstance();

// Ativa/Inativa formulário via GET (toggle)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggleId = (int) $_GET['id'];
    $db->query('UPDATE forms SET is_active = 1 - is_active WHERE id = ?', [$toggleId]);
    setFlash('Status do formulário atualizado.', 'success');
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

// Lista todos os formulários com contagem de envios
$forms = $db->fetchAll(
    'SELECT f.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM submissions s WHERE s.form_id = f.id) AS submission_count
     FROM forms f
     LEFT JOIN users u ON u.id = f.created_by
     ORDER BY f.created_at DESC'
);

$sysSettings = getAllSettings();
$appUrl      = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle   = 'Formulários';
$activeMenu  = 'forms';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h2>Formulários</h2>
        <p>Gerencie os formulários cadastrados no sistema.</p>
    </div>
    <a href="<?= $appUrl ?>/admin/form-create.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14m-7-7h14"/></svg>
        Novo Formulário
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <?php if (empty($forms)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg>
                <h3>Nenhum formulário cadastrado</h3>
                <p>Crie seu primeiro formulário para começar a coletar dados.</p>
                <a href="<?= $appUrl ?>/admin/form-create.php" class="btn btn-primary">+ Criar Formulário</a>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Slug / URL</th>
                        <th>Campos</th>
                        <th>Envios</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                    <?php
                        $fields     = decodeFields($form['fields']);
                        $numFields  = count($fields);
                        $formUrl    = $appUrl . '/form.php?slug=' . urlencode($form['slug']);
                    ?>
                    <tr>
                        <td class="text-muted text-sm"><?= (int) $form['id'] ?></td>
                        <td>
                            <span style="font-weight:600;"><?= e($form['title']) ?></span>
                            <?php if ($form['pdf_template'] === 'authorization'): ?>
                                <br><span class="badge badge-primary" style="margin-top:3px;">Autorização</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <code style="font-size:11.5px;color:var(--muted);"><?= e($form['slug']) ?></code>
                                <button class="btn-icon" data-copy="<?= e($formUrl) ?>" title="Copiar link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                </button>
                                <a href="<?= e($formUrl) ?>" target="_blank" class="btn-icon" title="Abrir formulário">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>
                        </td>
                        <td><span class="badge badge-gray"><?= $numFields ?> campo(s)</span></td>
                        <td>
                            <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>" style="font-weight:600;color:var(--primary);">
                                <?= (int) $form['submission_count'] ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($form['is_active']): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted"><?= formatDate($form['created_at']) ?></td>
                        <td>
                            <div style="display:flex;gap:5px;align-items:center;">
                                <a href="<?= $appUrl ?>/admin/form-edit.php?id=<?= (int) $form['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
                                <a href="<?= $appUrl ?>/admin/forms.php?toggle=1&id=<?= (int) $form['id'] ?>"
                                   class="btn btn-sm <?= $form['is_active'] ? 'btn-ghost' : 'btn-success' ?>"
                                   data-confirm="<?= $form['is_active'] ? 'Desativar este formulário?' : 'Ativar este formulário?' ?>">
                                    <?= $form['is_active'] ? 'Desativar' : 'Ativar' ?>
                                </a>
                                <form method="POST" action="<?= $appUrl ?>/admin/form-delete.php"
                                      data-confirm="Excluir este formulário e todos os seus envios? Esta ação é irreversível.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
