# ============================================================
# Instalar PHP e Composer no Windows (Sem precisar de Admin)
# ============================================================
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$installDir = "$HOME\php"
if (!(Test-Path $installDir)) { New-Item -ItemType Directory -Force -Path $installDir | Out-Null }

Write-Host "1. Encontrando a ultima versao do PHP 8.2..." -ForegroundColor Yellow
$html = Invoke-RestMethod "https://windows.php.net/downloads/releases/" -UseBasicParsing
$verMatched = [regex]::match($html, 'php-(8\.2\.\d+)-nts-Win32-vs16-x64\.zip')

if (!$verMatched.Success) {
    Write-Host "ERRO: Nao foi possivel encontrar o PHP 8.2 na pagina oficial." -ForegroundColor Red
    exit 1
}

$version = $verMatched.Groups[1].Value
$zipUrl = "https://windows.php.net/downloads/releases/php-$version-nts-Win32-vs16-x64.zip"
$zipPath = "$installDir\php-$version.zip"

Write-Host "Baixando PHP $version de $zipUrl ..." -ForegroundColor Yellow
Invoke-WebRequest -Uri $zipUrl -OutFile $zipPath -UseBasicParsing

Write-Host "2. Extraindo PHP para $installDir..." -ForegroundColor Yellow
Expand-Archive -Path $zipPath -DestinationPath $installDir -Force
Remove-Item $zipPath -Force

Write-Host "3. Configurando php.ini..." -ForegroundColor Yellow
$iniDest = "$installDir\php.ini"
Copy-Item "$installDir\php.ini-development" $iniDest -Force

$iniText = Get-Content $iniDest -Raw
$iniText = $iniText -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
$iniText = $iniText -replace ';extension=curl', 'extension=curl'
$iniText = $iniText -replace ';extension=dom', 'extension=dom'
$iniText = $iniText -replace ';extension=fileinfo', 'extension=fileinfo'
$iniText = $iniText -replace ';extension=mbstring', 'extension=mbstring'
$iniText = $iniText -replace ';extension=openssl', 'extension=openssl'
$iniText = $iniText -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
$iniText = $iniText -replace ';extension=sysvshm', 'extension=zip' # fallback ou zip normal 
$iniText = $iniText -replace ';extension=zip', 'extension=zip'
Set-Content $iniDest -Value $iniText -Encoding UTF8

Write-Host "4. Adicionando PHP ao PATH do seu usuario..." -ForegroundColor Yellow
$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
if ($userPath -notmatch [regex]::Escape($installDir)) {
    $newPath = $userPath + ";$installDir"
    [Environment]::SetEnvironmentVariable("Path", $newPath, "User")
    $env:Path = $newPath + ";" + [Environment]::GetEnvironmentVariable("Path", "Machine")
}

Write-Host "5. Verificando o PHP..." -ForegroundColor Yellow
& "$installDir\php.exe" -v

Write-Host "6. Baixando Composer GLOBAL..." -ForegroundColor Yellow
$composerPath = "$installDir\composer.phar"
Invoke-WebRequest "https://getcomposer.org/composer.phar" -OutFile $composerPath -UseBasicParsing

$composerBat = "$installDir\composer.bat"
Set-Content -Path $composerBat -Value "@echo off`nphp `"%~dp0composer.phar`" %*"

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " PHP E COMPOSER INSTALADOS COM SUCESSO!" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "1. FECHE este terminal (PowerShell/CMD) e abra um NOVO." -ForegroundColor White
Write-Host "2. Voce podera usar o comando 'composer' de qualquer lugar." -ForegroundColor White
Write-Host "3. Para o nosso projeto, abra o novo terminal e rode:" -ForegroundColor White
Write-Host "   cd `"C:\Users\User\Documents\SITEFORMA4IMOBILIARIA`"" -ForegroundColor Yellow
Write-Host "   composer install --no-dev" -ForegroundColor Yellow
Write-Host "   Compress o 'vendor' gerado num .zip e envie no cPanel!" -ForegroundColor Yellow
Write-Host ""
