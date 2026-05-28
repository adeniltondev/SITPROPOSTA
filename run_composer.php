<?php
$_senha = 'a4imob2026';
$_cname = 'rc_auth';
$_cval  = md5($_senha . 'forma4x');

function rc_es($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rc_cmd($cmd) {
    $out = ''; $code = -1;
    if (function_exists('exec')) {
        $lines = [];
        exec($cmd . ' 2>&1', $lines, $code);
        $out = implode("\n", $lines);
    } elseif (function_exists('shell_exec')) {
        $out = (string)shell_exec($cmd . ' 2>&1');
        $code = 0;
    } else {
        $out = 'ERRO: exec e shell_exec desabilitados no servidor.';
        $code = 1;
    }
    return ['out' => $out, 'code' => $code];
}

$authed = (isset($_COOKIE[$_cname]) && $_COOKIE[$_cname] === $_cval);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if ($_POST['senha'] === $_senha) {
        setcookie($_cname, $_cval, time() + 7200, '/');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $err = 'Senha incorreta.';
}

if ($authed && isset($_GET['ac'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ac = $_GET['ac'];

    if ($ac === 'dl') {
        $dest = __DIR__ . '/composer.phar';
        if (is_file($dest)) {
            echo json_encode(['ok' => true, 'msg' => 'composer.phar ja existe.']);
            exit;
        }
        $data = @file_get_contents('https://getcomposer.org/composer.phar');
        if ($data && strlen($data) > 100000) {
            file_put_contents($dest, $data);
            echo json_encode(['ok' => true, 'msg' => 'Baixado! ' . round(strlen($data)/1024) . ' KB']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Falha no download. Envie manualmente pelo cPanel.']);
        }
        exit;
    }

    if ($ac === 'inst') {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        @ignore_user_abort(true);
        error_reporting(0);

        // Tenta encontrar um PHP CLI valido
        $phpBin = null;
        $candidates = array_filter([PHP_BINARY, '/usr/bin/php', '/usr/local/bin/php', 'php', 'php8.2', 'php8.1', 'php8.0', 'php7.4']);
        foreach ($candidates as $c) {
            $t = rc_cmd($c . ' -r "echo 1;"');
            if (trim($t['out']) === '1') { $phpBin = $c; break; }
        }

        $phar = __DIR__ . '/composer.phar';
        if ($phpBin && is_file($phar)) {
            $cmd = $phpBin . ' ' . escapeshellarg($phar) . ' install --no-dev --optimize-autoloader --no-interaction --working-dir=' . escapeshellarg(__DIR__);
        } elseif ($phpBin) {
            $cmd = 'cd ' . escapeshellarg(__DIR__) . ' && ' . $phpBin . ' composer install --no-dev --optimize-autoloader --no-interaction';
        } else {
            $cmd = 'cd ' . escapeshellarg(__DIR__) . ' && composer install --no-dev --optimize-autoloader --no-interaction';
        }

        $r = rc_cmd($cmd);
        $ok = ($r['code'] === 0 && is_dir(__DIR__ . '/vendor'));
        // mesmo sem vendor/ pode ter parcialmente instalado — checar novamente
        if (!$ok && is_dir(__DIR__ . '/vendor')) $ok = true;
        echo json_encode(['ok' => $ok, 'out' => $r['out'], 'code' => $r['code'], 'php' => $phpBin ?: 'nao encontrado']);
        exit;
    }

    if ($ac === 'del') {
        setcookie($_cname, '', time() - 3600, '/');
        echo json_encode(['ok' => @unlink(__FILE__), 'msg' => 'Arquivo excluido!']);
        exit;
    }

    if ($ac === 'info') {
        echo json_encode([
            'php'    => PHP_VERSION,
            'exec'   => function_exists('exec')       ? 'sim' : 'nao',
            'shell'  => function_exists('shell_exec') ? 'sim' : 'nao',
            'fopen'  => ini_get('allow_url_fopen')    ? 'sim' : 'nao',
            'vendor' => is_dir(__DIR__ . '/vendor')   ? 'sim' : 'nao',
            'phar'   => is_file(__DIR__ . '/composer.phar') ? 'sim' : 'nao',
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'acao invalida']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalador Composer</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font:14px/1.5 Arial,sans-serif;background:#f0f4f8;padding:24px 12px}
.w{max-width:660px;margin:0 auto}
h1{font-size:18px;color:#1e293b;margin-bottom:4px}
.sub{color:#64748b;font-size:12px;margin-bottom:20px}
.card{background:#fff;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.1);padding:18px;margin-bottom:14px}
.card h2{font-size:13px;font-weight:700;margin-bottom:10px;color:#1e293b}
.row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:12px}
.row:last-child{border-bottom:none}
.lbl{color:#64748b}
.badge{display:inline-block;padding:1px 9px;border-radius:20px;font-size:11px;font-weight:700}
.ok{background:#dcfce7;color:#15803d}.warn{background:#fef9c3;color:#854d0e}
.btn{padding:9px 16px;border-radius:6px;border:none;font-size:13px;font-weight:600;cursor:pointer}
.btn:hover{opacity:.85}.btn:disabled{opacity:.4;cursor:not-allowed}
.blue{background:#2563eb;color:#fff}.green{background:#16a34a;color:#fff}
.red{background:#dc2626;color:#fff}.gray{background:#e2e8f0;color:#374151}
.bw{display:block;width:100%;text-align:center;margin-bottom:8px}
.log{background:#0f172a;color:#94a3b8;font:11px/1.6 monospace;padding:12px;border-radius:6px;max-height:240px;overflow-y:auto;white-space:pre-wrap;display:none;margin-top:10px}
.log.vis{display:block}
.spin{display:inline-block;width:13px;height:13px;border:2px solid #c7d2fe;border-top-color:#2563eb;border-radius:50%;animation:g .7s linear infinite;vertical-align:middle;margin-right:5px}
@keyframes g{to{transform:rotate(360deg)}}
.msg{padding:9px 13px;border-radius:6px;font-size:12px;margin-top:8px;display:none}
.mok{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.merr{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
code{background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:11px}
.form-box{max-width:320px;margin:50px auto}
.form-box h2{text-align:center;margin-bottom:16px}
.form-box input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;margin-bottom:10px}
.err-msg{color:#dc2626;font-size:12px;text-align:center;margin-bottom:10px}
</style>
</head>
<body>
<div class="w">
<?php if (!$authed): ?>
<div class="card form-box">
  <h2>Instalador Composer</h2>
  <?php if ($err): ?><div class="err-msg"><?= rc_es($err) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <input type="password" name="senha" placeholder="Senha de acesso" autofocus required>
    <button class="btn blue" style="width:100%;padding:11px">Entrar</button>
  </form>
</div>
<?php else: ?>
<h1>Instalador de Dependencias</h1>
<p class="sub">Instala DomPDF + PHPMailer via Composer. <strong>Exclua este arquivo apos usar!</strong></p>

<div class="card">
  <h2>Status do Ambiente</h2>
  <div class="row"><span class="lbl">PHP</span><span class="badge ok"><?= rc_es(PHP_VERSION) ?></span></div>
  <div class="row"><span class="lbl">vendor/</span><span class="badge <?= is_dir(__DIR__.'/vendor') ? 'ok' : 'warn' ?>"><?= is_dir(__DIR__.'/vendor') ? 'Instalada' : 'Ausente' ?></span></div>
  <div class="row"><span class="lbl">composer.phar</span><span class="badge <?= is_file(__DIR__.'/composer.phar') ? 'ok' : 'warn' ?>"><?= is_file(__DIR__.'/composer.phar') ? 'Presente' : 'Ausente' ?></span></div>
  <button class="btn gray" style="margin-top:10px;font-size:12px" onclick="info()">Checar ambiente</button>
  <div class="log" id="logInfo"></div>
</div>

<?php if (is_dir(__DIR__.'/vendor')): ?>
<div class="card" style="border:2px solid #bbf7d0">
  <h2 style="color:#15803d">Dependencias ja instaladas!</h2>
  <p style="font-size:12px;color:#166534">A pasta vendor/ existe. Exclua este arquivo por seguranca.</p>
</div>
<?php else: ?>
<div class="card">
  <h2>Passo 1 — Baixar composer.phar</h2>
  <button class="btn blue bw" id="btnDl" onclick="baixar()" <?= is_file(__DIR__.'/composer.phar') ? 'disabled' : '' ?>>
    <?= is_file(__DIR__.'/composer.phar') ? 'composer.phar ja presente' : 'Baixar composer.phar automaticamente' ?>
  </button>
  <div class="msg mok" id="okDl"></div>
  <div class="msg merr" id="errDl"></div>
  <p style="font-size:12px;color:#64748b;margin-top:10px"><strong>Ou envie manualmente:</strong> baixe em <a href="https://getcomposer.org/composer.phar">getcomposer.org/composer.phar</a> e envie para a raiz do site pelo cPanel.</p>
</div>

<div class="card">
  <h2>Passo 2 — Instalar Dependencias</h2>
  <p style="font-size:12px;color:#64748b;margin-bottom:12px">Executa <code>composer install --no-dev</code> na raiz do site.</p>
  <button class="btn green bw" id="btnInst" onclick="instalar()">Executar Composer Install</button>
  <div class="msg mok" id="okInst"></div>
  <div class="msg merr" id="errInst"></div>
  <div class="log" id="logInst"></div>
</div>

<div class="card" style="border:1px dashed #94a3b8">
  <h2>Alternativa — Instalar no Windows e enviar pelo cPanel</h2>
  <p style="font-size:12px;color:#374151;line-height:2">
    1. Baixe o Composer: <a href="https://getcomposer.org/Composer-Setup.exe" target="_blank">getcomposer.org/Composer-Setup.exe</a><br>
    2. Abra o CMD na pasta do projeto:<br>
    <code>cd "C:\Users\User\Documents\SITEFORMA4IMOBILIARIA"</code><br>
    <code>composer install --no-dev --optimize-autoloader</code><br>
    3. Compacte a pasta <code>vendor/</code> gerada em um .zip<br>
    4. cPanel → Gerenciador de Arquivos → public_html → Upload → Extrair
  </p>
</div>
<?php endif; ?>

<div class="card" style="border:2px solid #fecaca">
  <h2 style="color:#dc2626">Excluir este arquivo (OBRIGATORIO)</h2>
  <button class="btn red bw" onclick="excluir()">Excluir run_composer.php</button>
  <div class="msg mok" id="okDel"></div>
  <div class="msg merr" id="errDel"></div>
</div>
<?php endif; ?>
</div>
<script>
function show(id,txt,cls){var e=document.getElementById(id);e.innerHTML=txt;e.className='msg '+cls;e.style.display='block';}
async function req(ac){
  var r=await fetch('?ac='+ac);
  if(!r.ok) throw new Error('HTTP '+r.status);
  return r.json();
}
async function baixar(){
  var b=document.getElementById('btnDl');
  b.disabled=true;b.innerHTML='<span class="spin"></span>Baixando...';
  try{var d=await req('dl');
    if(d.ok){show('okDl','Sucesso: '+d.msg,'mok');b.textContent='Baixado!';}
    else{show('errDl','Erro: '+d.msg,'merr');b.disabled=false;b.textContent='Tentar novamente';}
  }catch(e){show('errDl','Erro: '+e.message,'merr');b.disabled=false;b.textContent='Tentar novamente';}
}
async function instalar(){
  var b=document.getElementById('btnInst');
  b.disabled=true;b.innerHTML='<span class="spin"></span>Instalando... aguarde 1-3 min';
  try{var d=await req('inst');
    var lg=document.getElementById('logInst');
    if(d.out){lg.textContent=d.out;lg.className='log vis';}
    if(d.ok){show('okInst','Instalado! Agora exclua este arquivo.','mok');b.textContent='Concluido!';}
    else{show('errInst','Falha (codigo '+d.code+'). Use a alternativa manual abaixo.','merr');b.disabled=false;b.textContent='Tentar novamente';}
  }catch(e){show('errInst','Timeout ou erro: '+e.message+'. A instalacao pode ter concluido, recarregue.','merr');b.disabled=false;b.textContent='Tentar novamente';}
}
async function excluir(){
  if(!confirm('Excluir run_composer.php?'))return;
  try{var d=await req('del');
    if(d.ok){show('okDel',d.msg,'mok');setTimeout(()=>{location.href='/admin/';},1500);}
    else{show('errDel','Nao foi possivel excluir. Remova pelo cPanel.','merr');}
  }catch(e){show('errDel','Erro. Remova pelo cPanel manualmente.','merr');}
}
async function info(){
  try{var d=await req('info');
    var lg=document.getElementById('logInfo');
    lg.textContent=JSON.stringify(d,null,2);
    lg.className='log vis';
  }catch(e){alert('Erro: '+e.message);}
}
</script>
</body>
</html>