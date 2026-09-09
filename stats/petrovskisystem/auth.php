<?php

declare(strict_types=1);

session_name('jp_spotify_admin');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict',
    'path' => '/stats/petrovskisystem/'
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const USERNAME = 'bendemen';
const PASSWORD_HASH = '6f8c08f07aa2740abd5d6bf3e93739536a062a06512fbc362bd64394fad0e6b5';
const CODE_TTL = 600;
const MAX_CODE_ATTEMPTS = 5;
const SMTP_HOST = 'JustPetrov.com';
const SMTP_PORT = 465;
const SMTP_USER = 'automail@justpetrov.com';

function out(bool $ok, string $message = '', array $extra = []): never {
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function originLocation(): string {
    $ip = clientIp();
    if ($ip === 'unknown' || filter_var($ip, FILTER_VALIDATE_IP) === false) return 'Unknown';
    $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    $d = $json ? json_decode($json, true) : null;
    if (!is_array($d)) return 'Unknown';
    $city = trim((string)($d['city'] ?? ''));
    $country = trim((string)($d['country_name'] ?? ''));
    return trim($country . ($city !== '' && $country !== '' ? ' — ' : '') . $city) ?: 'Unknown';
}

function smtpRead($fp): string {
    $response = '';
    while (($line = fgets($fp, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    return $response;
}

function smtpExpect($fp, int $code): void {
    $r = smtpRead($fp);
    if ((int)substr($r, 0, 3) !== $code) throw new RuntimeException('SMTP error: ' . trim($r));
}

function smtpCommand($fp, string $command, int $code): void {
    fwrite($fp, $command . "\r\n");
    smtpExpect($fp, $code);
}

function sendAuthMail(string $code, string $location): void {
    $password = getenv('SMTP_PASS') ?: '';
    if ($password === '') throw new RuntimeException('SMTP_PASS is not configured on the server.');

    $fp = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$fp) throw new RuntimeException('SMTP connection failed: ' . $errstr);
    stream_set_timeout($fp, 10);

    try {
        smtpExpect($fp, 220);
        smtpCommand($fp, 'EHLO justpetrov.com', 250);
        smtpCommand($fp, 'AUTH LOGIN', 334);
        smtpCommand($fp, base64_encode(SMTP_USER), 334);
        smtpCommand($fp, base64_encode($password), 235);
        smtpCommand($fp, 'MAIL FROM:<' . SMTP_USER . '>', 250);
        smtpCommand($fp, 'RCPT TO:<' . SMTP_USER . '>', 250);
        fwrite($fp, "DATA\r\n");
        smtpExpect($fp, 354);

        $body = "(Auth Code)\r\nPage: Spotify Admin Login\r\nOrigin: " . $location . "\r\n\r\nYour authentication code is: " . $code . "\r\nThis code expires in 10 minutes.\r\n";
        $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;
        $message = "From: JustPetrov Admin <" . SMTP_USER . ">\r\n" .
                   "To: " . SMTP_USER . "\r\n" .
                   "Subject: Auth Code Requested\r\n" .
                   "Date: " . date(DATE_RFC2822) . "\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n" .
                   "Content-Transfer-Encoding: 8bit\r\n\r\n" . $body;
        fwrite($fp, $message . "\r\n.\r\n");
        smtpExpect($fp, 250);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

$action = (string)($_GET['action'] ?? '');
$data = input();

if ($action === 'status') {
    out(true, '', ['authenticated' => !empty($_SESSION['authenticated'])]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], '', (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
    out(true, 'Logged out.');
}

if ($action === 'request') {
    $user = (string)($data['username'] ?? '');
    $pass = (string)($data['password'] ?? '');
    if (!hash_equals(USERNAME, $user) || !hash_equals(PASSWORD_HASH, hash('sha256', $pass))) {
        usleep(250000);
        out(false, 'INVALID LOGIN');
    }

    $now = time();
    $last = (int)($_SESSION['last_code_request'] ?? 0);
    if ($last && $now - $last < 30) out(false, 'Please wait before requesting another code.');

    $code = (string)random_int(100000, 999999);
    $_SESSION['auth_code_hash'] = hash('sha256', $code);
    $_SESSION['auth_code_expires'] = $now + CODE_TTL;
    $_SESSION['auth_code_attempts'] = 0;
    $_SESSION['last_code_request'] = $now;
    $_SESSION['pending_login'] = true;

    try {
        sendAuthMail($code, originLocation());
    } catch (Throwable $e) {
        unset($_SESSION['auth_code_hash'], $_SESSION['auth_code_expires'], $_SESSION['pending_login']);
        error_log('Spotify admin 2FA mail failed: ' . $e->getMessage());
        out(false, '2FA email could not be sent. Check the server SMTP configuration.');
    }
    out(true, 'AUTH CODE REQUESTED');
}

if ($action === 'verify') {
    if (empty($_SESSION['pending_login']) || empty($_SESSION['auth_code_hash'])) out(false, 'No active authentication request.');
    if (time() > (int)($_SESSION['auth_code_expires'] ?? 0)) out(false, 'AUTH CODE EXPIRED');
    if ((int)($_SESSION['auth_code_attempts'] ?? 0) >= MAX_CODE_ATTEMPTS) out(false, 'TOO MANY ATTEMPTS');

    $_SESSION['auth_code_attempts']++;
    $code = trim((string)($data['code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code) || !hash_equals((string)$_SESSION['auth_code_hash'], hash('sha256', $code))) out(false, 'INVALID AUTH CODE');

    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    unset($_SESSION['pending_login'], $_SESSION['auth_code_hash'], $_SESSION['auth_code_expires'], $_SESSION['auth_code_attempts']);
    out(true, 'AUTHENTICATED', ['authenticated' => true]);
}

if ($action === 'check') {
    if (empty($_SESSION['authenticated'])) out(false, 'UNAUTHORIZED');
    out(true, 'AUTHORIZED', ['authenticated' => true]);
}

out(false, 'Unknown action.');
