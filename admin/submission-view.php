<?php
/**
 * Visualização detalhada de um envio + download de PDF
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db   = Database::getInstance();
$subId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$subId) {
    setFlash('Envio inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

$submission = $db->fetchOne(
    'SELECT s.*, f.title AS form_title, f.slug AS form_slug, f.fields, f.pdf_template, f.id AS form_id_val
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     WHERE s.id = ? LIMIT 1',
    [$subId]
);

if (!$submission) {
    setFlash('Envio não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

// -------------------------------------------------------
// Download PDF (attachment)
// -------------------------------------------------------
if (isset($_GET['download']) && $_GET['download'] == '1') {
    if (!empty($submission['pdf_path'])) {
        $pdfFile = PDF_PATH . DIRECTORY_SEPARATOR . basename($submission['pdf_path']);
        if (is_file($pdfFile)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="autorizacao_envio_' . $subId . '.pdf"');
            header('Content-Length: ' . filesize($pdfFile));
            header('Cache-Control: private, no-cache');
            readfile($pdfFile);
            exit;
        }
    }
    setFlash('Arquivo PDF não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Visualizar PDF inline (abre no navegador)
// -------------------------------------------------------
if (isset($_GET['view']) && $_GET['view'] == '1') {
    if (!empty($submission['pdf_path'])) {
        $pdfFile = PDF_PATH . DIRECTORY_SEPARATOR . basename($submission['pdf_path']);
        if (is_file($pdfFile)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="autorizacao_envio_' . $subId . '.pdf"');
            header('Content-Length: ' . filesize($pdfFile));
            header('Cache-Control: private, no-cache');
            readfile($pdfFile);
            exit;
        }
    }
    setFlash('Arquivo PDF não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Reenviar e-mail
// -------------------------------------------------------
if (isset($_POST['resend_email']) && validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    if (!empty($submission['pdf_path'])) {
        require_once APP_PATH . '/includes/mailer.php';
        $sysSettings = getAllSettings();
        $form        = ['id' => $submission['form_id_val'], 'title' => $submission['form_title'], 'fields' => $submission['fields']];
        $subArr      = ['id' => $submission['id'], 'data' => $submission['data'], 'created_at' => $submission['created_at']];
        $sent        = sendSubmissionEmail($subArr, $form, $submission['pdf_path'], $sysSettings);

        if ($sent) {
            $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$subId]);
            setFlash('E-mail reenviado com sucesso!', 'success');
        } else {
            setFlash('Falha ao reenviar e-mail. Verifique as configurações SMTP.', 'error');
        }
    } else {
        setFlash('Sem PDF disponível para enviar.', 'error');
    }

    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Regenerar PDF
// -------------------------------------------------------
if (isset($_POST['regen_pdf']) && validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    require_once APP_PATH . '/includes/pdf.php';
    $sysSettings = getAllSettings();
    $form        = [
        'id'           => $submission['form_id_val'],
        'title'        => $submission['form_title'],
        'fields'       => $submission['fields'],
        'pdf_template' => $submission['pdf_template'],
    ];
    $submData   = json_decode($submission['data'], true);
    $subArr     = [
        'id'         => $submission['id'],
        'data'       => $submData,
        'created_at' => $submission['created_at'],
    ];

    // Remove PDF antigo
    if (!empty($submission['pdf_path'])) {
        deleteUploadedFile($submission['pdf_path']);
    }

    $newPdf = generatePDF($form, $subArr, $sysSettings);
    if ($newPdf) {
        $db->query('UPDATE submissions SET pdf_path = ? WHERE id = ?', [$newPdf, $subId]);
        setFlash('PDF regenerado com sucesso!', 'success');
    } else {
        setFlash('Falha ao regenerar o PDF.', 'error');
    }

    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Prepara dados para exibição
// -------------------------------------------------------
$submData  = json_decode($submission['data'], true) ?? [];
$fields    = decodeFields($submission['fields']);
$form      = [
    'id'    => $submission['form_id_val'],
    'title' => $submission['form_title'],
    'slug'  => $submission['form_slug'],
];

$sysSettings = getAllSettings();
$appUrl      = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle   = 'Envio #' . $subId;
$activeMenu  = 'submissions';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="<?= $appUrl ?>/admin/submissions.php">Envios</a>
            <span>›</span>
            <span>#<?= $subId ?></span>
        </div>
        <h2>Detalhes do Envio #<?= $subId ?></h2>
        <p><?= e($submission['form_title']) ?> — <?= formatDate($submission['created_at'], true) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <?php if (!empty($submission['pdf_path'])): ?>
            <a href="?id=<?= $subId ?>&view=1" target="_blank" class="btn btn-primary"
               title="Abre o PDF no navegador">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Visualizar PDF
            </a>
            <a href="?id=<?= $subId ?>&download=1" class="btn btn-success"
               title="Baixa o arquivo PDF">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Baixar PDF
            </a>
        <?php else: ?>
            <span class="badge badge-gray" style="padding:8px 14px;font-size:12px;">PDF não gerado — use &ldquo;Regenerar PDF&rdquo;</span>
        <?php endif; ?>
        <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="grid-detail">

    <!-- Dados do envio -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Dados Preenchidos</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Campo</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Para formulários de autorização, exibe todos os campos do JSON
                    $isPdvAuth = ($submission['pdf_template'] ?? '') === 'authorization';
                    if ($isPdvAuth && !empty($submData)):
                        $authLabels = [
                            'nome_razao_social'      => 'Nome / Razão Social',
                            'sexo'                   => 'Sexo',
                            'data_nascimento'        => 'Data de Nascimento',
                            'rg'                     => 'RG nº',
                            'orgao_expedidor'        => 'Órgão Expedidor',
                            'cpf'                    => 'CPF nº',
                            'naturalidade'           => 'Naturalidade',
                            'nacionalidade'          => 'Nacionalidade',
                            'cnpj'                   => 'CNPJ nº',
                            'nome_fantasia'          => 'Nome de Fantasia',
                            'estado_civil'           => 'Estado Civil',
                            'conjuge'                => 'Cônjuge',
                            'telefones'              => 'Telefones',
                            'endereco_residencial'   => 'Endereço Residencial',
                            'bairro_residencial'     => 'Bairro Residencial',
                            'cidade_uf_residencial'  => 'Cidade/UF Residencial',
                            'cep_residencial'        => 'CEP Residencial',
                            'telefone_fixo'          => 'Telefone Fixo',
                            'celular'                => 'Celular / WhatsApp',
                            'endereco_comercial'     => 'Endereço Comercial',
                            'bairro_comercial'       => 'Bairro Comercial',
                            'cidade_uf_comercial'    => 'Cidade/UF Comercial',
                            'cep_comercial'          => 'CEP Comercial',
                            'emails'                 => 'E-mail(s)',
                            'tipo_imovel'            => 'Tipo do Imóvel',
                            'situacao_imovel'        => 'Situação do Imóvel',
                            'endereco_imovel'        => 'Endereço do Imóvel',
                            'bairro_imovel'          => 'Bairro do Imóvel',
                            'cidade_uf_imovel'       => 'Cidade/UF do Imóvel',
                            'cep_imovel'             => 'CEP do Imóvel',
                            'ponto_referencia'       => 'Ponto de Referência',
                            'registro_imovel'        => 'Registro do Imóvel',
                            'matricula_iptu'         => 'Matrícula de IPTU',
                            'num_dormitorios'        => 'Dormitórios',
                            'num_salas'              => 'Salas',
                            'num_suites'             => 'Suítes',
                            'garagens'               => 'Garagens',
                            'area_privativa'         => 'Área Privativa (m²)',
                            'tem_varanda'            => 'Tem Varanda?',
                            'tem_elevador'           => 'Tem Elevador?',
                            'lazer_completo'         => 'Lazer Completo?',
                            'garagem_coberta'        => 'Garagem Coberta?',
                            'obs_descricao'          => 'Obs. Descrição',
                            'valor_minimo_venda'     => 'Valor Mínimo de Venda (R$)',
                            'valor_minimo_extenso'   => 'Valor por Extenso',
                            'obs_preco'              => 'Obs. Preço',
                            'valor_condominio'       => 'Valor do Condomínio (R$)',
                            'valor_condominio_extenso' => 'Condomínio por Extenso',
                            'porcentagem_comissao'   => 'Comissão (%)',
                            'prazo_exclusividade'    => 'Prazo de Exclusividade (dias)',
                            'formas_pagamento'       => 'Formas de Pagamento',
                            'nome_corretor'          => 'Nome do Corretor',
                            'assinatura_contratante' => 'Assinatura Contratante',
                            'assinatura_conjuge'     => 'Assinatura Cônjuge',
                            'testemunha_1_nome'      => 'Testemunha 1 — Nome',
                            'testemunha_1_cpf'       => 'Testemunha 1 — CPF',
                            'testemunha_2_nome'      => 'Testemunha 2 — Nome',
                            'testemunha_2_cpf'       => 'Testemunha 2 — CPF',
                            // Documentos uploados
                            'doc_cpf_rg'             => 'Documento: RG / CPF',
                            'doc_iptu'               => 'Documento: IPTU',
                            'doc_matricula'          => 'Documento: Matrícula do Imóvel',
                            'doc_outros'             => 'Documento: Outros',
                        ];
                        foreach ($authLabels as $key => $label):
                            $val = $submData[$key] ?? '';
                            if ($val === '') continue;
                            $isDoc = str_starts_with($key, 'doc_');
                    ?>
                    <tr>
                        <td style="font-weight:600;background:#fafbfc;"><?= e($label) ?></td>
                        <td>
                        <?php if ($isDoc && !empty($val)): ?>
                            <a href="<?= $appUrl ?>/uploads/<?= e($val) ?>" target="_blank"
                               class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Baixar documento
                            </a>
                        <?php else: ?>
                            <?= nl2br(e((string) $val)) ?>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <?php foreach ($fields as $field): ?>
                    <?php
                        $name  = $field['name'] ?? '';
                        $label = $field['label'] ?? $name;
                        $type  = $field['type']  ?? 'text';
                        $value = $submData[$name] ?? '';
                        if ($type === 'checkbox') {
                            $value = $value == '1' ? 'Sim ✓' : 'Não';
                        }
                    ?>
                    <tr>
                        <td style="font-weight:600;background:#fafbfc;"><?= e($label) ?></td>
                        <td>
                        <?php if ($type === 'file' && !empty($value)): ?>
                            <a href="<?= $appUrl ?>/uploads/<?= e($value) ?>" target="_blank"
                               class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Baixar documento
                            </a>
                        <?php elseif ($type === 'file'): ?>
                            <span class="text-muted">— Não enviado</span>
                        <?php else: ?>
                            <?= nl2br(e((string) $value)) ?>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Painel lateral -->
    <div>
        <!-- Info do envio -->
        <div class="card mb-16">
            <div class="card-header"><h3 class="card-title">Informações</h3></div>
            <div class="card-body">
                <table style="width:100%;font-size:13px;">
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">ID</td>
                        <td style="text-align:right;font-weight:600;">#<?= $subId ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">Data</td>
                        <td style="text-align:right;"><?= formatDate($submission['created_at'], true) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">IP</td>
                        <td style="text-align:right;"><?= e($submission['ip_address'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">PDF</td>
                        <td style="text-align:right;">
                            <?php if (!empty($submission['pdf_path'])): ?>
                                <span class="badge badge-success">Gerado</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Não gerado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">E-mail</td>
                        <td style="text-align:right;">
                            <?php if ($submission['email_sent']): ?>
                                <span class="badge badge-info">Enviado</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Não enviado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Ações -->
        <div class="card mb-16">
            <div class="card-header"><h3 class="card-title">Ações</h3></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">

                <?php if (!empty($submission['pdf_path'])): ?>
                <!-- Ver / Baixar PDF -->
                <a href="?id=<?= $subId ?>&view=1" target="_blank"
                   class="btn btn-primary w-full" style="justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Visualizar PDF
                </a>
                <a href="?id=<?= $subId ?>&download=1"
                   class="btn btn-success w-full" style="justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Baixar PDF
                </a>
                <hr class="divider" style="margin:2px 0;">
                <?php endif; ?>

                <!-- Regenerar PDF -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="regen_pdf" value="1">
                    <button type="submit" class="btn <?= empty($submission['pdf_path']) ? 'btn-primary' : 'btn-secondary' ?> w-full"
                            style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                        <?= empty($submission['pdf_path']) ? 'Gerar PDF' : 'Regenerar PDF' ?>
                    </button>
                </form>

                <!-- Reenviar e-mail -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="resend_email" value="1">
                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Reenviar E-mail
                    </button>
                </form>

                <hr class="divider" style="margin:4px 0;">

                <!-- Excluir -->
                <form method="POST" action="<?= $appUrl ?>/admin/submission-delete.php"
                      data-confirm="Excluir este envio definitivamente?">
                    <?= csrfField() ?>
                    <input type="hidden" name="submission_id" value="<?= $subId ?>">
                    <input type="hidden" name="redirect" value="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>">
                    <button type="submit" class="btn btn-danger w-full" style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        Excluir Envio
                    </button>
                </form>
            </div>
        </div>

        <!-- Link do formulário -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Formulário</h3></div>
            <div class="card-body">
                <p class="text-sm text-muted mb-8"><?= e($submission['form_title']) ?></p>
                <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>"
                   class="btn btn-secondary btn-sm w-full" style="justify-content:center;">
                    Ver Todos os Envios
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
