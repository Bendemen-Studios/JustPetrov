<?php

declare(strict_types=1);

// Shared JustPetrov admin authentication. Credentials remain in the server .env.
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) $value = substr($value, 1, -1);
        if (($value[0] ?? '') === "'" && str_ends_with($value, "'")) $value = substr($value, 1, -1);
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}

session_name('jp_admin');
session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Strict','path'=>'/admin/']);
session_start();
if (!empty($_SESSION['authenticated']) && !empty($_SESSION['authenticated_expires']) && time() >= (int) $_SESSION['authenticated_expires']) {
    $_SESSION = [];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const USERNAME = 'bendemen';
const CODE_TTL = 600;
const LOGIN_TTL = 43200;
const MAX_CODE_ATTEMPTS = 5;
const SMTP_HOST = 'JustPetrov.com';
const SMTP_PORT = 465;
const SMTP_USER = 'automail@justpetrov.com';
const ADMIN_EMAIL = 'ben@justpetrov.com';
const FROM_NAME = 'Automail | JustPetrov';
const QUOTA_LOG = __DIR__ . '/../stats/quota-log.json';

function out(bool $ok,string $message='',array $extra=[]): never { http_response_code($ok?200:400); echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_SLASHES); exit; }
function input(): array { $d=json_decode(file_get_contents('php://input') ?: '{}',true); return is_array($d)?$d:[]; }
function clientIp(): string {
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $forwarded) $candidates[] = trim($forwarded);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return 'unknown';
}

function originLocation(): string {
    $ip = clientIp();
    if ($ip === 'unknown') return 'Unknown';

    $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
    $ch = curl_init($url);
    if ($ch === false) return 'Unknown';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: JustPetrov-Admin/1.0'],
    ]);
    $json = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($json) || $status < 200 || $status >= 300) return 'Unknown';
    $d = json_decode($json, true);
    if (!is_array($d) || !empty($d['error'])) return 'Unknown';

    $city = trim((string)($d['city'] ?? ''));
    $country = trim((string)($d['country_name'] ?? ''));
    return trim($country . ($city !== '' && $country !== '' ? ' — ' : '') . $city) ?: 'Unknown';
}
function smtpRead($fp): string { $r=''; while(($line=fgets($fp,515))!==false){$r.=$line;if(strlen($line)<4||$line[3]!=='-')break;}return$r; }
function smtpExpect($fp,int $code): void { $r=smtpRead($fp);if((int)substr($r,0,3)!==$code)throw new RuntimeException('SMTP error: '.trim($r)); }
function smtpCommand($fp,string $command,int $code): void { fwrite($fp,$command."\r\n");smtpExpect($fp,$code); }
function sendAuthMail(string $code,string $location): void {
    $password = getenv('SMTP_PASS') ?: '';
    if ($password === '') throw new RuntimeException('SMTP_PASS is not configured on the server.');

    $headerPath = dirname(__DIR__) . '/assets/automail-header.png';
    $signaturePath = dirname(__DIR__) . '/assets/automail-signature.png';
    if (!is_readable($headerPath) || !is_readable($signaturePath)) {
        throw new RuntimeException('Automail header/signature image is missing or unreadable.');
    }

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
        smtpCommand($fp, 'RCPT TO:<' . ADMIN_EMAIL . '>', 250);

        fwrite($fp, "DATA\r\n");
        smtpExpect($fp, 354);

        $safeLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $body = "Reden: Admin Toegang\r\n"
            . "Page: Admin Dashboard Login\r\n"
            . "Origin: " . $location . "\r\n\r\n"
            . "Your authentication code is: " . $code . "\r\n"
            . "This code expires in 10 minutes.\r\n";

        $headerCid = 'automail-header';
        $signatureCid = 'automail-signature';

        $htmlBody = '<div style="font-family:Arial,sans-serif;color:#111;line-height:1.5">'
            . '<p style="margin:0 0 20px"><img src="cid:' . $headerCid . '" alt="Automail" width="100%" style="display:block;width:100%;max-width:100%;height:auto"></p>'
            . '<p>Reden: Admin Toegang<br>Page: Admin Dashboard Login<br>Origin: ' . $safeLocation . '</p>'
            . '<p><strong>Your authentication code is: ' . $safeCode . '</strong><br>This code expires in 10 minutes.</p>'
            . '<p style="margin:28px 0 0"><img src="cid:' . $signatureCid . '" alt="Automail signature" width="100%" style="display:block;width:100%;max-width:100%;height:auto"></p>'
            . '</div>';

        $boundary = '=_JustPetrov_' . bin2hex(random_bytes(8));
        $headerData = base64_encode((string) file_get_contents($headerPath));
        $signatureData = base64_encode((string) file_get_contents($signaturePath));

        $message = "From: " . FROM_NAME . " <" . SMTP_USER . ">\r\n"
            . "To: " . ADMIN_EMAIL . "\r\n"
            . "Subject: Admin Code Requested\r\n"
            . "Date: " . date(DATE_RFC2822) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/related; boundary=\"" . $boundary . "\"\r\n"
            . "\r\n"
            . "--" . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n"
            . "--" . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--" . $boundary . "\r\n"
            . "Content-Type: image/png; name=\"automail-header.png\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-ID: <" . $headerCid . ">\r\n"
            . "Content-Disposition: inline; filename=\"automail-header.png\"\r\n\r\n"
            . $headerData . "\r\n"
            . "--" . $boundary . "\r\n"
            . "Content-Type: image/png; name=\"automail-signature.png\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-ID: <" . $signatureCid . ">\r\n"
            . "Content-Disposition: inline; filename=\"automail-signature.png\"\r\n\r\n"
            . $signatureData . "\r\n"
            . "--" . $boundary . "--\r\n";

        fwrite($fp, $message . "\r\n.\r\n");
        smtpExpect($fp, 250);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

$action=(string)($_GET['action']??'');$data=input();$passwordHash=getenv('ADMIN_PASSWORD_HASH')?:'';
if($action==='status'){if(!empty($_SESSION['authenticated']) && !empty($_SESSION['authenticated_expires']) && time() < (int)$_SESSION['authenticated_expires'])out(true,'',['authenticated'=>true,'expiresAt'=>(int)$_SESSION['authenticated_expires']]);out(true,'',['authenticated'=>false]);}
if($action==='logout'){$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy();out(true,'Logged out.');}
if($action==='request'){if($passwordHash==='')out(false,'ADMIN_PASSWORD_HASH is not configured on the server.');$user=(string)($data['username']??'');$pass=(string)($data['password']??'');if(!hash_equals(USERNAME,$user)||!hash_equals($passwordHash,hash('sha256',$pass))){usleep(250000);out(false,'INVALID LOGIN');}$now=time();$last=(int)($_SESSION['last_code_request']??0);if($last&&$now-$last<30)out(false,'Please wait before requesting another code.');$code=(string)random_int(100000,999999);$_SESSION['auth_code_hash']=hash('sha256',$code);$_SESSION['auth_code_expires']=$now+CODE_TTL;$_SESSION['auth_code_attempts']=0;$_SESSION['last_code_request']=$now;$_SESSION['pending_login']=true;try{sendAuthMail($code,originLocation());}catch(Throwable $e){unset($_SESSION['auth_code_hash'],$_SESSION['auth_code_expires'],$_SESSION['pending_login']);error_log('Admin 2FA mail failed: '.$e->getMessage());out(false,'2FA email could not be sent. Check the server SMTP configuration.');}out(true,'AUTH CODE REQUESTED');}
if($action==='verify'){if(empty($_SESSION['pending_login'])||empty($_SESSION['auth_code_hash']))out(false,'No active authentication request.');if(time()>(int)($_SESSION['auth_code_expires']??0))out(false,'AUTH CODE EXPIRED');if((int)($_SESSION['auth_code_attempts']??0)>=MAX_CODE_ATTEMPTS)out(false,'TOO MANY ATTEMPTS');$_SESSION['auth_code_attempts']++;$code=trim((string)($data['code']??''));if(!preg_match('/^\d{6}$/',$code)||!hash_equals((string)$_SESSION['auth_code_hash'],hash('sha256',$code)))out(false,'INVALID AUTH CODE');session_regenerate_id(true);$_SESSION['authenticated']=true;$_SESSION['authenticated_at']=time();$_SESSION['authenticated_expires']=time()+LOGIN_TTL;unset($_SESSION['pending_login'],$_SESSION['auth_code_hash'],$_SESSION['auth_code_expires'],$_SESSION['auth_code_attempts']);out(true,'AUTHENTICATED',['authenticated'=>true,'expiresIn'=>LOGIN_TTL]);}
if($action==='check'){if(empty($_SESSION['authenticated'])||empty($_SESSION['authenticated_expires'])||time()>=(int)$_SESSION['authenticated_expires']){$_SESSION=[];out(false,'UNAUTHORIZED');}out(true,'AUTHORIZED',['authenticated'=>true,'expiresAt'=>(int)$_SESSION['authenticated_expires']]);}
if($action==='quota_log'){if(empty($_SESSION['authenticated']))out(false,'UNAUTHORIZED');$log=[];if(is_readable(QUOTA_LOG)){$decoded=json_decode(file_get_contents(QUOTA_LOG)?:'[]',true);if(is_array($decoded))$log=$decoded;}$total=count($log);
$log=array_reverse(array_values(array_slice($log,-20)));
out(true,'',['logs'=>$log,'count'=>count($log),'total'=>$total]);}
out(false,'Unknown action.');
