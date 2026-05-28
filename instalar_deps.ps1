# ============================================================
# instalar_deps.ps1 — Instala dependencias PHP localmente
# e gera vendor_upload.zip pronto para enviar ao cPanel
# ============================================================
$ProgressPreference = 'SilentlyContinue'
$ErrorActionPreference = 'Stop'

$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
Write-Host ""
Write-Host "=== Instalador de Dependencias FORMA4 ===" -ForegroundColor Cyan
Write-Host "Pasta do projeto: $dir"
Write-Host ""

# --- 1. Verificar se PHP ja esta disponivel ---
$phpExe = $null
foreach ($candidate in @('php', 'php.exe')) {
    try {
        $v = & $candidate -r "echo 1;" 2>$null
        if ($v -eq '1') { $phpExe = $candidate; break }
    } catch {}
}

# --- 2. Procurar PHP em locais comuns se nao encontrado no PATH ---
if (-not $phpExe) {
    $commonPaths = @(
        "C:\xampp\php\php.exe",
        "C:\xampp8\php\php.exe",
        "C:\laragon\bin\php\php8.2\php.exe",
        "C:\laragon\bin\php\php8.1\php.exe",
        "C:\laragon\bin\php\php8.0\php.exe",
        "C:\laragon\bin\php\php7.4\php.exe",
        "C:\wamp64\bin\php\php8.2.0\php.exe",
        "C:\wamp\bin\php\php8.1.0\php.exe",
        "$env:ProgramFiles\php\php.exe",
        "$env:ProgramFiles\PHP\php.exe",
        "C:\php\php.exe",
        "C:\php8\php.exe"
    )
    foreach ($p in $commonPaths) {
        if (Test-Path $p) {
            try {
                $v = & $p -r "echo 1;" 2>$null
                if ($v -eq '1') { $phpExe = $p; Write-Host "PHP encontrado: $p" -ForegroundColor Green; break }
            } catch {}
        }
    }
}

# --- 3. Tentar instalar PHP via winget se ainda nao encontrado ---
if (-not $phpExe) {
    Write-Host "PHP nao encontrado. Tentando instalar via winget..." -ForegroundColor Yellow
    try {
        winget install --id PHP.PHP.8.2 --silent --accept-source-agreements --accept-package-agreements 2>&1 | Out-Null
        # Recarregar PATH
        $env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path','User')
        $v = & php -r "echo 1;" 2>$null
        if ($v -eq '1') { $phpExe = 'php'; Write-Host "PHP instalado via winget." -ForegroundColor Green }
    } catch {}
}

if (-not $phpExe) {
    Write-Host ""
    Write-Host "ERRO: PHP nao encontrado no sistema." -ForegroundColor Red
    Write-Host ""
    Write-Host "Opcoes:" -ForegroundColor Yellow
    Write-Host "  1. Instale o XAMPP: https://www.apachefriends.org/pt_br/download.html" -ForegroundColor White
    Write-Host "     Depois execute este script novamente."
    Write-Host ""
    Write-Host "  2. Instale o PHP direto: https://windows.php.net/download/" -ForegroundColor White
    Write-Host "     Baixe 'PHP 8.2 VS16 x64 Non Thread Safe', extraia em C:\php\"
    Write-Host "     Adicione C:\php\ ao PATH e execute este script novamente."
    Write-Host ""
    Read-Host "Pressione Enter para sair"
    exit 1
}

Write-Host "PHP: $phpExe" -ForegroundColor Green

# --- 4. Baixar composer.phar ---
$composerPhar = "$dir\composer.phar"
if (-not (Test-Path $composerPhar)) {
    Write-Host "Baixando composer.phar..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile $composerPhar -UseBasicParsing
    Write-Host "composer.phar baixado." -ForegroundColor Green
} else {
    Write-Host "composer.phar ja existe." -ForegroundColor Green
}

# --- 5. Rodar composer install ---
Write-Host ""
Write-Host "Rodando composer install (pode demorar 1-3 minutos)..." -ForegroundColor Yellow
Write-Host ""

$env:COMPOSER_NO_INTERACTION = "1"
& $phpExe $composerPhar install --no-dev --optimize-autoloader --no-interaction --working-dir="$dir"

if (-not (Test-Path "$dir\vendor")) {
    Write-Host ""
    Write-Host "ERRO: vendor/ nao foi criado. Verifique as mensagens acima." -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}

Write-Host ""
Write-Host "vendor/ criado com sucesso!" -ForegroundColor Green

# --- 6. Criar zip para upload ---
$zipPath = "$dir\vendor_upload.zip"
Write-Host "Compactando vendor/ para upload..." -ForegroundColor Yellow

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -Assembly System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory("$dir\vendor", $zipPath)

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host " PRONTO! vendor_upload.zip criado ($sizeMB MB)" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximos passos:" -ForegroundColor White
Write-Host "1. Abra o cPanel -> Gerenciador de Arquivos" -ForegroundColor White
Write-Host "2. Navegue ate public_html/" -ForegroundColor White
Write-Host "3. Clique em Upload e envie: vendor_upload.zip" -ForegroundColor White
Write-Host "4. Selecione o arquivo e clique em Extrair" -ForegroundColor White
Write-Host "   (sera criada a pasta vendor/ no servidor)" -ForegroundColor White
Write-Host ""
Write-Host "Apos extrair, o sistema de PDF e email funcionara!" -ForegroundColor Green
Write-Host ""

Read-Host "Pressione Enter para sair"
