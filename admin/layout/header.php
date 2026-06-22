<?php
/**
 * Layout: Cabeçalho do painel administrativo
 * Incluído no topo de todas as páginas admin.
 *
 * Variáveis esperadas antes do include:
 *  $pageTitle  (string) – título da aba/página
 *  $activeMenu (string) – slug do menu ativo (dashboard|forms|submissions|settings)
 *
 * @package FORMA4
 */

// Carrega settings do banco uma vez por requisição
if (!isset($sysSettings)) {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
    $sysSettings = getAllSettings();
}

$appName      = e($sysSettings['app_name']     ?? APP_NAME);
$primaryColor = e($sysSettings['primary_color'] ?? '#2563EB');
$logoFile     = $sysSettings['logo_path'] ?? '';
// Usa sempre APP_URL da constante para garantir assets corretos mesmo com DB desatualizado
$appUrl       = rtrim(APP_URL, '/');

// Sincroniza app_url no banco se estiver desatualizado
if (empty($sysSettings['app_url']) || rtrim($sysSettings['app_url'], '/') !== $appUrl) {
    setSetting('app_url', $appUrl);
}
$pageTitle    = $pageTitle ?? 'Painel';
$activeMenu   = $activeMenu ?? '';

// Flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= $appName ?></title>

    <!-- Fonte Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS principal -->
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/style.css">

    <!-- Cor primária dinâmica via variável CSS -->
    <style>
        :root { --primary: <?= $primaryColor ?>; }

        .nav-item--disabled {
            opacity: .5;
            cursor: not-allowed;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .badge-soon {
            margin-left: auto;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #f59e0b;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }

        /* Share button & dropdown */
        .share-wrapper { position: relative; }

        .share-dropdown {
            display: none;
            position: absolute;
            left: calc(100% + 8px);
            top: 0;
            width: 280px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            padding: 14px;
            z-index: 999;
        }
        .share-dropdown.open { display: block; }

        .share-dropdown h4 {
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .share-link-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 10px;
        }
        .share-link-row:last-child { margin-bottom: 0; }

        .share-link-label {
            font-size: .72rem;
            font-weight: 600;
            color: var(--body-text);
        }

        .share-link-box {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 8px;
        }

        .share-link-box span {
            flex: 1;
            font-size: .7rem;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-copy {
            flex-shrink: 0;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 3px 8px;
            font-size: .68rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-copy:hover { background: var(--primary-dark); }
        .btn-copy.copied { background: #10b981; }
    </style>
</head>
<body class="admin-layout">

<!-- =========================================================
     SIDEBAR
     ========================================================= -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logoFile && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoFile)): ?>
            <img src="<?= $appUrl ?>/uploads/logos/<?= e($logoFile) ?>" alt="<?= $appName ?>" class="sidebar-logo">
        <?php else: ?>
            <span class="sidebar-brand"><?= $appName ?></span>
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= $appUrl ?>/admin/index.php"
           class="nav-item <?= $activeMenu === 'dashboard'    ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <span class="nav-item nav-item--disabled">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg>
            Formulários
            <span class="badge-soon">Em breve</span>
        </span>
        <a href="<?= $appUrl ?>/admin/submissions.php"
           class="nav-item <?= $activeMenu === 'submissions'  ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            Envios
        </a>
        <a href="<?= $appUrl ?>/admin/settings.php"
           class="nav-item <?= $activeMenu === 'settings'     ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            Configurações
        </a>

        <!-- Compartilhar formulários -->
        <div class="share-wrapper">
            <button type="button" class="nav-item" id="shareBtn" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                Compartilhar
            </button>

            <div class="share-dropdown" id="shareDropdown">
                <h4>Links dos Formulários</h4>

                <div class="share-link-row">
                    <span class="share-link-label">Proposta de Locação</span>
                    <div class="share-link-box">
                        <span title="https://propostadelocacao.a4imobiliaria.com.br/proposta-locacao.php">propostadelocacao.a4imobiliaria.com.br/proposta-locacao.php</span>
                        <button class="btn-copy" data-url="https://propostadelocacao.a4imobiliaria.com.br/proposta-locacao.php">Copiar</button>
                    </div>
                </div>

                <div class="share-link-row">
                    <span class="share-link-label">Proposta de Fiador</span>
                    <div class="share-link-box">
                        <span title="https://propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php">propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php</span>
                        <button class="btn-copy" data-url="https://propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php">Copiar</button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
    (function(){
        var btn = document.getElementById('shareBtn');
        var dd  = document.getElementById('shareDropdown');
        if (!btn || !dd) return;

        btn.addEventListener('click', function(e){
            e.stopPropagation();
            dd.classList.toggle('open');
        });

        document.addEventListener('click', function(e){
            if (!dd.contains(e.target) && e.target !== btn) {
                dd.classList.remove('open');
            }
        });

        dd.querySelectorAll('.btn-copy').forEach(function(b){
            b.addEventListener('click', function(){
                var url = this.dataset.url;
                navigator.clipboard.writeText(url).then(function(){
                    b.textContent = 'Copiado!';
                    b.classList.add('copied');
                    setTimeout(function(){ b.textContent = 'Copiar'; b.classList.remove('copied'); }, 2000);
                });
            });
        });
    })();
    </script>

    <div class="sidebar-footer">
        <a href="<?= $appUrl ?>/logout.php" class="nav-item nav-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Sair
        </a>
    </div>
</aside>

<!-- =========================================================
     CONTEÚDO PRINCIPAL
     ========================================================= -->
<main class="main-content">
    <!-- Top bar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        <div class="topbar-user">
            <span class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></span>
            <span class="user-name"><?= e($_SESSION['user_name'] ?? '') ?></span>
        </div>
    </header>

    <!-- Flash message -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Fechar">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Conteúdo da página começa aqui -->
    <div class="page-body">
