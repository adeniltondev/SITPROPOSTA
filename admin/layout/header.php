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
            position: fixed;
            width: 300px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,.16);
            padding: 14px;
            z-index: 9999;
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

        .share-actions {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }

        .btn-share-action {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: none;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: .7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .15s;
        }
        .btn-share-action:hover { opacity: .85; text-decoration: none; }
        .btn-share-action svg { width: 13px; height: 13px; flex-shrink: 0; }

        .btn-whatsapp { background: #25d366; color: #fff; }
        .btn-email    { background: #64748b; color: #fff; }
        .btn-copy-sm  { background: var(--primary); color: #fff; }
        .btn-copy-sm.copied { background: #10b981; }
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
                    </div>
                    <div class="share-actions">
                        <button class="btn-share-action btn-copy-sm" data-url="https://propostadelocacao.a4imobiliaria.com.br/proposta-locacao.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copiar
                        </button>
                        <a class="btn-share-action btn-whatsapp"
                           href="https://wa.me/?text=Preencha%20a%20Proposta%20de%20Loca%C3%A7%C3%A3o%3A%20https%3A%2F%2Fpropostadelocacao.a4imobiliaria.com.br%2Fproposta-locacao.php"
                           target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                            WhatsApp
                        </a>
                        <a class="btn-share-action btn-email"
                           href="mailto:?subject=Proposta%20de%20Loca%C3%A7%C3%A3o&body=Ol%C3%A1%2C%20acesse%20o%20formul%C3%A1rio%20de%20Proposta%20de%20Loca%C3%A7%C3%A3o%3A%0Ahttps%3A%2F%2Fpropostadelocacao.a4imobiliaria.com.br%2Fproposta-locacao.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            E-mail
                        </a>
                    </div>
                </div>

                <div class="share-link-row">
                    <span class="share-link-label">Proposta de Fiador</span>
                    <div class="share-link-box">
                        <span title="https://propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php">propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php</span>
                    </div>
                    <div class="share-actions">
                        <button class="btn-share-action btn-copy-sm" data-url="https://propostadelocacao.a4imobiliaria.com.br/proposta-fiador.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            Copiar
                        </button>
                        <a class="btn-share-action btn-whatsapp"
                           href="https://wa.me/?text=Preencha%20a%20Proposta%20de%20Fiador%3A%20https%3A%2F%2Fpropostadelocacao.a4imobiliaria.com.br%2Fproposta-fiador.php"
                           target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                            WhatsApp
                        </a>
                        <a class="btn-share-action btn-email"
                           href="mailto:?subject=Proposta%20de%20Fiador&body=Ol%C3%A1%2C%20acesse%20o%20formul%C3%A1rio%20de%20Proposta%20de%20Fiador%3A%0Ahttps%3A%2F%2Fpropostadelocacao.a4imobiliaria.com.br%2Fproposta-fiador.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            E-mail
                        </a>
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
            var isOpen = dd.classList.contains('open');
            dd.classList.remove('open');
            if (!isOpen) {
                var rect = btn.getBoundingClientRect();
                dd.style.top  = rect.top + 'px';
                dd.style.left = (rect.right + 8) + 'px';
                // Evita sair da tela na vertical
                dd.style.display = 'block';
                var ddH = dd.offsetHeight;
                dd.style.display = '';
                if (rect.top + ddH > window.innerHeight - 10) {
                    dd.style.top = Math.max(10, window.innerHeight - ddH - 10) + 'px';
                }
                dd.classList.add('open');
            }
        });

        document.addEventListener('click', function(e){
            if (!dd.contains(e.target) && e.target !== btn) {
                dd.classList.remove('open');
            }
        });

        dd.querySelectorAll('.btn-copy-sm').forEach(function(b){
            b.addEventListener('click', function(){
                var url = this.dataset.url;
                navigator.clipboard.writeText(url).then(function(){
                    b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
                    b.classList.add('copied');
                    setTimeout(function(){
                        b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copiar';
                        b.classList.remove('copied');
                    }, 2000);
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
