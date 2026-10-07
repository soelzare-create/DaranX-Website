<?php
/**
 * Login gate for /merchandising/ (everything under this folder is served through here).
 * .htaccess sends every request to this file; it checks a session and then streams the
 * requested static file. Credentials: user below + a bcrypt hash (no plain-text password).
 * To change them: php -r 'echo password_hash("NEW", PASSWORD_BCRYPT, ["cost"=>12]);'
 */
declare(strict_types=1);

const USER_NAME = 'Daran';
const PASS_HASH = '$2y$12$9psGVxiqY.opVw9YieB4A.p/kPx0sW5.3i2qlj.GmVeMZnTNWvqga';
const SESSION_NAME = 'dx_merch';
const BASE = '/merchandising/';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name(SESSION_NAME);
session_set_cookie_params(['lifetime' => 0, 'path' => BASE, 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? BASE, PHP_URL_PATH) ?: BASE;
$rel = rawurldecode(substr($uri, strlen(BASE)));

if (($_GET['logout'] ?? '') === '1') {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE);
    exit;
}

$error = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['u'], $_POST['p'])) {
    $ok = hash_equals(USER_NAME, (string)$_POST['u']) & password_verify((string)$_POST['p'], PASS_HASH);
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        header('Location: ' . $uri);
        exit;
    }
    sleep(1); // slows down guessing
    $error = true;
}

if (empty($_SESSION['ok'])) {
    // Only the login page for visitors; never any file content.
    header('Cache-Control: no-store');
    http_response_code($error ? 401 : 200);
    login_page($error);
    exit;
}

// ---- signed in: serve the requested static file -----------------------------------
$root = realpath(__DIR__);
$path = $root . '/' . $rel;
if (is_dir($path)) { $path = rtrim($path, '/') . '/index.html'; }
$real = realpath($path);
$name = $real ? basename($real) : '';
if (!$real || strncmp($real, $root . '/', strlen($root) + 1) !== 0 || !is_file($real)
    || $name[0] === '.' || preg_match('/\.(php|phtml|htaccess|htpasswd)$/i', $name)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}
$types = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp', 'woff2' => 'font/woff2', 'dxf' => 'application/dxf', 'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8'];
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($real));
readfile($real);
exit;

function login_page(bool $error): void
{
    $msg = $error ? '<p class="err" role="alert">نام کاربری یا رمز عبور درست نیست.</p>' : '';
    echo <<<HTML
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>ورود — Merchandising — DaranX</title>
<style>
@font-face{font-family:"Vazirmatn";src:url("/assets/fonts/Vazirmatn-VF.woff2") format("woff2");font-weight:100 900;font-display:swap}
:root{--navy:#153A62;--steel:#3E7BB6;--ground:#F3F6FA;--surface:#fff;--ink:#12233A;--soft:#43566E;--line:#C2D0E0}
@media (prefers-color-scheme:dark){:root{--ground:#081320;--surface:#0F2338;--ink:#E9F0F8;--soft:#A6BACF;--line:#2A4666;--steel:#5B95CE}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;font-family:Vazirmatn,system-ui,sans-serif;
 background:linear-gradient(240deg,#0E2749 0%,#153A62 55%,#2E6098 100%);color:var(--ink)}
.card{width:100%;max-width:380px;background:var(--surface);border-radius:20px;padding:28px 26px;box-shadow:0 20px 50px rgba(0,0,0,.3)}
.logo{display:block;height:34px;margin:0 auto 14px}
@media (prefers-color-scheme:dark){.logo{filter:brightness(0) invert(1)}}
h1{font-size:1.25rem;text-align:center;margin:0 0 4px}p.s{color:var(--soft);text-align:center;margin:0 0 18px;font-size:.92rem}
label{display:block;font-weight:700;font-size:.9rem;margin:12px 0 5px}
input{width:100%;font:inherit;padding:.65rem .8rem;border:1px solid var(--line);border-radius:10px;background:transparent;color:var(--ink);direction:ltr;text-align:left}
input:focus{outline:2px solid var(--steel);outline-offset:1px}
button{width:100%;margin-top:18px;font:inherit;font-weight:800;padding:.75rem;border:0;border-radius:12px;background:var(--steel);color:#fff;cursor:pointer}
button:hover{filter:brightness(1.08)}.err{color:#B42318;background:#FEF3F2;border-radius:10px;padding:.5rem .8rem;margin:0 0 6px;text-align:center;font-size:.9rem}
@media (prefers-color-scheme:dark){.err{color:#F97066;background:#2a1517}}
@media (prefers-reduced-motion:no-preference){.card{animation:up .5s ease both}@keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}}
</style></head><body>
<form class="card" method="post" autocomplete="on">
<img class="logo" src="/assets/daranx-logo.svg" alt="DaranX">
<h1>Merchandising</h1><p class="s">برای دیدن گزارش‌ها وارد شوید.</p>

<label for="u">نام کاربری</label><input id="u" name="u" autocomplete="username" autocapitalize="none" required autofocus>
<label for="p">رمز عبور</label><input id="p" name="p" type="password" autocomplete="current-password" required>
<button type="submit">ورود</button>
</form></body></html>
HTML;
}
