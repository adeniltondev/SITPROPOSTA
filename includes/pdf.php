<?php
/**
 * PDF Helper – A4 Imobiliária
 *
 * Funções de geração de contratos em PDF via DomPDF.
 * =====================================================
 *  buildAuthorizationHTML  – Autorização de Venda
 *  buildLocacaoHTML        – Autorização de Locação
 *  buildDefaultHTML        – Fallback genérico
 *  buildLogoImg            – Utilitário base64
 * =====================================================
 */

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ============================================================
// FUNÇÃO PRINCIPAL: GERAR PDF
// ============================================================

/**
 * Gera um arquivo PDF a partir dos dados da submissão.
 *
 * @param  array  $form        Dados do formulário (inclui pdf_template)
 * @param  array  $submission  Dados da submissão
 * @param  array  $settings    Configurações da aplicação
 * @return string|null         Caminho relativo do PDF gerado ou null em caso de erro
 */
function generatePDF(array $form, array $submission, array $settings = [])
{
    try {
        // Seleciona o template HTML
        $template = $form['pdf_template'] ?? 'default';
        $data     = is_array($submission['data'])
            ? $submission['data']
            : json_decode($submission['data'], true);

        if ($template === 'authorization') {
            $html = buildAuthorizationHTML($form, $submission, $data, $settings);
        } elseif ($template === 'locacao') {
            $html = buildLocacaoHTML($form, $submission, $data, $settings);
        } elseif ($template === 'proposta-locacao') {
            $html = buildPropostaLocacaoHTML($form, $submission, $data, $settings);
        } elseif ($template === 'proposta-fiador') {
            $html = buildPropostaFiadorHTML($form, $submission, $data, $settings);
        } else {
            $html = buildDefaultHTML($form, $submission, $data, $settings);
        }

        // Carrega o DomPDF
        $vendorPath = APP_PATH . '/vendor';
        if (!class_exists('Dompdf\Dompdf')) {
            $autoload = $vendorPath . '/autoload.php';
            if (!is_file($autoload)) {
                throw new RuntimeException('DomPDF não encontrado. Execute: composer require dompdf/dompdf');
            }
            require_once $autoload;
        }

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        if (empty($pdfContent)) {
            throw new RuntimeException('DomPDF retornou conteúdo vazio.');
        }

        // Salva o arquivo
        $pdfDir = PDF_PATH;
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $submId   = (int) ($submission['id'] ?? 0);
        $filename = 'form_' . $submId . '_' . time() . '.pdf';
        $fullPath = $pdfDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($fullPath, $pdfContent) === false) {
            throw new RuntimeException('Não foi possível salvar o PDF em: ' . $fullPath);
        }

        return 'pdfs/' . $filename;

    } catch (Exception $e) {
        error_log('[FORMA4 PDF] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}

// ============================================================
// CSS COMPARTILHADO – Design moderno para ambos os templates
// ============================================================

function sharedPdfCss(string $primary = '#0b3a50'): string
{
    // Derivar variações da cor primária para uso no CSS
    return <<<CSS
/* ── RESET ── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',sans-serif; font-size:9px; color:#1e293b; background:#f8fafc; line-height:1.5; }

/* ══════════════════════════════════════════════
   HEADER PREMIUM
══════════════════════════════════════════════ */
.ph-wrap { background:#0d1f35; width:100%; }
.ph-inner { padding:18px 22px 16px; }
.ph-inner table { width:100%; border-collapse:collapse; }
.ph-logo-cell { width:110px; vertical-align:middle; }
.ph-title-cell { vertical-align:middle; padding-left:18px; }
.ph-meta-cell  { vertical-align:middle; text-align:right; width:150px; }

.logo-frame { background:#fff; border-radius:6px; padding:5px 9px; display:inline-block; text-align:center; }
.logo-frame img { max-height:48px; max-width:96px; display:block; }
.brand-text { color:#fff; font-size:15px; font-weight:bold; line-height:1.1; }
.brand-sub  { color:#94a3b8; font-size:6.5px; text-transform:uppercase; letter-spacing:.5px; }

.ph-badge { background:#1e3a5a; color:#7dd3fc; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.7px; padding:2px 8px; border-radius:10px; display:inline-block; margin-bottom:6px; }
.ph-h1    { color:#f0f9ff; font-size:20px; font-weight:bold; line-height:1.1; letter-spacing:-.3px; }
.ph-sub   { color:#7dd3fc; font-size:8.5px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; margin-top:2px; display:block; }
.ph-desc  { color:#94a3b8; font-size:7.5px; margin-top:4px; }

.ph-protocol { color:#f0f9ff; font-size:13px; font-weight:bold; }
.ph-date     { color:#94a3b8; font-size:7px; margin-top:2px; }
.ph-status   { background:#166534; color:#dcfce7; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; padding:3px 9px; border-radius:10px; display:inline-block; margin-top:5px; }

/* Linha de acento */
.ph-accent { background:{$primary}; height:3px; width:100%; }

/* ══════════════════════════════════════════════
   INFO STRIP (linha de metadados)
══════════════════════════════════════════════ */
.info-strip { background:#f1f5f9; border-bottom:1px solid #e2e8f0; padding:7px 22px; }
.info-strip table { width:100%; border-collapse:collapse; }
.info-strip td { vertical-align:middle; padding-right:20px; }
.is-lbl { color:#94a3b8; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:1px; }
.is-val  { color:#0f172a; font-size:8.5px; font-weight:bold; }

/* ══════════════════════════════════════════════
   BODY
══════════════════════════════════════════════ */
.page-body { padding:14px 22px 0; background:#f8fafc; }

/* ══════════════════════════════════════════════
   SECTION CARD
══════════════════════════════════════════════ */
.card { background:#fff; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:12px; overflow:hidden; }
.card-head { background:{$primary}; padding:7px 14px; }
.card-head-alt { background:#f0f7ff; border-bottom:2px solid {$primary}; padding:7px 14px; }
.ch-title     { color:#fff; font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px; }
.ch-title-alt { color:{$primary}; font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px; }
.card-sub { background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:4px 14px; font-size:7.5px; color:#475569; }
.card-sub strong { color:{$primary}; }

/* ══════════════════════════════════════════════
   FIELD TABLE DENTRO DO CARD
══════════════════════════════════════════════ */
.ft { width:100%; border-collapse:collapse; }
.ft td { border-right:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; padding:6px 14px; background:#fff; vertical-align:top; }
.ft td:last-child { border-right:none; }
.ft tr:last-child td { border-bottom:none; }
.ft tr.alt td { background:#fafcff; }
.fl { color:#94a3b8; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.45px; display:block; margin-bottom:2px; }
.fv { color:#0f172a; font-size:9px; display:block; min-height:12px; }
.fv-money { color:#166534; font-size:11px; font-weight:bold; display:block; }
.fv-empty { color:#cbd5e0; font-style:italic; }

/* ══════════════════════════════════════════════
   BADGES / DESTAQUES
══════════════════════════════════════════════ */
.badge        { display:inline-block; padding:2px 8px; border-radius:10px; font-size:7px; font-weight:bold; }
.badge-green  { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.badge-blue   { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe; }
.badge-gray   { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.badge-yellow { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
.money-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; padding:4px 10px; display:inline-block; }
.money-box span { color:#166534; font-size:11px; font-weight:bold; }

/* ══════════════════════════════════════════════
   BLOCO LEGAL / CLÁUSULAS
══════════════════════════════════════════════ */
.legal { background:#fffbeb; border:1px solid #fde68a; border-left:3px solid #f59e0b; border-radius:0 5px 5px 0; padding:9px 13px; font-size:7.5px; line-height:1.9; color:#78350f; text-align:justify; margin:10px 0; }
.clause { font-size:7.5px; line-height:1.9; color:#334155; text-align:justify; margin-bottom:5px; padding-left:4px; }
.cl     { font-weight:bold; color:{$primary}; }
.agree-text { font-size:7.5px; line-height:1.9; color:#334155; text-align:justify; margin-top:7px; }
.date-line  { font-size:8px; text-align:right; margin-top:8px; color:#475569; }

/* ══════════════════════════════════════════════
   ASSINATURAS
══════════════════════════════════════════════ */
.sig-section { padding:12px 22px 0; }
.sig-section-title { font-size:7.5px; font-weight:bold; text-transform:uppercase; color:#94a3b8; letter-spacing:.5px; margin-bottom:10px; }
.sig-table { width:100%; border-collapse:collapse; }
.sig-table td { text-align:center; padding:28px 10px 6px; vertical-align:bottom; width:25%; }
.sline  { border-top:1px solid #334155; padding-top:4px; font-size:8px; color:#334155; min-height:20px; }
.stitle { font-size:7px; font-weight:bold; color:{$primary}; text-transform:uppercase; margin-top:3px; }
.ssub   { font-size:6.5px; color:#94a3b8; font-style:italic; }
.test-label { font-size:7px; font-weight:bold; text-transform:uppercase; color:#94a3b8; letter-spacing:.5px; margin-top:12px; margin-bottom:0; padding-left:22px; }
.test-table { width:100%; border-collapse:collapse; }
.test-table td { text-align:center; padding:24px 14px 6px; vertical-align:bottom; width:50%; }

/* ══════════════════════════════════════════════
   DOCUMENTOS ANEXADOS
══════════════════════════════════════════════ */
.docs-table { width:100%; border-collapse:collapse; }
.docs-table tr { border-bottom:1px solid #f1f5f9; }
.docs-table tr:last-child { border-bottom:none; }
.docs-td-lbl { background:#f8fafc; width:34%; padding:6px 14px; vertical-align:middle; }
.docs-td-val { padding:6px 14px; vertical-align:middle; }
.docs-lbl  { color:#94a3b8; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.4px; display:block; }
.docs-file { font-size:8px; color:#0f172a; word-break:break-all; }
.docs-file a { color:{$primary}; text-decoration:none; }

/* ══════════════════════════════════════════════
   RODAPÉ
══════════════════════════════════════════════ */
.page-footer { background:#0d1f35; padding:10px 22px; margin-top:14px; }
.page-footer table { width:100%; border-collapse:collapse; }
.page-footer td { vertical-align:middle; color:#64748b; font-size:7px; }
.pf-right { text-align:right; }
.page-footer strong { color:#94a3b8; }
.pf-brand { color:#e2e8f0; font-size:8px; font-weight:bold; }
.pf-sep { color:#334155; margin:0 5px; }

/* ══════════════════════════════════════════════
   UTILITÁRIOS
══════════════════════════════════════════════ */
.mt4  { margin-top:4px; }
.mt8  { margin-top:8px; }
.mt12 { margin-top:12px; }
.mb0  { margin-bottom:0; }
.p0   { padding:0; }
.text-right { text-align:right; }
.text-center { text-align:center; }
hr.div { border:none; border-top:1px solid #e2e8f0; margin:8px 0; }
CSS;
}

// ============================================================
// TEMPLATE: AUTORIZAÇÃO DE VENDA COM EXCLUSIVIDADE
// ============================================================

/**
 * Monta HTML do contrato de Autorização de Venda com Exclusividade.
 */
function buildAuthorizationHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName  = e($settings['app_name'] ?? APP_NAME);
    $logoPath = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $submId   = (int) $submission['id'];
    $submDate = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));
    $anoAtual = date('Y');

    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    // ── Contratante
    $nomeContratante = $d('nome_razao_social');
    $sexo            = $d('sexo');
    $dataNasc        = $d('data_nascimento');
    $rg              = $d('rg');
    $orgaoExp        = $d('orgao_expedidor');
    $cpf             = $d('cpf');
    $naturalidade    = $d('naturalidade');
    $nacionalidade   = $d('nacionalidade');
    $cnpj            = $d('cnpj');
    $nomeFant        = $d('nome_fantasia');
    $estadoCivil     = $d('estado_civil');
    $conjuge         = $d('conjuge');
    $telefones       = $d('telefones');
    $endRes          = $d('endereco_residencial');
    $bairroRes       = $d('bairro_residencial');
    $cidUfRes        = $d('cidade_uf_residencial');
    $cepRes          = $d('cep_residencial');
    $telFixo         = $d('telefone_fixo');
    $celular         = $d('celular');
    $endCom          = $d('endereco_comercial');
    $bairroCom       = $d('bairro_comercial');
    $cidUfCom        = $d('cidade_uf_comercial');
    $cepCom          = $d('cep_comercial');
    $emails          = $d('emails');

    // ── Imóvel
    $tipoImovel     = $d('tipo_imovel');
    $situacaoImovel = $d('situacao_imovel');
    $endImovel      = $d('endereco_imovel');
    $bairroImovel   = $d('bairro_imovel');
    $cidUfImovel    = $d('cidade_uf_imovel');
    $cepImovel      = $d('cep_imovel');
    $pontoRef       = $d('ponto_referencia');
    $registroImovel = $d('registro_imovel');
    $matriculaIptu  = $d('matricula_iptu');

    // ── Descrição
    $numDorm    = $d('num_dormitorios');
    $numSalas   = $d('num_salas');
    $numSuites  = $d('num_suites');
    $garagens   = $d('garagens');
    $areaPriv   = $d('area_privativa');
    $temVaranda = $d('tem_varanda');
    $temElevador= $d('tem_elevador');
    $lazer      = $d('lazer_completo');
    $garagemCob = $d('garagem_coberta');
    $obsDesc    = $d('obs_descricao');

    // ── Condições
    $valorMin        = $d('valor_minimo_venda', '—');
    $valorMinExtenso = $d('valor_minimo_extenso');
    $obsPreco        = $d('obs_preco');
    $valorCondo      = $d('valor_condominio', '—');
    $condoExtenso    = $d('valor_condominio_extenso');
    $formasPag       = $d('formas_pagamento');
    $comissao        = $d('porcentagem_comissao', '—');
    $prazo           = $d('prazo_exclusividade', '—');

    // ── Assinaturas
    $nomeCorretor = $d('nome_corretor');
    $test1Nome    = $d('testemunha_1_nome');
    $test1Cpf     = $d('testemunha_1_cpf');
    $test2Nome    = $d('testemunha_2_nome');
    $test2Cpf     = $d('testemunha_2_cpf');

    // ── Badge exclusividade
    $exc = trim($data['com_exclusividade'] ?? '');
    $excBadge = ($exc === 'Sim')
        ? "<span class='badge badge-green'>&#10003; COM EXCLUSIVIDADE</span>"
        : (($exc !== '') ? "<span class='badge badge-gray'>SEM EXCLUSIVIDADE</span>" : '');

    // ── Logo base64
    $logoCell = buildLogoBannerCell($logoPath, $appName);

    // ── Documentos
    $docsHtml = buildDocsSection([
        'doc_cpf_rg'    => 'RG / CPF do Propriet&aacute;rio',
        'doc_iptu'      => 'Carn&ecirc; / IPTU',
        'doc_matricula' => 'Matr&iacute;cula do Im&oacute;vel',
        'doc_outros'    => 'Outros Documentos',
    ], $data);

    $css = sharedPdfCss();

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><style>{$css}</style></head>
<body>

<div class="ph-wrap">
  <div class="ph-inner">
    <table><tr>
      <td class="ph-logo-cell"><div class="logo-frame">{$logoCell}</div></td>
      <td class="ph-title-cell">
        <div class="ph-badge">Documento Contratual</div>
        <div class="ph-h1">Autoriza&ccedil;&atilde;o de Venda</div>
        <span class="ph-sub">Com Exclusividade</span>
        <span class="ph-desc">Contrato de intermedia&ccedil;&atilde;o imobili&aacute;ria &mdash; {$appName}</span>
      </td>
      <td class="ph-meta-cell">
        <div class="ph-protocol">N&ordm; AVE-{$submId}</div>
        <div class="ph-date">Emitido em {$submDate}</div>
        <div>{$excBadge}</div>
      </td>
    </tr></table>
  </div>
</div>
<div class="ph-accent"></div>

<div class="info-strip">
  <table><tr>
    <td><span class="is-lbl">Prazo de Exclusividade</span><span class="is-val">{$prazo} dias</span></td>
    <td><span class="is-lbl">Comiss&atilde;o</span><span class="is-val">{$comissao}%</span></td>
    <td class="text-right"><span class="is-lbl">Valor M&iacute;nimo de Venda</span><span class="is-val" style="color:#166534;">R$ {$valorMin}</span></td>
  </tr></table>
</div>

<div class="page-body">

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128100; &nbsp;Dados do Contratante</span></div>
    <table class="ft">
      <tr>
        <td colspan="2"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Nascimento</span><span class="fv">{$dataNasc}</span></td>
        <td><span class="fl">RG n&ordm;</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Expedidor</span><span class="fv">{$orgaoExp}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CPF n&ordm;</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Naturalidade</span><span class="fv">{$naturalidade}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">CNPJ n&ordm;</span><span class="fv">{$cnpj}</span></td>
        <td colspan="2"><span class="fl">Nome de Fantasia</span><span class="fv">{$nomeFant}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
        <td><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular / WhatsApp</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio e leg&iacute;timo possuidor do im&oacute;vel abaixo relacionado, contrata a <strong>{$appName}</strong>, inscrita no CRECI n&ordm; 218 PJ, para promover de forma <strong>EXCLUSIVA</strong> a <strong>VENDA</strong> do seu im&oacute;vel abaixo descrito, pelo prazo m&iacute;nimo de <strong>({$prazo}) dias</strong>, prorrog&aacute;vel automaticamente por per&iacute;odo igual e sucessivo, at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e condi&ccedil;&otilde;es estipuladas nesta autoriza&ccedil;&atilde;o de <strong>VENDA</strong>.
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#127968; &nbsp;Dados do Im&oacute;vel</span></div>
    <div class="card-sub"><strong>Tipo:</strong> {$tipoImovel} &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Situa&ccedil;&atilde;o:</strong> {$situacaoImovel}</div>
    <table class="ft">
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o</span><span class="fv">{$endImovel}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfImovel}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepImovel}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Ponto de Refer&ecirc;ncia</span><span class="fv">{$pontoRef}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">N&ordm; Registro do Im&oacute;vel</span><span class="fv">{$registroImovel}</span></td>
        <td colspan="2"><span class="fl">Matr&iacute;cula IPTU</span><span class="fv">{$matriculaIptu}</span></td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128203; &nbsp;Descri&ccedil;&atilde;o do Im&oacute;vel</span></div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea m&sup2;</span><span class="fv">{$areaPriv}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
        <td><span class="fl">Lazer?</span><span class="fv">{$lazer}</span></td>
        <td colspan="2"><span class="fl">Garagem Coberta?</span><span class="fv">{$garagemCob}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es</span><span class="fv" style="min-height:16px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128176; &nbsp;Condi&ccedil;&otilde;es de Venda</span></div>
    <table class="ft">
      <tr>
        <td style="width:30%"><span class="fl">Valor M&iacute;nimo R$</span><span class="fv-money">R$ {$valorMin}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$valorMinExtenso}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">Observa&ccedil;&otilde;es do Pre&ccedil;o</span><span class="fv" style="min-height:13px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Condom&iacute;nio R$</span><span class="fv">{$valorCondo}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$condoExtenso}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Formas de Pagamento</span><span class="fv">{$formasPag}</span></td>
        <td><span class="fl">Comiss&atilde;o</span><span class="fv">{$comissao}%</span></td>
      </tr>
    </table>
  </div>

  <div class="clause"><span class="cl">a)</span>&nbsp;Sobre o valor da <strong>VENDA</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%, pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.</div>
  <div class="clause"><span class="cl">b)</span>&nbsp;Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente o im&oacute;vel de sua propriedade acima descrito, fotografar o im&oacute;vel e suas depend&ecirc;ncias internas fazendo se publicar as fotos nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que desejar, inclusive na internet, afixar placas, faixas ou letreiros no im&oacute;vel, realizar visita&ccedil;&otilde;es e demonstra&ccedil;&otilde;es aos interessados.</div>
  <div class="clause"><span class="cl">c)</span>&nbsp;O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>VENDA</strong>, comprometendo-se em apresentar &agrave;s suas custas a documenta&ccedil;&atilde;o exigida em transa&ccedil;&otilde;es de VENDA, t&atilde;o logo que solicitado.</div>
  <p class="agree-text">E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a de duas testemunhas, ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.</p>
  <p class="date-line">Aracaju, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.</p>

  <div class="sig-section">
    <table class="sig-table">
      <tr>
        <td><div class="sline">{$nomeContratante}</div><div class="stitle">Contratante</div></td>
        <td><div class="sline">&nbsp;</div><div class="stitle">Contratante</div><div class="ssub">C&ocirc;njuge</div></td>
        <td><div class="sline">&nbsp;</div><div class="stitle">{$appName}</div><div class="ssub">Contratada</div></td>
        <td><div class="sline">{$nomeCorretor}</div><div class="stitle">Corretor(a)</div><div class="ssub">Credenciado</div></td>
      </tr>
    </table>
  </div>
  <p class="test-label">Testemunhas:</p>
  <table class="test-table">
    <tr>
      <td><div class="sline">{$test1Nome}</div><div class="ssub">CPF: {$test1Cpf}</div></td>
      <td><div class="sline">{$test2Nome}</div><div class="ssub">CPF: {$test2Cpf}</div></td>
    </tr>
  </table>

  {$docsHtml}

</div>

<div class="page-footer">
  <table><tr>
    <td><span class="pf-brand">{$appName}</span><span class="pf-sep">|</span>Av. Hermes Fontes, n&ordm; 1524, Luzia &mdash; Aracaju/SE<span class="pf-sep">|</span>(79) 3304-0000 / 99691-0000</td>
    <td class="pf-right">Protocolo: <strong>AVE-{$submId}</strong><span class="pf-sep">|</span>{$submDate}</td>
  </tr></table>
</div>

</body>
</html>
HTML;
}

// ============================================================
// TEMPLATE: AUTORIZAÇÃO DE LOCAÇÃO COM EXCLUSIVIDADE
// ============================================================

/**
 * Monta HTML do contrato de Autorização de Locação com Exclusividade.
 */
function buildLocacaoHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName  = e($settings['app_name'] ?? APP_NAME);
    $logoPath = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $submId   = (int) $submission['id'];
    $submDate = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));
    $anoAtual = date('Y');

    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    // ── Contratante
    $nomeContratante = $d('nome_razao_social');
    $sexo            = $d('sexo');
    $dataNasc        = $d('data_nascimento');
    $rg              = $d('rg');
    $orgaoExp        = $d('orgao_expedidor');
    $cpf             = $d('cpf');
    $naturalidade    = $d('naturalidade');
    $nacionalidade   = $d('nacionalidade');
    $cnpj            = $d('cnpj');
    $nomeFant        = $d('nome_fantasia');
    $estadoCivil     = $d('estado_civil');
    $conjuge         = $d('conjuge');
    $telefones       = $d('telefones');
    $endRes          = $d('endereco_residencial');
    $bairroRes       = $d('bairro_residencial');
    $cidUfRes        = $d('cidade_uf_residencial');
    $cepRes          = $d('cep_residencial');
    $telFixo         = $d('telefone_fixo');
    $celular         = $d('celular');
    $endCom          = $d('endereco_comercial');
    $bairroCom       = $d('bairro_comercial');
    $cidUfCom        = $d('cidade_uf_comercial');
    $cepCom          = $d('cep_comercial');
    $emails          = $d('emails');

    // ── Imóvel
    $tipoImovel      = $d('tipo_imovel');
    $endImovel       = $d('endereco_imovel');
    $bairroImovel    = $d('bairro_imovel');
    $cidUfImovel     = $d('cidade_uf_imovel');
    $cepImovel       = $d('cep_imovel');
    $pontoRef        = $d('ponto_referencia');
    $registroImovel  = $d('registro_imovel');
    $matriculaIptu   = $d('matricula_iptu');
    $energisaUc      = $d('energisa_uc');
    $deso            = $d('deso');
    $energisaUcNum   = $d('energisa_uc_num');
    $desoMatNum      = $d('deso_matricula_num');

    // ── Descrição
    $numDorm    = $d('num_dormitorios');
    $numSalas   = $d('num_salas');
    $numSuites  = $d('num_suites');
    $garagens   = $d('garagens');
    $areaPriv   = $d('area_privativa');
    $temVaranda = $d('tem_varanda');
    $temElevador= $d('tem_elevador');
    $lazer      = $d('lazer_completo');
    $obsDesc    = $d('obs_descricao');

    // ── Valor
    $valorLocacao        = $d('valor_locacao', '—');
    $valorLocacaoExtenso = $d('valor_locacao_extenso');
    $obsPreco            = $d('obs_preco');
    $valorCondo          = $d('valor_condominio', '—');
    $dataVencimento      = $d('data_vencimento');
    $valorIptuAnual      = $d('valor_iptu_anual', '—');
    $comissao            = $d('porcentagem_comissao', '—');
    $prazo               = $d('prazo_exclusividade', '—');
    $prazoMinimo         = $d('prazo_minimo', '___');

    // ── Assinaturas
    $nomeCorretor = $d('nome_corretor');
    $test1Nome    = $d('testemunha_1_nome');
    $test1Cpf     = $d('testemunha_1_cpf');
    $test2Nome    = $d('testemunha_2_nome');
    $test2Cpf     = $d('testemunha_2_cpf');

    // ── Badge exclusividade
    $exc = trim($data['com_exclusividade'] ?? '');
    $excBadge = ($exc === 'Sim')
        ? "<span class='badge badge-green'>&#10003; COM EXCLUSIVIDADE</span>"
        : (($exc !== '') ? "<span class='badge badge-gray'>SEM EXCLUSIVIDADE</span>" : '');

    // ── Logo
    $logoCell = buildLogoBannerCell($logoPath, $appName);

    // ── Documentos
    $docsHtml = buildDocsSection([
        'doc_cpf_rg'    => 'RG / CPF do Propriet&aacute;rio',
        'doc_iptu'      => 'Carn&ecirc; / IPTU',
        'doc_matricula' => 'Matr&iacute;cula do Im&oacute;vel',
        'doc_outros'    => 'Outros Documentos',
    ], $data);

    $css = sharedPdfCss();

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8">
<style>{$css}</style>
</head>
<body>

<div class="ph-wrap">
  <div class="ph-inner">
    <table><tr>
      <td class="ph-logo-cell"><div class="logo-frame">{$logoCell}</div></td>
      <td class="ph-title-cell">
        <div class="ph-badge">Documento Contratual</div>
        <div class="ph-h1">Autoriza&ccedil;&atilde;o de Loca&ccedil;&atilde;o</div>
        <span class="ph-sub">Com Exclusividade</span>
        <span class="ph-desc">Contrato de intermedia&ccedil;&atilde;o imobili&aacute;ria &mdash; {$appName}</span>
      </td>
      <td class="ph-meta-cell">
        <div class="ph-protocol">N&ordm; ALE-{$submId}</div>
        <div class="ph-date">Emitido em {$submDate}</div>
        <div>{$excBadge}</div>
      </td>
    </tr></table>
  </div>
</div>
<div class="ph-accent"></div>

<div class="info-strip">
  <table><tr>
    <td><span class="is-lbl">Prazo de Exclusividade</span><span class="is-val">{$prazo} dias</span></td>
    <td><span class="is-lbl">Prazo M&iacute;nimo Contrato</span><span class="is-val">{$prazoMinimo} dias</span></td>
    <td><span class="is-lbl">Comiss&atilde;o</span><span class="is-val">{$comissao}%</span></td>
    <td class="text-right"><span class="is-lbl">Valor de Loca&ccedil;&atilde;o</span><span class="is-val" style="color:#166534;">R$ {$valorLocacao}</span></td>
  </tr></table>
</div>

<div class="page-body">

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128100; &nbsp;Dados do Contratante</span></div>
    <table class="ft">
      <tr>
        <td colspan="2"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Nascimento</span><span class="fv">{$dataNasc}</span></td>
        <td><span class="fl">RG n&ordm;</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Expedidor</span><span class="fv">{$orgaoExp}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CPF n&ordm;</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Naturalidade</span><span class="fv">{$naturalidade}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">CNPJ n&ordm;</span><span class="fv">{$cnpj}</span></td>
        <td colspan="2"><span class="fl">Nome de Fantasia</span><span class="fv">{$nomeFant}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
        <td><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular / WhatsApp</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio(a) e leg&iacute;timo(a) possuidor(a) do im&oacute;vel abaixo relacionado, contrata a <strong>{$appName}</strong>, inscrita no CRECI n&ordm; 218 PJ, para promover, de forma <strong>EXCLUSIVA</strong>, a <strong>LOCA&Ccedil;&Atilde;O</strong> do seu im&oacute;vel acima descrito, pelo prazo m&iacute;nimo de <strong>({$prazoMinimo}) dias</strong>, prorrog&aacute;vel automaticamente por per&iacute;odo igual e sucessivo, at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e pelas condi&ccedil;&otilde;es estipuladas nesta autoriza&ccedil;&atilde;o de <strong>LOCA&Ccedil;&Atilde;O</strong>.
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#127968; &nbsp;Dados do Im&oacute;vel</span></div>
    <div class="card-sub"><strong>Tipo:</strong> {$tipoImovel}</div>
    <table class="ft">
      <tr>
        <td colspan="4"><span class="fl">Endere&ccedil;o Completo</span><span class="fv">{$endImovel}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfImovel}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepImovel}</span></td>
        <td><span class="fl">Ponto de Refer&ecirc;ncia</span><span class="fv">{$pontoRef}</span></td>
      </tr>
      <tr>
        <td><span class="fl">N&ordm; Registro</span><span class="fv">{$registroImovel}</span></td>
        <td><span class="fl">Matr&iacute;cula IPTU</span><span class="fv">{$matriculaIptu}</span></td>
        <td><span class="fl">Energisa / UC</span><span class="fv">{$energisaUc} &mdash; {$energisaUcNum}</span></td>
        <td><span class="fl">Igu&aacute;</span><span class="fv">{$deso} &mdash; {$desoMatNum}</span></td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128203; &nbsp;Descri&ccedil;&atilde;o do Im&oacute;vel</span></div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea m&sup2;</span><span class="fv">{$areaPriv}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
        <td colspan="3"><span class="fl">Lazer Completo?</span><span class="fv">{$lazer}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es</span><span class="fv" style="min-height:14px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><span class="ch-title">&#128176; &nbsp;Valor da Loca&ccedil;&atilde;o</span></div>
    <table class="ft">
      <tr>
        <td style="width:30%"><span class="fl">Valor Pretendido R$</span><span class="fv-money">R$ {$valorLocacao}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$valorLocacaoExtenso}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">Observa&ccedil;&otilde;es do Pre&ccedil;o</span><span class="fv" style="min-height:13px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Condom&iacute;nio R$</span><span class="fv">{$valorCondo}</span></td>
        <td><span class="fl">Data de Vencimento</span><span class="fv">{$dataVencimento}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">IPTU Anual R$</span><span class="fv">{$valorIptuAnual}</span></td>
        <td><span class="fl">Comiss&atilde;o</span><span class="fv">{$comissao}%</span></td>
      </tr>
    </table>
  </div>

  <div class="clause"><span class="cl">a)</span>&nbsp;Sobre o valor da <strong>LOCA&Ccedil;&Atilde;O</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%, pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.</div>
  <div class="clause"><span class="cl">b)</span>&nbsp;Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente o im&oacute;vel de sua propriedade acima descrito, fotografar o im&oacute;vel e suas depend&ecirc;ncias internas fazendo se publicar as fotos nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que desejar, inclusive na internet, afixar placas, faixas ou letreiros no im&oacute;vel, realizar visita&ccedil;&otilde;es e demonstra&ccedil;&otilde;es aos interessados.</div>
  <div class="clause"><span class="cl">c)</span>&nbsp;O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>LOCA&Ccedil;&Atilde;O</strong>, comprometendo-se em apresentar &agrave;s suas custas a documenta&ccedil;&atilde;o exigida em transa&ccedil;&otilde;es de LOCA&Ccedil;&Atilde;O, t&atilde;o logo que solicitado.</div>
  <p class="agree-text">E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a de duas testemunhas, ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.</p>
  <p class="date-line">Aracaju, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.</p>

  <div class="sig-section">
    <table class="sig-table">
      <tr>
        <td><div class="sline">{$nomeContratante}</div><div class="stitle">Contratante</div></td>
        <td><div class="sline">&nbsp;</div><div class="stitle">Contratante</div><div class="ssub">C&ocirc;njuge</div></td>
        <td><div class="sline">&nbsp;</div><div class="stitle">{$appName}</div><div class="ssub">Contratada</div></td>
        <td><div class="sline">{$nomeCorretor}</div><div class="stitle">Corretor(a)</div><div class="ssub">Credenciado</div></td>
      </tr>
    </table>
  </div>
  <p class="test-label">Testemunhas:</p>
  <table class="test-table">
    <tr>
      <td><div class="sline">{$test1Nome}</div><div class="ssub">CPF: {$test1Cpf}</div></td>
      <td><div class="sline">{$test2Nome}</div><div class="ssub">CPF: {$test2Cpf}</div></td>
    </tr>
  </table>

  {$docsHtml}

</div>

<div class="page-footer">
  <table><tr>
    <td><span class="pf-brand">{$appName}</span><span class="pf-sep">|</span>Av. Hermes Fontes, n&ordm; 1524, Luzia &mdash; Aracaju/SE<span class="pf-sep">|</span>(79) 3304-0000 / 99691-0000</td>
    <td class="pf-right">Protocolo: <strong>ALE-{$submId}</strong><span class="pf-sep">|</span>{$submDate}</td>
  </tr></table>
</div>

</body>
</html>
HTML;
}

// ============================================================
// TEMPLATE: PROPOSTA DE LOCAÇÃO
// ============================================================

function buildPropostaLocacaoHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName  = e($settings['app_name'] ?? APP_NAME);
    $logoPath = !empty($settings['logo_path']) ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path'] : '';
    $submId   = (int) $submission['id'];
    $submDate = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));

    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    $codigoImovel     = $d('codigo_imovel');
    $prazoMeses       = $d('prazo_meses');
    $valorRs          = $d('valor_rs', '—');
    $destinacao       = $d('destinacao');
    $dataVencimento   = $d('data_vencimento');
    $tipoFianca       = $d('tipo_fianca');

    $nome             = $d('nome');
    $nascimento       = $d('nascimento');
    $rg               = $d('rg');
    $exp              = $d('exp');
    $cpf              = $d('cpf');
    $nacionalidade    = $d('nacionalidade');
    $estadoCivil      = $d('estado_civil');
    $conjuge          = $d('conjuge');
    $endRes           = $d('endereco_residencial');
    $bairro           = $d('bairro');
    $cidadeUf         = $d('cidade_uf');
    $cep              = $d('cep');
    $whatsapp         = $d('whatsapp');
    $resFixo          = $d('residencial_fixo');
    $celular          = $d('celular');
    $email            = $d('email_contato');
    $tipoResidencia   = $d('tipo_residencia');
    $valorAluguel     = $d('valor_aluguel');
    $tempoReside      = $d('tempo_reside_anos');
    $numDep           = $d('num_dependentes');
    $criaAnimal       = $d('cria_animal');
    $empresa          = $d('empresa_trabalha');
    $cargo            = $d('cargo_funcao');
    $endCom           = $d('endereco_comercial');
    $bairroCom        = $d('bairro_comercial');
    $cidadeUfCom      = $d('cidade_uf_comercial');
    $cepCom           = $d('cep_comercial');
    $telFixoCom       = $d('telefone_fixo_comercial');
    $celularCom       = $d('celular_comercial');
    $emailCom         = $d('email_comercial');
    $tempoTrabalha    = $d('tempo_trabalha');
    $rendaMensal      = $d('renda_mensal', '—');
    $ref1Nome         = $d('ref1_nome');
    $ref1Relacao      = $d('ref1_relacao');
    $ref1Tel          = $d('ref1_telefone');
    $ref2Nome         = $d('ref2_nome');
    $ref2Relacao      = $d('ref2_relacao');
    $ref2Tel          = $d('ref2_telefone');
    $observacoes      = $d('observacoes');

    $docAnexo = trim($data['doc_anexo'] ?? '');
    $docsHtml = '';
    if ($docAnexo !== '') {
        $files = array_filter(array_map('trim', explode(',', $docAnexo)));
        $rows  = '';
        foreach ($files as $f) {
            $fname = e(basename($f));
            $furl  = e(APP_URL . '/uploads/' . ltrim($f, '/'));
            $rows .= "<tr><td class='docs-td-lbl'><span class='docs-lbl'>Documento Anexado</span></td>"
                   . "<td><span class='docs-file'>&#128206; <a href='{$furl}'>{$fname}</a></span></td></tr>";
        }
        $docsHtml = "<div class='section' style='margin-top:9px;'>"
                  . "<div class='sec-head'>Documentos Anexados</div>"
                  . "<table class='docs-table'>{$rows}</table></div>";
    }

    $logoCell = buildLogoBannerCell($logoPath, $appName);
    $css      = sharedPdfCss();

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><style>{$css}</style></head>
<body>

<!-- ═══════ HEADER PREMIUM ═══════ -->
<div class="ph-wrap">
  <div class="ph-inner">
    <table><tr>
      <td class="ph-logo-cell"><div class="logo-frame">{$logoCell}</div></td>
      <td class="ph-title-cell">
        <div class="ph-badge">Formul&aacute;rio Oficial</div>
        <div class="ph-h1">Proposta de Loca&ccedil;&atilde;o</div>
        <span class="ph-desc">Solicita&ccedil;&atilde;o de loca&ccedil;&atilde;o de im&oacute;vel &mdash; {$appName}</span>
      </td>
      <td class="ph-meta-cell">
        <div class="ph-protocol">N&ordm; PL-{$submId}</div>
        <div class="ph-date">Emitido em {$submDate}</div>
        <div class="ph-status">Em An&aacute;lise</div>
      </td>
    </tr></table>
  </div>
</div>
<div class="ph-accent"></div>

<!-- ═══════ INFO STRIP ═══════ -->
<div class="info-strip">
  <table><tr>
    <td>
      <span class="is-lbl">Destina&ccedil;&atilde;o</span>
      <span class="is-val">{$destinacao}</span>
    </td>
    <td>
      <span class="is-lbl">Tipo de Fian&ccedil;a</span>
      <span class="is-val">{$tipoFianca}</span>
    </td>
    <td>
      <span class="is-lbl">Prazo Contratado</span>
      <span class="is-val">{$prazoMeses} meses</span>
    </td>
    <td class="text-right">
      <span class="is-lbl">Valor Pretendido</span>
      <span class="is-val" style="color:#166534;">R$ {$valorRs}</span>
    </td>
  </tr></table>
</div>

<!-- ═══════ BODY ═══════ -->
<div class="page-body">

  <!-- IMÓVEL DESEJADO -->
  <div class="card">
    <div class="card-head"><span class="ch-title">&#127968; &nbsp;Im&oacute;vel Desejado</span></div>
    <table class="ft">
      <tr>
        <td style="width:20%"><span class="fl">C&oacute;digo n&ordm;</span><span class="fv">{$codigoImovel}</span></td>
        <td style="width:20%"><span class="fl">Prazo</span><span class="fv">{$prazoMeses} meses</span></td>
        <td style="width:25%"><span class="fl">Valor Mensal R$</span><span class="fv-money">R$ {$valorRs}</span></td>
        <td style="width:20%"><span class="fl">Vencimento</span><span class="fv">Dia {$dataVencimento}</span></td>
        <td><span class="fl">Destina&ccedil;&atilde;o</span><span class="fv">{$destinacao}</span></td>
      </tr>
    </table>
  </div>

  <!-- PRETENDENTE A LOCATÁRIO -->
  <div class="card">
    <div class="card-head"><span class="ch-title">&#128100; &nbsp;Pretendente a Locat&aacute;rio</span></div>
    <table class="ft">
      <tr>
        <td colspan="3"><span class="fl">Nome Completo</span><span class="fv">{$nome}</span></td>
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$nascimento}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">RG</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">Expedidor</span><span class="fv">{$exp}</span></td>
        <td><span class="fl">CPF</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
        <td colspan="3"><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="4"><span class="fl">Endere&ccedil;o Residencial Atual</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Bairro</span><span class="fv">{$bairro}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidadeUf}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cep}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">WhatsApp</span><span class="fv">{$whatsapp}</span></td>
        <td><span class="fl">Residencial Fixo</span><span class="fv">{$resFixo}</span></td>
        <td><span class="fl">Celular</span><span class="fv">{$celular}</span></td>
        <td><span class="fl">E-mail</span><span class="fv">{$email}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Tipo de Resid&ecirc;ncia</span><span class="fv">{$tipoResidencia}</span></td>
        <td><span class="fl">Aluguel Atual R$</span><span class="fv">{$valorAluguel}</span></td>
        <td><span class="fl">Tempo que Reside</span><span class="fv">{$tempoReside} ano(s)</span></td>
        <td><span class="fl">N&ordm; Dependentes</span><span class="fv">{$numDep}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="4"><span class="fl">Cria Animal Dom&eacute;stico?</span><span class="fv">{$criaAnimal}</span></td>
      </tr>
    </table>
  </div>

  <!-- INFORMAÇÕES PROFISSIONAIS -->
  <div class="card">
    <div class="card-head"><span class="ch-title">&#128188; &nbsp;Informa&ccedil;&otilde;es Profissionais</span></div>
    <table class="ft">
      <tr>
        <td colspan="2"><span class="fl">Empresa onde Trabalha</span><span class="fv">{$empresa}</span></td>
        <td colspan="2"><span class="fl">Cargo / Fun&ccedil;&atilde;o</span><span class="fv">{$cargo}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="4"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Bairro Comercial</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidadeUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Tel. Fixo Comercial</span><span class="fv">{$telFixoCom}</span></td>
        <td><span class="fl">Celular Comercial</span><span class="fv">{$celularCom}</span></td>
        <td colspan="2"><span class="fl">E-mail Comercial</span><span class="fv">{$emailCom}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Tempo de Empresa</span><span class="fv">{$tempoTrabalha}</span></td>
        <td colspan="2">
          <span class="fl">Renda Mensal</span>
          <span class="fv-money">R$ {$rendaMensal}</span>
        </td>
      </tr>
    </table>
  </div>

  <!-- REFERÊNCIAS PESSOAIS -->
  <div class="card">
    <div class="card-head"><span class="ch-title">&#128101; &nbsp;Refer&ecirc;ncias Pessoais</span></div>
    <table class="ft">
      <tr>
        <td style="width:8%;background:#f8fafc;padding:6px 10px;text-align:center;vertical-align:middle;">
          <span style="color:#94a3b8;font-size:7px;font-weight:bold;">01</span>
        </td>
        <td colspan="2"><span class="fl">Nome</span><span class="fv">{$ref1Nome}</span></td>
        <td><span class="fl">Rela&ccedil;&atilde;o</span><span class="fv">{$ref1Relacao}</span></td>
        <td><span class="fl">Telefone</span><span class="fv">{$ref1Tel}</span></td>
      </tr>
      <tr class="alt">
        <td style="width:8%;background:#f0f7ff;padding:6px 10px;text-align:center;vertical-align:middle;">
          <span style="color:#94a3b8;font-size:7px;font-weight:bold;">02</span>
        </td>
        <td colspan="2"><span class="fl">Nome</span><span class="fv">{$ref2Nome}</span></td>
        <td><span class="fl">Rela&ccedil;&atilde;o</span><span class="fv">{$ref2Relacao}</span></td>
        <td><span class="fl">Telefone</span><span class="fv">{$ref2Tel}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es Gerais</span><span class="fv" style="min-height:18px;">{$observacoes}</span></td>
      </tr>
    </table>
  </div>

  <!-- NOTA LEGAL -->
  <div class="legal">
    <strong>AVISO LEGAL:</strong> A presente proposta &eacute; apenas de interesse de participa&ccedil;&atilde;o na loca&ccedil;&atilde;o, n&atilde;o tendo valor contratual. Com seja aprovada, os dados nela contidos ser&atilde;o utilizados para confec&ccedil;&atilde;o do contrato de loca&ccedil;&atilde;o, onde estar&atilde;o estabelecidas as cl&aacute;usulas contratuais.
  </div>

  {$docsHtml}

</div><!-- /page-body -->

<!-- ═══════ RODAPÉ ═══════ -->
<div class="page-footer">
  <table><tr>
    <td>
      <span class="pf-brand">{$appName}</span>
      <span class="pf-sep">|</span>
      Av. Hermes Fontes, n&ordm; 1524, Luzia &mdash; Aracaju/SE
      <span class="pf-sep">|</span>
      (79) 3304-0000 / 99691-0000
    </td>
    <td class="pf-right">
      Protocolo: <strong>PL-{$submId}</strong>
      <span class="pf-sep">|</span>
      {$submDate}
    </td>
  </tr></table>
</div>

</body>
</html>
HTML;
}

// ============================================================
// TEMPLATE: PROPOSTA PARA FIANÇA DE LOCAÇÃO
// ============================================================

function buildPropostaFiadorHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName  = e($settings['app_name'] ?? APP_NAME);
    $logoPath = !empty($settings['logo_path']) ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path'] : '';
    $submId   = (int) $submission['id'];
    $submDate = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));

    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    // Imóvel
    $imovelSituado  = $d('imovel_situado');
    $codigo         = $d('codigo');
    $bairroImovel   = $d('bairro_imovel');
    $valorMensal    = $d('valor_mensal', '—');
    $rendaFamiliar  = $d('renda_familiar', '—');
    $destinacao     = $d('destinacao');

    // 1º Proponente
    $p1Nome         = $d('p1_nome');
    $p1Rg           = $d('p1_rg');
    $p1Orgao        = $d('p1_orgao_emissor');
    $p1Nasc         = $d('p1_nascimento');
    $p1Cpf          = $d('p1_cpf_cnpj');
    $p1Profissao    = $d('p1_profissao');
    $p1Empresa      = $d('p1_empresa');
    $p1Estado       = $d('p1_estado_civil');
    $p1Conjuge      = $d('p1_conjuge');
    $p1CnjNasc      = $d('p1_conjuge_nascimento');
    $p1CnjRg        = $d('p1_conjuge_rg');
    $p1CnjOrgao     = $d('p1_conjuge_orgao');
    $p1CnjCpf       = $d('p1_conjuge_cpf');
    $p1Endereco     = $d('p1_endereco');
    $p1Compl        = $d('p1_complemento');
    $p1Bairro       = $d('p1_bairro');
    $p1Cidade       = $d('p1_cidade');
    $p1Cep          = $d('p1_cep');
    $p1Uf           = $d('p1_uf');
    $p1Tel1         = $d('p1_telefone1');
    $p1Tel2         = $d('p1_telefone2');
    $p1Tel3         = $d('p1_telefone3');
    $p1Email1       = $d('p1_email1');
    $p1Email2       = $d('p1_email2');

    // 2º Proponente
    $p2Nome         = $d('p2_nome');
    $p2Rg           = $d('p2_rg');
    $p2Orgao        = $d('p2_orgao_emissor');
    $p2Nasc         = $d('p2_nascimento');
    $p2Cpf          = $d('p2_cpf_cnpj');
    $p2Profissao    = $d('p2_profissao');
    $p2Empresa      = $d('p2_empresa');
    $p2Estado       = $d('p2_estado_civil');
    $p2Conjuge      = $d('p2_conjuge');
    $p2CnjNasc      = $d('p2_conjuge_nascimento');
    $p2CnjRg        = $d('p2_conjuge_rg');
    $p2CnjOrgao     = $d('p2_conjuge_orgao');
    $p2CnjCpf       = $d('p2_conjuge_cpf');
    $p2Endereco     = $d('p2_endereco');
    $p2Compl        = $d('p2_complemento');
    $p2Bairro       = $d('p2_bairro');
    $p2Cidade       = $d('p2_cidade');
    $p2Cep          = $d('p2_cep');
    $p2Uf           = $d('p2_uf');
    $p2Tel1         = $d('p2_telefone1');
    $p2Tel2         = $d('p2_telefone2');
    $p2Tel3         = $d('p2_telefone3');
    $p2Email1       = $d('p2_email1');
    $p2Email2       = $d('p2_email2');

    // Informações complementares
    $info1Nome      = $d('info1_nome');
    $info1Contatos  = $d('info1_contatos');
    $info2Nome      = $d('info2_nome');
    $info2Contatos  = $d('info2_contatos');

    $docAnexo = trim($data['doc_anexo'] ?? '');
    $docsHtml = '';
    if ($docAnexo !== '') {
        $files = array_filter(array_map('trim', explode(',', $docAnexo)));
        $rows  = '';
        foreach ($files as $f) {
            $fname = e(basename($f));
            $furl  = e(APP_URL . '/uploads/' . ltrim($f, '/'));
            $rows .= "<tr><td class='docs-td-lbl'><span class='docs-lbl'>Documento Anexado</span></td>"
                   . "<td><span class='docs-file'>&#128206; <a href='{$furl}'>{$fname}</a></span></td></tr>";
        }
        $docsHtml = "<div class='section' style='margin-top:9px;'>"
                  . "<div class='sec-head'>Documentos Anexados</div>"
                  . "<table class='docs-table'>{$rows}</table></div>";
    }

    $logoCell = buildLogoBannerCell($logoPath, $appName);
    $css      = sharedPdfCss();

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><style>{$css}</style></head>
<body>

<!-- ═══════ HEADER PREMIUM ═══════ -->
<div class="ph-wrap">
  <div class="ph-inner">
    <table><tr>
      <td class="ph-logo-cell"><div class="logo-frame">{$logoCell}</div></td>
      <td class="ph-title-cell">
        <div class="ph-badge">Formul&aacute;rio Oficial</div>
        <div class="ph-h1">Proposta para Fian&ccedil;a</div>
        <span class="ph-sub">de Loca&ccedil;&atilde;o</span>
        <span class="ph-desc">Dados do(s) fiador(es) para proposta de loca&ccedil;&atilde;o &mdash; {$appName}</span>
      </td>
      <td class="ph-meta-cell">
        <div class="ph-protocol">N&ordm; PF-{$submId}</div>
        <div class="ph-date">Emitido em {$submDate}</div>
        <div class="ph-status">Em An&aacute;lise</div>
      </td>
    </tr></table>
  </div>
</div>
<div class="ph-accent"></div>

<!-- ═══════ INFO STRIP ═══════ -->
<div class="info-strip">
  <table><tr>
    <td>
      <span class="is-lbl">Im&oacute;vel</span>
      <span class="is-val">{$imovelSituado}</span>
    </td>
    <td>
      <span class="is-lbl">C&oacute;digo</span>
      <span class="is-val">{$codigo}</span>
    </td>
    <td>
      <span class="is-lbl">Destina&ccedil;&atilde;o</span>
      <span class="is-val">{$destinacao}</span>
    </td>
    <td class="text-right">
      <span class="is-lbl">Valor Mensal</span>
      <span class="is-val" style="color:#166534;">R$ {$valorMensal}</span>
    </td>
  </tr></table>
</div>

<!-- ═══════ BODY ═══════ -->
<div class="page-body">

  <!-- IMÓVEL -->
  <div class="card">
    <div class="card-head"><span class="ch-title">&#127968; &nbsp;Im&oacute;vel</span></div>
    <table class="ft">
      <tr>
        <td colspan="4"><span class="fl">Endere&ccedil;o do Im&oacute;vel</span><span class="fv">{$imovelSituado}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">C&oacute;digo n&ordm;</span><span class="fv">{$codigo}</span></td>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Valor Mensal R$</span><span class="fv-money">R$ {$valorMensal}</span></td>
        <td><span class="fl">Destina&ccedil;&atilde;o</span><span class="fv">{$destinacao}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Renda Familiar R$</span><span class="fv-money">R$ {$rendaFamiliar}</span></td>
        <td colspan="2"></td>
      </tr>
    </table>
  </div>

  <!-- 1º PROPONENTE -->
  <div class="card">
    <div class="card-head">
      <table style="width:100%;border-collapse:collapse;"><tr>
        <td><span class="ch-title">&#128100; &nbsp;1&ordm; Proponente (Fiador Principal)</span></td>
        <td style="text-align:right;"><span style="background:#27ae60;color:#fff;font-size:6.5px;font-weight:bold;padding:2px 8px;border-radius:8px;">PRINCIPAL</span></td>
      </tr></table>
    </div>
    <table class="ft">
      <tr>
        <td colspan="4"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$p1Nome}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">RG</span><span class="fv">{$p1Rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Emissor</span><span class="fv">{$p1Orgao}</span></td>
        <td><span class="fl">Nascimento</span><span class="fv">{$p1Nasc}</span></td>
        <td><span class="fl">CPF / CNPJ</span><span class="fv">{$p1Cpf}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Profiss&atilde;o / Atividade</span><span class="fv">{$p1Profissao}</span></td>
        <td colspan="2"><span class="fl">Empresa onde Trabalha</span><span class="fv">{$p1Empresa}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="4"><span class="fl">Estado Civil</span><span class="fv">{$p1Estado}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$p1Conjuge}</span></td>
        <td><span class="fl">Nasc. C&ocirc;njuge</span><span class="fv">{$p1CnjNasc}</span></td>
        <td><span class="fl">CPF C&ocirc;njuge</span><span class="fv">{$p1CnjCpf}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">RG C&ocirc;njuge</span><span class="fv">{$p1CnjRg}</span></td>
        <td colspan="3"><span class="fl">&Oacute;rg&atilde;o Emissor</span><span class="fv">{$p1CnjOrgao}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Atual</span><span class="fv">{$p1Endereco}</span></td>
        <td><span class="fl">Complemento</span><span class="fv">{$p1Compl}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$p1Bairro}</span></td>
        <td><span class="fl">Cidade</span><span class="fv">{$p1Cidade}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$p1Cep}</span></td>
        <td><span class="fl">UF</span><span class="fv">{$p1Uf}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone 1</span><span class="fv">{$p1Tel1}</span></td>
        <td><span class="fl">Telefone 2</span><span class="fv">{$p1Tel2}</span></td>
        <td><span class="fl">Telefone 3</span><span class="fv">{$p1Tel3}</span></td>
        <td></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">E-mail 1</span><span class="fv">{$p1Email1}</span></td>
        <td colspan="2"><span class="fl">E-mail 2</span><span class="fv">{$p1Email2}</span></td>
      </tr>
    </table>
    <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:5px 14px;">
      <table class="ft" style="border:none;">
        <tr>
          <td style="border:none;padding:4px 0;"><span class="fl">Informa&ccedil;&otilde;es Complementares &mdash; Nome</span><span class="fv">{$info1Nome}</span></td>
          <td style="border:none;padding:4px 0;"><span class="fl">Contatos</span><span class="fv">{$info1Contatos}</span></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- 2º PROPONENTE -->
  <div class="card">
    <div class="card-head">
      <table style="width:100%;border-collapse:collapse;"><tr>
        <td><span class="ch-title">&#128101; &nbsp;2&ordm; Proponente (Fiador Secund&aacute;rio)</span></td>
        <td style="text-align:right;"><span style="background:#1e40af;color:#fff;font-size:6.5px;font-weight:bold;padding:2px 8px;border-radius:8px;">SECUND&Aacute;RIO</span></td>
      </tr></table>
    </div>
    <table class="ft">
      <tr>
        <td colspan="4"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$p2Nome}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">RG</span><span class="fv">{$p2Rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Emissor</span><span class="fv">{$p2Orgao}</span></td>
        <td><span class="fl">Nascimento</span><span class="fv">{$p2Nasc}</span></td>
        <td><span class="fl">CPF / CNPJ</span><span class="fv">{$p2Cpf}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Profiss&atilde;o / Atividade</span><span class="fv">{$p2Profissao}</span></td>
        <td colspan="2"><span class="fl">Empresa onde Trabalha</span><span class="fv">{$p2Empresa}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="4"><span class="fl">Estado Civil</span><span class="fv">{$p2Estado}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$p2Conjuge}</span></td>
        <td><span class="fl">Nasc. C&ocirc;njuge</span><span class="fv">{$p2CnjNasc}</span></td>
        <td><span class="fl">CPF C&ocirc;njuge</span><span class="fv">{$p2CnjCpf}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">RG C&ocirc;njuge</span><span class="fv">{$p2CnjRg}</span></td>
        <td colspan="3"><span class="fl">&Oacute;rg&atilde;o Emissor</span><span class="fv">{$p2CnjOrgao}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Atual</span><span class="fv">{$p2Endereco}</span></td>
        <td><span class="fl">Complemento</span><span class="fv">{$p2Compl}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$p2Bairro}</span></td>
        <td><span class="fl">Cidade</span><span class="fv">{$p2Cidade}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$p2Cep}</span></td>
        <td><span class="fl">UF</span><span class="fv">{$p2Uf}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone 1</span><span class="fv">{$p2Tel1}</span></td>
        <td><span class="fl">Telefone 2</span><span class="fv">{$p2Tel2}</span></td>
        <td><span class="fl">Telefone 3</span><span class="fv">{$p2Tel3}</span></td>
        <td></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">E-mail 1</span><span class="fv">{$p2Email1}</span></td>
        <td colspan="2"><span class="fl">E-mail 2</span><span class="fv">{$p2Email2}</span></td>
      </tr>
    </table>
    <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:5px 14px;">
      <table class="ft" style="border:none;">
        <tr>
          <td style="border:none;padding:4px 0;"><span class="fl">Informa&ccedil;&otilde;es Complementares &mdash; Nome</span><span class="fv">{$info2Nome}</span></td>
          <td style="border:none;padding:4px 0;"><span class="fl">Contatos</span><span class="fv">{$info2Contatos}</span></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- NOTA LEGAL -->
  <div class="legal">
    <strong>AVISO LEGAL:</strong> A presente proposta &eacute; apenas de interesse de participa&ccedil;&atilde;o na loca&ccedil;&atilde;o como fiador, n&atilde;o tendo valor contratual. Com seja aprovada, os dados nela contidos ser&atilde;o utilizados para confec&ccedil;&atilde;o do contrato de loca&ccedil;&atilde;o, onde estar&atilde;o estabelecidas as cl&aacute;usulas contratuais.
  </div>

  {$docsHtml}

</div><!-- /page-body -->

<!-- ═══════ RODAPÉ ═══════ -->
<div class="page-footer">
  <table><tr>
    <td>
      <span class="pf-brand">{$appName}</span>
      <span class="pf-sep">|</span>
      Av. Hermes Fontes, n&ordm; 1524, Luzia &mdash; Aracaju/SE
      <span class="pf-sep">|</span>
      (79) 3304-0000 / 99691-0000
    </td>
    <td class="pf-right">
      Protocolo: <strong>PF-{$submId}</strong>
      <span class="pf-sep">|</span>
      {$submDate}
    </td>
  </tr></table>
</div>

</body>
</html>
HTML;
}

// ============================================================
// HELPERS INTERNOS
// ============================================================

/**
 * Monta a célula do logo no banner do PDF (base64).
 */
function buildLogoBannerCell(string $logoPath, string $appName): string
{
    if (!empty($logoPath) && is_file($logoPath)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoPath);
        if (in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
            $b64 = base64_encode(file_get_contents($logoPath));
            return "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:62px;max-width:118px;\">";
        }
    }
    $safe = e($appName);
    return "<div class=\"brand-box\"><span class=\"bn\">{$safe}</span><span class=\"bs\">Imobili&aacute;ria</span></div>";
}

/**
 * Monta HTML da seção de documentos anexados.
 */
function buildDocsSection(array $labels, array $data): string
{
    $rows = '';
    foreach ($labels as $key => $label) {
        $filePath = trim($data[$key] ?? '');
        if ($filePath === '') continue;
        $fileName = e(basename($filePath));
        $fileUrl  = e(APP_URL . '/uploads/' . ltrim($filePath, '/'));
        $rows .= "<tr>"
            . "<td class='docs-td-lbl'><span class='docs-lbl'>{$label}</span></td>"
            . "<td class='docs-td-val'><span class='docs-file'>&#128206; <a href='{$fileUrl}'>{$fileName}</a></span></td>"
            . "</tr>";
    }

    if ($rows === '') return '';

    return "<div class='card' style='margin-top:12px;'>"
        . "<div class='card-head'><span class='ch-title'>&#128206; &nbsp;Documentos Anexados</span></div>"
        . "<table class='docs-table'>{$rows}</table>"
        . "</div>";
}

// ============================================================
// TEMPLATE PADRÃO (fallback)
// ============================================================

function buildDefaultHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName  = e($settings['app_name'] ?? APP_NAME);
    $formName = e($form['title'] ?? 'Formulário');
    $submId   = (int) $submission['id'];
    $submDate = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));
    $logoPath = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $logoCell = buildLogoBannerCell($logoPath, $appName);
    $css      = sharedPdfCss();

    $rows = '';
    foreach ((array)$data as $key => $value) {
        $label = ucwords(str_replace(['_', '-'], ' ', $key));
        $val   = is_array($value) ? implode(', ', $value) : $value;
        if (strpos((string)$val, 'docs/') === 0) {
            $url   = e(APP_URL . '/uploads/' . $val);
            $fname = e(basename($val));
            $val   = "&#128206; <a href='{$url}'>{$fname}</a>";
        } else {
            $val = e((string)$val);
        }
        $rows .= "<tr><td class='docs-td-lbl'><span class='docs-lbl'>" . e($label) . "</span></td>"
               . "<td><span class='docs-file'>{$val}</span></td></tr>";
    }

    return <<<HTML
<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><style>{$css}</style></head>
<body>
<div class="header-ribbon">
  <table><tr>
    <td>Documento</td>
    <td class="hr-center">Preenchimento Eletr&ocirc;nico</td>
    <td class="hr-right"><strong>{$appName}</strong></td>
  </tr></table>
</div>
<div class="header-main">
  <table><tr>
    <td class="hm-logo"><div class="logo-box-wrap">{$logoCell}</div></td>
    <td class="hm-title">
      <div class="hm-kicker">Formul&aacute;rio Oficial</div>
      <div class="hm-h1">{$formName}</div>
    </td>
  </tr></table>
</div>
<div class="meta-strip">
  <table><tr>
    <td><span class="badge-num">N&ordm; {$submId}</span> &nbsp;&nbsp; &#128197;&nbsp;{$submDate}</td>
  </tr></table>
</div>
<div class="content">
  <div class="section">
    <div class="sec-head">Dados Submetidos</div>
    <table class="docs-table">{$rows}</table>
  </div>
</div>
<div class="footer"><strong>{$appName}</strong> &nbsp;|&nbsp; Gerado em: {$submDate}</div>
</body></html>
HTML;
}

// ============================================================
// UTILITÁRIO: LOGO EM BASE64 PARA PDF
// ============================================================

/**
 * Converte a imagem do logo para base64 para incluir no PDF.
 * DomPDF com isRemoteEnabled=false não carrega URLs externas,
 * então embedamos a imagem.
 *
 * @param string $logoAbsPath Caminho absoluto da imagem
 * @param string $altText
 * @return string HTML img ou string vazia
 */
function buildLogoImg(string $logoAbsPath, string $altText): string
{
    if (empty($logoAbsPath) || !is_file($logoAbsPath)) {
        return '';
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoAbsPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return '';
    }

    $b64 = base64_encode(file_get_contents($logoAbsPath));
    $alt = htmlspecialchars($altText, ENT_QUOTES, 'UTF-8');

    return "<img src=\"data:{$mime};base64,{$b64}\" alt=\"{$alt}\" style=\"max-height:55px;max-width:150px;\">";
}
