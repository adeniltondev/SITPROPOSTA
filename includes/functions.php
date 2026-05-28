<?php
/**
 * Funções auxiliares gerais da aplicação
 *
 * @package FORMA4
 */

require_once __DIR__ . '/db.php';

// ============================================================
// SANITIZAÇÃO E VALIDAÇÃO
// ============================================================

/**
 * Sanitiza uma string para exibição segura no HTML (anti-XSS).
 *
 * @param mixed $value
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitiza um array de inputs, aplicando trim e htmlspecialchars.
 *
 * @param array $data
 * @return array
 */
function sanitizeArray(array $data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if (is_array($value)) {
            $clean[$cleanKey] = sanitizeArray($value);
        } else {
            $clean[$cleanKey] = trim(strip_tags((string) $value));
        }
    }
    return $clean;
}

/**
 * Valida CPF brasileiro.
 *
 * @param string $cpf
 * @return bool
 */
function validateCPF(string $cpf): bool
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += $cpf[$i] * ($t + 1 - $i);
        }
        $rem = $sum % 11;
        $digit = $rem < 2 ? 0 : 11 - $rem;
        if ($cpf[$t] != $digit) {
            return false;
        }
    }

    return true;
}

// ============================================================
// FORMATAÇÕES
// ============================================================

/**
 * Formata um timestamp ou string de data para o padrão brasileiro.
 *
 * @param string $dateStr
 * @return string  Ex: 14/04/2026 ou 14/04/2026 09:30
 */
function formatDate(string $dateStr, bool $withTime = false): string
{
    $ts = strtotime($dateStr);
    if (!$ts) {
        return $dateStr;
    }
    return $withTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

/**
 * Formata um valor numérico como moeda BRL.
 *
 * @param float|string $amount
 * @return string  Ex: R$ 250.000,00
 */
function formatCurrency($amount): string
{
    return 'R$ ' . number_format((float) $amount, 2, ',', '.');
}

/**
 * Gera um slug URL-friendly a partir de uma string.
 *
 * @param string $text
 * @return string
 */
function generateSlug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');

    // Substitui acentos
    $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
    $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    $text = str_replace($from, $to, $text);

    // Remove caracteres não alfanuméricos exceto hifens
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', trim($text));

    return $text;
}

/**
 * Garante que um slug seja único na tabela forms.
 *
 * @param string   $slug      Slug base
 * @param int|null $excludeId ID a excluir da verificação (em updates)
 * @return string
 */
function uniqueSlug(string $slug, ?int $excludeId = null): string
{
    $db   = Database::getInstance();
    $base = $slug;
    $i    = 1;

    while (true) {
        $sql    = 'SELECT id FROM forms WHERE slug = ?';
        $params = [$slug];

        if ($excludeId) {
            $sql    .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $exists = $db->fetchOne($sql, $params);

        if (!$exists) {
            break;
        }

        $slug = $base . '-' . $i++;
    }

    return $slug;
}

// ============================================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================================

/**
 * Retorna o valor de uma configuração do banco.
 *
 * @param string $key
 * @param string $default
 * @return string
 */
function getSetting(string $key, string $default = ''): string
{
    $db  = Database::getInstance();
    $row = $db->fetchOne('SELECT value FROM settings WHERE key_name = ? LIMIT 1', [$key]);

    return $row ? (string) $row['value'] : $default;
}

/**
 * Atualiza ou insere uma configuração.
 *
 * @param string $key
 * @param string $value
 */
function setSetting(string $key, string $value): void
{
    $db = Database::getInstance();
    $db->query(
        'INSERT INTO settings (key_name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [$key, $value]
    );
}

/**
 * Retorna todas as configurações como array chave => valor.
 *
 * @return array
 */
function getAllSettings(): array
{
    $db   = Database::getInstance();
    $rows = $db->fetchAll('SELECT key_name, value FROM settings');
    $map  = [];
    foreach ($rows as $row) {
        $map[$row['key_name']] = $row['value'];
    }
    return $map;
}

// ============================================================
// UPLOAD DE ARQUIVOS
// ============================================================

/**
 * Faz upload de um arquivo para o diretório informado.
 *
 * @param array  $file      Entrada de $_FILES[...]
 * @param string $directory Diretório de destino (caminho absoluto)
 * @param array  $allowed   Tipos MIME permitidos
 * @return string|false     Nome do arquivo salvo ou false em erro
 */
function uploadFile(array $file, string $directory, array $allowed = [])
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Valida tamanho
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    // Valida tipo MIME real (não apenas a extensão declarada)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!empty($allowed) && !in_array($mimeType, $allowed, true)) {
        return false;
    }

    // Gera nome único
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('', true) . '.' . $ext;
    $dest     = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    return $filename;
}

/**
 * Remove um arquivo do servidor com segurança (apenas dentro de /uploads).
 *
 * @param string $relativePath Caminho relativo dentro de /uploads (ex: pdfs/arquivo.pdf)
 * @return bool
 */
function deleteUploadedFile(string $relativePath): bool
{
    // Garante que o caminho não escape do diretório uploads
    $realBase = realpath(UPLOAD_PATH);
    $fullPath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));

    if (!$fullPath || strpos($fullPath, $realBase) !== 0) {
        return false; // Path traversal bloqueado
    }

    if (is_file($fullPath)) {
        return unlink($fullPath);
    }

    return false;
}

// ============================================================
// MENSAGENS FLASH
// ============================================================

/**
 * Armazena uma mensagem flash na sessão.
 *
 * @param string $message
 * @param string $type  success | error | warning | info
 */
function setFlash(string $message, string $type = 'success'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Lê e remove a mensagem flash da sessão.
 *
 * @return array|null ['message' => '...', 'type' => '...'] ou null
 */
function getFlash(): ?array
{
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// FORMULÁRIOS
// ============================================================

/**
 * Decodifica o JSON de campos de um formulário com segurança.
 *
 * @param string $json
 * @return array
 */
function decodeFields(string $json): array
{
    $fields = json_decode($json, true);
    return is_array($fields) ? $fields : [];
}

/**
 * Retorna o IP real do visitante.
 *
 * @return string
 */
function getClientIP(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Busca cidade e estado pelo IP via ip-api.com (gratuito, sem chave).
 * Retorna ['city' => '...', 'state' => '...'] ou valores vazios em caso de falha.
 *
 * @param string $ip
 * @return array{city:string,state:string}
 */
function getIPGeoLocation(string $ip): array
{
    $empty = ['city' => '', 'state' => ''];

    // IPs privados / loopback não têm geolocalização
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $empty;
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=city,regionName&lang=pt-BR';

    $ctx = stream_context_create(['http' => [
        'timeout'        => 3,
        'ignore_errors'  => true,
    ]]);

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return $empty;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $empty;
    }

    return [
        'city'  => mb_substr($data['city']       ?? '', 0, 100),
        'state' => mb_substr($data['regionName'] ?? '', 0, 100),
    ];
}

/**
 * Retorna o número de envios de um formulário.
 *
 * @param int $formId
 * @return int
 */
function countSubmissions(int $formId): int
{
    $db  = Database::getInstance();
    $row = $db->fetchOne('SELECT COUNT(*) as total FROM submissions WHERE form_id = ?', [$formId]);
    return $row ? (int) $row['total'] : 0;
}
