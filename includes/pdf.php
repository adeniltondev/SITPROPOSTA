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
    return <<<CSS
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',sans-serif; font-size:9px; color:#1e2d3a; background:#fff; line-height:1.4; }

/* ── HEADER RIBBON ── */
.header-ribbon { background:#08384d; width:100%; }
.header-ribbon table { width:100%; border-collapse:collapse; }
.header-ribbon td { padding:5px 14px; vertical-align:middle; color:rgba(255,255,255,.92); font-size:7px; font-weight:bold; text-transform:uppercase; letter-spacing:.4px; }
.header-ribbon .hr-center { text-align:center; }
.header-ribbon .hr-right { text-align:right; }
.header-ribbon strong { color:#fff; }

/* ── HEADER MAIN ── */
.header-main { background:#edf5fa; border-bottom:3px solid #0f6788; width:100%; }
.header-main table { width:100%; border-collapse:collapse; }
.header-main .hm-logo { width:148px; padding:14px 16px; vertical-align:middle; }
.header-main .hm-title { padding:14px 20px 14px 10px; vertical-align:middle; }
.hm-kicker { font-size:7px; font-weight:bold; text-transform:uppercase; letter-spacing:.75px; color:#0f607e; background:#d6eaf5; border:1px solid #b8d9ec; padding:2px 7px; display:inline-block; margin-bottom:6px; }
.hm-h1 { color:#163d4f; font-size:17px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px; line-height:1.15; margin:0; }
.hm-sub { color:#2f6880; font-size:9.5px; font-weight:bold; letter-spacing:1.6px; text-transform:uppercase; margin-top:3px; display:block; }
.hm-desc { color:#4a6978; font-size:7.5px; margin-top:5px; }
.hm-meta { color:#53798b; font-size:7px; font-weight:bold; text-transform:uppercase; letter-spacing:.4px; margin-top:5px; }

/* ── LOGO BOX ── */
.logo-box-wrap { background:#fff; border:1px solid #dbe8ef; padding:8px 10px; text-align:center; }
.logo-box-wrap img { max-height:62px; max-width:118px; }
.brand-box { text-align:center; }
.brand-box .bn { color:#12465d; font-size:14px; font-weight:bold; }
.brand-box .bs { color:#3a6474; font-size:7px; text-transform:uppercase; letter-spacing:.4px; display:block; margin-top:2px; }

/* ── META STRIP ── */
.meta-strip { background:#1a6e8e; padding:5px 16px; }
.meta-strip table { width:100%; border-collapse:collapse; }
.meta-strip td { vertical-align:middle; color:rgba(255,255,255,.9); font-size:7.5px; }
.meta-right { text-align:right; }
.badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:7.5px; font-weight:bold; }
.badge-green { background:#27ae60; color:#fff; }
.badge-gray  { background:rgba(255,255,255,.25); color:#fff; }
.badge-num   { background:rgba(255,255,255,.18); color:#fff; padding:2px 8px; border-radius:8px; font-size:7.5px; }

/* ── CONTENT ── */
.content { background:#fff; padding:13px 18px 0; }

/* ── SECTIONS ── */
.section { margin-bottom:9px; }
.sec-head { background:{$primary}; color:#fff; padding:4px 9px; font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.6px; }
.sec-sub  { background:#e8f3f7; border:1px solid #cde3ec; border-top:none; padding:3px 8px; font-size:8px; color:{$primary}; }

/* ── FIELD TABLE ── */
.ft { width:100%; border-collapse:collapse; }
.ft td { border:1px solid #cfe4ec; padding:3px 7px; background:#fff; }
.ft .alt { background:#f5fafc; }
.fl { color:#1a6e8e; font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.35px; display:block; margin-bottom:1px; }
.fv { color:#1e2d3a; font-size:8.5px; display:block; min-height:11px; }

/* ── LEGAL ── */
.legal { border-left:3px solid #1a6e8e; background:#f0f9fc; padding:8px 11px; font-size:7.5px; line-height:1.8; color:#2c3e50; text-align:justify; margin:7px 0; }

/* ── CLAUSES ── */
.clause { font-size:7.5px; line-height:1.8; color:#2c3e50; text-align:justify; margin-bottom:4px; }
.cl { font-weight:bold; color:#1a6e8e; }

/* ── AGREEMENTS ── */
.agree-text { font-size:7.5px; line-height:1.8; color:#2c3e50; text-align:justify; margin-top:5px; }

/* ── SIGNATURES ── */
.sig-table { width:100%; border-collapse:collapse; margin-top:14px; }
.sig-table td { width:25%; text-align:center; padding:22px 6px 4px; vertical-align:bottom; }
.sline { border-top:1.5px solid #1e2d3a; padding-top:3px; min-height:26px; font-size:7.5px; color:#1e2d3a; }
.stitle { font-size:7.5px; font-weight:bold; color:{$primary}; text-transform:uppercase; margin-top:2px; }
.ssub   { font-size:6.5px; color:#7f8c8d; font-style:italic; }

/* ── TESTEMUNHAS ── */
.test-label { font-size:7.5px; font-weight:bold; text-transform:uppercase; color:{$primary}; margin-top:10px; margin-bottom:0; }
.test-table { width:100%; border-collapse:collapse; margin-top:0; }
.test-table td { width:50%; text-align:center; padding:16px 8px 4px; vertical-align:bottom; }

/* ── DOCUMENTS ── */
.docs-table { width:100%; border-collapse:collapse; margin-top:0; }
.docs-table td { border:1px solid #cfe4ec; padding:4px 8px; }
.docs-td-lbl { background:#e8f3f7; width:36%; }
.docs-lbl { color:{$primary}; font-size:7px; font-weight:bold; text-transform:uppercase; display:block; }
.docs-file { font-size:8px; color:#1e2d3a; word-break:break-all; }
.docs-file a { color:#1a6e8e; text-decoration:underline; }

/* ── DATE LINE ── */
.date-line { font-size:8.5px; text-align:right; margin-top:8px; color:#1e2d3a; }

/* ── FOOTER ── */
.footer { background:{$primary}; color:rgba(255,255,255,.78); font-size:7px; text-align:center; padding:8px 18px; line-height:1.7; margin-top:12px; }
.footer a { color:rgba(255,255,255,.88); }

/* ── DIVIDER ── */
hr.div { border:none; border-top:1px solid #cfe4ec; margin:7px 0; }
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
<head><meta charset="UTF-8">
<style>{$css}</style>
</head>
<body>

<!-- ═══ HEADER RIBBON ═══ -->
<div class="header-ribbon">
  <table><tr>
    <td>Documento Contratual</td>
    <td class="hr-center">Preenchimento Eletr&ocirc;nico</td>
    <td class="hr-right"><strong>{$appName}</strong></td>
  </tr></table>
</div>

<!-- ═══ HEADER MAIN ═══ -->
<div class="header-main">
  <table><tr>
    <td class="hm-logo"><div class="logo-box-wrap">{$logoCell}</div></td>
    <td class="hm-title">
      <div class="hm-kicker">Formul&aacute;rio Oficial</div>
      <div class="hm-h1">Autoriza&ccedil;&atilde;o de Venda</div>
      <span class="hm-sub">Com Exclusividade</span>
      <div class="hm-desc">Contrato de intermedia&ccedil;&atilde;o imobili&aacute;ria</div>
      <div class="hm-meta">Validade jur&iacute;dica mediante preenchimento completo e aceita&ccedil;&atilde;o das cl&aacute;usulas</div>
    </td>
  </tr></table>
</div>

<!-- ═══ META STRIP ═══ -->
<div class="meta-strip">
  <table><tr>
    <td style="width:65%">
      <span class="badge-num">N&ordm; AVE-{$submId}</span>
      &nbsp;&nbsp;&#128197;&nbsp;{$submDate}
      &nbsp;&nbsp;&#9201;&nbsp;Prazo: <strong>{$prazo} dias</strong>
    </td>
    <td class="meta-right">{$excBadge}</td>
  </tr></table>
</div>

<div class="content">

  <!-- ═════ DADOS DO CONTRATANTE ═════ -->
  <div class="section">
    <div class="sec-head">Dados do Contratante</div>
    <table class="ft">
      <tr>
        <td style="width:60%"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$dataNasc}</span></td>
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
        <td colspan="3"><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular / WhatsApp</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ PARÁGRAFO LEGAL ═════ -->
  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio e leg&iacute;timo possuidor do im&oacute;vel abaixo relacionado, contrata a
    <strong>{$appName}</strong>, inscrita no Conselho Regional dos corretores de im&oacute;veis com o n&ordm; 218 PJ,
    para promover de forma <strong>EXCLUSIVA</strong> a <strong>VENDA</strong> do seu im&oacute;vel abaixo descrito,
    pelo prazo m&iacute;nimo de <strong>({$prazo}) dias</strong>, prorrog&aacute;vel automaticamente por per&iacute;odo
    igual e sucessivo, at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e
    condi&ccedil;&otilde;es estipuladas nesta autoriza&ccedil;&atilde;o de <strong>VENDA</strong>.
  </div>

  <!-- ═════ DADOS DO IMÓVEL ═════ -->
  <div class="section">
    <div class="sec-head">Dados do Im&oacute;vel</div>
    <div class="sec-sub"><strong>Tipo:</strong> {$tipoImovel} &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Situa&ccedil;&atilde;o:</strong> {$situacaoImovel}</div>
    <table class="ft" style="border-top:none;">
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
        <td><span class="fl">N&ordm; e Registro do Im&oacute;vel</span><span class="fv">{$registroImovel}</span></td>
        <td colspan="2"><span class="fl">Matr&iacute;cula de IPTU n&ordm;</span><span class="fv">{$matriculaIptu}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ DESCRIÇÃO ═════ -->
  <div class="section">
    <div class="sec-head">Descri&ccedil;&atilde;o do Im&oacute;vel</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea m&sup2;</span><span class="fv">{$areaPriv}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td colspan="3"><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Lazer Completo?</span><span class="fv">{$lazer}</span></td>
        <td colspan="3"><span class="fl">Garagem Coberta?</span><span class="fv">{$garagemCob}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es</span><span class="fv" style="min-height:16px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ CONDIÇÕES ═════ -->
  <div class="section">
    <div class="sec-head">Condi&ccedil;&otilde;es Pretendidas</div>
    <table class="ft">
      <tr>
        <td style="width:28%"><span class="fl">Valor M&iacute;nimo de Venda R$</span><span class="fv" style="font-size:10px;font-weight:bold;color:#1a6e8e;">{$valorMin}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$valorMinExtenso}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">Observa&ccedil;&otilde;es do Pre&ccedil;o</span><span class="fv" style="min-height:13px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Valor do Condom&iacute;nio R$</span><span class="fv">{$valorCondo}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$condoExtenso}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Formas de Pagamento</span><span class="fv">{$formasPag}</span></td>
        <td><span class="fl">Comiss&atilde;o</span><span class="fv">{$comissao}%</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ CLÁUSULAS ═════ -->
  <div class="clause"><span class="cl">a)</span>&nbsp;
    Sobre o valor da <strong>VENDA</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%,
    pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.
  </div>
  <div class="clause"><span class="cl">b)</span>&nbsp;
    Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente o im&oacute;vel
    de sua propriedade acima descrito, cuja &agrave;s custas ser&atilde;o de responsabilidade da CONTRATADA, fotografar o im&oacute;vel
    e suas depend&ecirc;ncias internas fazendo se publicar as fotos nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que
    desejar, inclusive na internet, afixar placas, faixas ou letreiros no im&oacute;vel, realizar visita&ccedil;&otilde;es e
    demonstra&ccedil;&otilde;es aos interessados.
  </div>
  <div class="clause"><span class="cl">c)</span>&nbsp;
    O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou
    restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>VENDA</strong>, comprometendo-se em apresentar &agrave;s suas custas
    a documenta&ccedil;&atilde;o exigida em transa&ccedil;&otilde;es de VENDA, t&atilde;o logo que solicitado.
  </div>
  <p class="agree-text">
    E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a
    de duas testemunhas, ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.
  </p>
  <p class="date-line">Aracaju, &nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.</p>

  <!-- ═════ ASSINATURAS ═════ -->
  <table class="sig-table">
    <tr>
      <td><div class="sline">{$nomeContratante}</div><div class="stitle">Contratante</div></td>
      <td><div class="sline">&nbsp;</div><div class="stitle">Contratante</div><div class="ssub">C&ocirc;njuge</div></td>
      <td><div class="sline">&nbsp;</div><div class="stitle">{$appName}</div><div class="ssub">Contratada</div></td>
      <td><div class="sline">{$nomeCorretor}</div><div class="stitle">Corretor(a)</div><div class="ssub">Credenciado</div></td>
    </tr>
  </table>

  <!-- ═════ TESTEMUNHAS ═════ -->
  <p class="test-label">Testemunhas:</p>
  <table class="test-table">
    <tr>
      <td><div class="sline">{$test1Nome}</div><div class="ssub">CPF: {$test1Cpf}</div></td>
      <td><div class="sline">{$test2Nome}</div><div class="ssub">CPF: {$test2Cpf}</div></td>
    </tr>
  </table>

  <!-- ═════ DOCUMENTOS ═════ -->
  {$docsHtml}

</div><!-- /content -->

<!-- ═══ FOOTER ═══ -->
<div class="footer">
  <strong>{$appName}</strong> &nbsp;|&nbsp;
  Av. Hermes Fontes, n&ordm; 1524, Bairro Luzia &ndash; CEP 49.048-010 &ndash; Aracaju/SE &nbsp;|&nbsp;
  (79) 3304-0000 / 99691-0000 &nbsp;|&nbsp;
  contato@a4imobiliaria.com.br &nbsp;|&nbsp;
  Gerado em: {$submDate}
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

<!-- ═══ HEADER RIBBON ═══ -->
<div class="header-ribbon">
  <table><tr>
    <td>Documento Contratual</td>
    <td class="hr-center">Preenchimento Eletr&ocirc;nico</td>
    <td class="hr-right"><strong>{$appName}</strong></td>
  </tr></table>
</div>

<!-- ═══ HEADER MAIN ═══ -->
<div class="header-main">
  <table><tr>
    <td class="hm-logo"><div class="logo-box-wrap">{$logoCell}</div></td>
    <td class="hm-title">
      <div class="hm-kicker">Formul&aacute;rio Oficial</div>
      <div class="hm-h1">Autoriza&ccedil;&atilde;o de Loca&ccedil;&atilde;o</div>
      <span class="hm-sub">Com Exclusividade</span>
      <div class="hm-desc">Contrato de intermedia&ccedil;&atilde;o imobili&aacute;ria</div>
      <div class="hm-meta">Validade jur&iacute;dica mediante preenchimento completo e aceita&ccedil;&atilde;o das cl&aacute;usulas</div>
    </td>
  </tr></table>
</div>

<!-- ═══ META STRIP ═══ -->
<div class="meta-strip">
  <table><tr>
    <td style="width:65%">
      <span class="badge-num">N&ordm; ALE-{$submId}</span>
      &nbsp;&nbsp;&#128197;&nbsp;{$submDate}
      &nbsp;&nbsp;&#9201;&nbsp;Prazo: <strong>{$prazo} dias</strong>
    </td>
    <td class="meta-right">{$excBadge}</td>
  </tr></table>
</div>

<div class="content">

  <!-- ═════ DADOS DO CONTRATANTE ═════ -->
  <div class="section">
    <div class="sec-head">Dados do Contratante</div>
    <table class="ft">
      <tr>
        <td style="width:60%"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$dataNasc}</span></td>
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
        <td colspan="3"><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr class="alt">
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular / WhatsApp</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ PARÁGRAFO LEGAL ═════ -->
  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio(a) e leg&iacute;timo(a) possuidor(a) do im&oacute;vel abaixo relacionado,
    contrata a <strong>{$appName}</strong>, inscrita no CRECI n&ordm; 218 PJ, para promover, de forma
    <strong>EXCLUSIVA</strong>, a <strong>LOCA&Ccedil;&Atilde;O</strong> do seu im&oacute;vel acima descrito, pelo prazo
    m&iacute;nimo de <strong>({$prazoMinimo}) dias</strong>, prorrog&aacute;vel automaticamente por per&iacute;odo igual e
    sucessivo, at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e
    pelas condi&ccedil;&otilde;es estipuladas nesta autoriza&ccedil;&atilde;o de <strong>LOCA&Ccedil;&Atilde;O</strong>.
  </div>

  <!-- ═════ DADOS DO IMÓVEL ═════ -->
  <div class="section">
    <div class="sec-head">Dados do Im&oacute;vel</div>
    <div class="sec-sub"><strong>Tipo:</strong> {$tipoImovel}</div>
    <table class="ft" style="border-top:none;">
      <tr>
        <td colspan="4"><span class="fl">Endere&ccedil;o Completo</span><span class="fv">{$endImovel}</span></td>
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
        <td><span class="fl">Matr&iacute;cula IPTU</span><span class="fv">{$matriculaIptu}</span></td>
        <td><span class="fl">Energisa / UC</span><span class="fv">{$energisaUc}</span></td>
        <td><span class="fl">Iguá</span><span class="fv">{$deso}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Energisa / UC N&ordm;</span><span class="fv">{$energisaUcNum}</span></td>
        <td colspan="2"><span class="fl">Iguá Matr&iacute;cula N&ordm;</span><span class="fv">{$desoMatNum}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ DESCRIÇÃO ═════ -->
  <div class="section">
    <div class="sec-head">Descri&ccedil;&atilde;o do Im&oacute;vel</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea m&sup2;</span><span class="fv">{$areaPriv}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="2"><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td colspan="3"><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Lazer Completo?</span><span class="fv">{$lazer}</span></td>
      </tr>
      <tr class="alt">
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es</span><span class="fv" style="min-height:16px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <!-- ═════ VALOR DA LOCAÇÃO ═════ -->
  <div class="section">
    <div class="sec-head">Valor da Loca&ccedil;&atilde;o</div>
    <table class="ft">
      <tr>
        <td style="width:28%"><span class="fl">Valor Pretendido R$</span><span class="fv" style="font-size:10px;font-weight:bold;color:#1a6e8e;">{$valorLocacao}</span></td>
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

  <!-- ═════ CLÁUSULAS ═════ -->
  <div class="clause"><span class="cl">a)</span>&nbsp;
    Sobre o valor da <strong>LOCA&Ccedil;&Atilde;O</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%,
    pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.
  </div>
  <div class="clause"><span class="cl">b)</span>&nbsp;
    Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente o im&oacute;vel
    de sua propriedade acima descrito, fotografar o im&oacute;vel e suas depend&ecirc;ncias internas fazendo se publicar as fotos
    nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que desejar, inclusive na internet, afixar placas, faixas ou letreiros
    no im&oacute;vel, realizar visita&ccedil;&otilde;es e demonstra&ccedil;&otilde;es aos interessados.
  </div>
  <div class="clause"><span class="cl">c)</span>&nbsp;
    O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou
    restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>LOCA&Ccedil;&Atilde;O</strong>, comprometendo-se em apresentar &agrave;s suas custas
    a documenta&ccedil;&atilde;o exigida em transa&ccedil;&otilde;es de LOCA&Ccedil;&Atilde;O, t&atilde;o logo que solicitado.
  </div>
  <p class="agree-text">
    E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a
    de duas testemunhas, ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.
  </p>
  <p class="date-line">Aracaju, &nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.</p>

  <!-- ═════ ASSINATURAS ═════ -->
  <table class="sig-table">
    <tr>
      <td><div class="sline">{$nomeContratante}</div><div class="stitle">Contratante</div></td>
      <td><div class="sline">&nbsp;</div><div class="stitle">Contratante</div><div class="ssub">C&ocirc;njuge</div></td>
      <td><div class="sline">&nbsp;</div><div class="stitle">{$appName}</div><div class="ssub">Contratada</div></td>
      <td><div class="sline">{$nomeCorretor}</div><div class="stitle">Corretor(a)</div><div class="ssub">Credenciado</div></td>
    </tr>
  </table>

  <!-- ═════ TESTEMUNHAS ═════ -->
  <p class="test-label">Testemunhas:</p>
  <table class="test-table">
    <tr>
      <td><div class="sline">{$test1Nome}</div><div class="ssub">CPF: {$test1Cpf}</div></td>
      <td><div class="sline">{$test2Nome}</div><div class="ssub">CPF: {$test2Cpf}</div></td>
    </tr>
  </table>

  <!-- ═════ DOCUMENTOS ═════ -->
  {$docsHtml}

</div><!-- /content -->

<!-- ═══ FOOTER ═══ -->
<div class="footer">
  <strong>{$appName}</strong> &nbsp;|&nbsp;
  Av. Hermes Fontes, n&ordm; 1524, Bairro Luzia &ndash; CEP 49.048-010 &ndash; Aracaju/SE &nbsp;|&nbsp;
  (79) 3304-0000 / 99691-0000 &nbsp;|&nbsp;
  contato@a4imobiliaria.com.br &nbsp;|&nbsp;
  Gerado em: {$submDate}
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
            . "<td><span class='docs-file'>&#128206; <a href='{$fileUrl}'>{$fileName}</a></span></td>"
            . "</tr>";
    }

    if ($rows === '') return '';

    return "<div class='section' style='margin-top:9px;'>"
        . "<div class='sec-head'>Documentos Anexados</div>"
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
