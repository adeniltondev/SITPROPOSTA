<?php
/**
 * Edição de formulário existente
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db     = Database::getInstance();
$formId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$formId) {
    setFlash('Formulário inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

$form = $db->fetchOne('SELECT * FROM forms WHERE id = ? LIMIT 1', [$formId]);
if (!$form) {
    setFlash('Formulário não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/forms.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $title       = trim(strip_tags($_POST['title']       ?? ''));
        $description = trim(strip_tags($_POST['description']  ?? ''));
        $pdfTemplate = in_array($_POST['pdf_template'] ?? '', ['default', 'authorization']) ? $_POST['pdf_template'] : 'default';
        $fieldsJson  = $_POST['fields_json'] ?? '[]';

        if (strlen($title) < 2) $errors[] = 'O título deve ter ao menos 2 caracteres.';

        $decodedFields = json_decode($fieldsJson, true);
        if (!is_array($decodedFields)) {
            $errors[] = 'Estrutura de campos inválida.';
        }
        if (empty($decodedFields)) {
            $errors[] = 'Adicione ao menos um campo.';
        }

        if (empty($errors)) {
            $slug = uniqueSlug(generateSlug($title), $formId);

            $db->query(
                'UPDATE forms SET title = ?, slug = ?, description = ?, fields = ?, pdf_template = ?, updated_at = NOW() WHERE id = ?',
                [$title, $slug, $description, $fieldsJson, $pdfTemplate, $formId]
            );

            setFlash('Formulário atualizado com sucesso!', 'success');
            header('Location: ' . APP_URL . '/admin/forms.php');
            exit;
        }

        // Preserva dados do POST em caso de erro
        $form['title']        = $title;
        $form['description']  = $description;
        $form['pdf_template'] = $pdfTemplate;
        $form['fields']       = $fieldsJson;
    }
}

$currentFieldsJson = htmlspecialchars($form['fields'], ENT_QUOTES, 'UTF-8');
$sysSettings       = getAllSettings();
$appUrl            = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle         = 'Editar Formulário';
$activeMenu        = 'forms';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="<?= $appUrl ?>/admin/forms.php">Formulários</a>
            <span>›</span>
            <span>Editar</span>
        </div>
        <h2>Editar Formulário</h2>
    </div>
    <a href="<?= $appUrl ?>/form.php?slug=<?= e($form['slug']) ?>" target="_blank" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Ver Formulário
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">
        <?= implode('<br>', array_map('e', $errors)) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<form id="formBuilderForm" method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="fields_json" id="fieldsJson" value="<?= $currentFieldsJson ?>">

    <div class="builder-layout">

        <div>
            <div class="card mb-16">
                <div class="card-header">
                    <h3 class="card-title">Informações do Formulário</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="title">Título <span class="required">*</span></label>
                        <input class="form-control" type="text" id="title" name="title"
                               value="<?= e($form['title']) ?>" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label" for="description">Descrição</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= e($form['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Campos do Formulário</h3>
                    <span class="text-muted text-sm" id="fieldCount">0 campo(s)</span>
                </div>
                <div class="card-body">
                    <div id="fieldsList" class="fields-list"></div>
                </div>
                <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px;">
                    <a href="<?= $appUrl ?>/admin/forms.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>

        <div>
            <div class="card mb-16">
                <div class="card-header"><h3 class="card-title">Adicionar Campo</h3></div>
                <div class="card-body">
                    <div class="add-field-grid">
                        <button type="button" class="add-field-btn" data-type="text">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 7H7a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M9 12h6"/></svg>
                            Texto
                        </button>
                        <button type="button" class="add-field-btn" data-type="number">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            Número
                        </button>
                        <button type="button" class="add-field-btn" data-type="date">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Data
                        </button>
                        <button type="button" class="add-field-btn" data-type="select">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                            Seleção
                        </button>
                        <button type="button" class="add-field-btn" data-type="checkbox">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                            Checkbox
                        </button>
                        <button type="button" class="add-field-btn" data-type="textarea">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                            Área Texto
                        </button>
                        <button type="button" class="add-field-btn" data-type="file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                            Arquivo
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mb-16">
                <div class="card-header"><h3 class="card-title">Template PDF</h3></div>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <label class="form-label" for="pdf_template">Modelo de PDF</label>
                        <select class="form-control" id="pdf_template" name="pdf_template">
                            <option value="default"       <?= $form['pdf_template'] === 'default'       ? 'selected' : '' ?>>Padrão (genérico)</option>
                            <option value="authorization" <?= $form['pdf_template'] === 'authorization' ? 'selected' : '' ?>>Autorização de Venda</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="field-editor" id="fieldEditorPanel" style="display:none;">
                <h3>Editar Campo</h3>
                <form id="fieldEditorForm">
                    <div class="form-group">
                        <label class="form-label" for="editor_label">Rótulo <span class="required">*</span></label>
                        <input class="form-control" type="text" id="editor_label" placeholder="Ex: Nome Completo">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editor_name">Nome (chave) <span class="required">*</span></label>
                        <input class="form-control" type="text" id="editor_name" placeholder="Ex: nome_completo">
                        <p class="form-text">Letras, números e underscore.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editor_type">Tipo</label>
                        <select class="form-control" id="editor_type">
                            <option value="text">Texto</option>
                            <option value="number">Número</option>
                            <option value="date">Data</option>
                            <option value="select">Seleção (select)</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="textarea">Área de Texto</option>
                            <option value="file">Arquivo (upload)</option>
                        </select>
                    </div>
                    <div class="form-group" id="optionsGroup" style="display:none;">
                        <label class="form-label" for="editor_options">Opções (separadas por vírgula)</label>
                        <textarea class="form-control" id="editor_options" rows="3"
                                  placeholder="Opção 1,Opção 2,Opção 3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editor_placeholder">Placeholder</label>
                        <input class="form-control" type="text" id="editor_placeholder" placeholder="Texto de exemplo">
                    </div>
                    <div class="form-check mb-16">
                        <input type="checkbox" id="editor_required" value="1">
                        <label for="editor_required">Campo obrigatório</label>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar Campo</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="cancelEditBtn">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</form>

<script src="<?= $appUrl ?>/assets/js/form-builder.js"></script>
<script>
var observer = new MutationObserver(function () {
    var count = document.querySelectorAll('#fieldsList .field-card').length;
    var el = document.getElementById('fieldCount');
    if (el) el.textContent = count + ' campo(s)';
});
observer.observe(document.getElementById('fieldsList'), { childList: true, subtree: false });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
