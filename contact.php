<?php
/**
 * DaranX — contact form handler (self-hosted, no third-party service).
 *
 * Receives the POST from the contact form on index.html, validates it, and
 * emails it to the company inbox. Works both with JavaScript (returns JSON)
 * and without it (redirects back to the page with ?sent=1 / ?sent=0).
 *
 * ─────────────────────────────────────────────────────────────────────────
 *  SET THESE TWO VALUES for your hosting, then upload next to index.html:
 * ─────────────────────────────────────────────────────────────────────────
 */
$TO   = 'info@daranx.com';       // ← inbox that should RECEIVE messages
$FROM = 'no-reply@daranx.com';   // ← a mailbox/alias ON YOUR OWN DOMAIN
                                 //   (needed so mail passes SPF/DKIM and is
                                 //    not marked as spam). e.g. no-reply@daranx.ir
$SUBJECT_PREFIX = 'پیام تماس سایت DaranX';
$REDIRECT_TO    = 'index.html#contact'; // where the no-JS fallback returns to
// ─────────────────────────────────────────────────────────────────────────

/** Is this an AJAX/fetch request that expects JSON back? */
function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false || $xrw !== '';
}

/** Finish the request: JSON for fetch, redirect for a plain form post. */
function respond(bool $ok, string $error = ''): void {
    global $REDIRECT_TO;
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 422);
        echo json_encode(['ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ' . $REDIRECT_TO . ($ok ? '?sent=1' : '?sent=0'));
        http_response_code(303);
    }
    exit;
}

/** Strip CR/LF so user input can never inject extra mail headers. */
function clean_header(string $v): string {
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], '', $v));
}

/** RFC 2047 encode a UTF-8 subject line so Persian renders in mail clients. */
function encode_subject(string $s): string {
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

// --- Only accept POST -------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(false, 'روش درخواست نامعتبر است.');
}

// --- Only from our own pages: a browser posting from another site sends its
// own Origin, so cross-site form spam is refused (no Origin = old browser, ok).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $oh = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $me = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if ($oh === '' || preg_replace('/^www\./', '', $oh) !== preg_replace('/^www\./', '', $me)) {
        respond(false, 'درخواست نامعتبر است.');
    }
}

// --- Honeypot: real users never fill "website" ------------------------------
if (!empty($_POST['website'])) {
    // Pretend success so bots don't learn they were caught.
    respond(true);
}

// --- Too fast to be human: the page's script sends how many seconds the form
// was open ("el"). A person needs more than 3; bots that run the script don't.
if (isset($_POST['el']) && (int) $_POST['el'] < 3) {
    respond(true);
}

// --- Per-IP throttle: 20 s between messages and at most 5 per hour
// (best-effort; skipped if the temp dir isn't writable).
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$throttle = sys_get_temp_dir() . '/daranx_cf_' . md5($ip);
$now = time();
$sent = [];
if (is_file($throttle)) {
    $sent = array_values(array_filter(
        array_map('intval', explode(',', (string) @file_get_contents($throttle))),
        fn($t) => $t > $now - 3600
    ));
}
if ($sent && $now - max($sent) < 20) {
    respond(false, 'کمی صبر کنید و دوباره ارسال کنید.');
}
if (count($sent) >= 5) {
    respond(false, 'تعداد پیام‌ها از این دستگاه زیاد شده است. لطفاً تلفنی تماس بگیرید.');
}

// --- Read + validate fields -------------------------------------------------
$name    = clean_header($_POST['name']    ?? '');
$phone   = clean_header($_POST['phone']   ?? '');
$email   = clean_header($_POST['email']   ?? '');
$company = clean_header($_POST['company'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if (mb_strlen($name) < 2)     { $errors[] = 'نام'; }
if (mb_strlen($phone) < 4)    { $errors[] = 'شماره تماس'; }
if (mb_strlen($message) < 5)  { $errors[] = 'پیام'; }
if ($errors) {
    respond(false, 'این فیلدها لازم‌اند: ' . implode('، ', $errors) . '.');
}
if (mb_strlen($message) > 5000) {
    respond(false, 'پیام بیش از حد طولانی است.');
}
// Link spam: real enquiries rarely carry more than a couple of links.
if (preg_match_all('~https?://|www\.~i', $message . ' ' . $company) > 3) {
    respond(false, 'لطفاً پیام را بدون لینک‌های متعدد بفرستید.');
}

$valid_email = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';

// --- Build the email --------------------------------------------------------
$subject = encode_subject($SUBJECT_PREFIX . ' — ' . $name);

$body  = "پیام جدید از فرم تماس سایت DaranX\n";
$body .= str_repeat('—', 24) . "\n";
$body .= "نام:      {$name}\n";
$body .= "تلفن:     {$phone}\n";
$body .= "ایمیل:    " . ($email !== '' ? $email : '—') . "\n";
$body .= "سازمان:   " . ($company !== '' ? $company : '—') . "\n";
$body .= str_repeat('—', 24) . "\n";
$body .= "پیام:\n{$message}\n";
$body .= str_repeat('—', 24) . "\n";
$body .= 'زمان: ' . date('Y-m-d H:i:s') . "\n";
$body .= 'IP:   ' . $ip . "\n";

$headers   = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$headers[] = 'From: DaranX Website <' . $FROM . '>';
// The display name is encoded, so characters like < , or " in it can never
// add or change addresses.
$headers[] = 'Reply-To: ' . ($valid_email !== ''
    ? encode_subject($name) . ' <' . $valid_email . '>'
    : $FROM);

// Count this message toward the throttle only now that it is valid, so fixing
// a typo and resending is never blocked.
$sent[] = $now;
@file_put_contents($throttle, implode(',', $sent), LOCK_EX);

$ok = @mail($TO, $subject, $body, implode("\r\n", $headers), '-f' . $FROM);

respond((bool) $ok, $ok ? '' : 'ارسال ایمیل ناموفق بود. لطفاً تلفنی تماس بگیرید.');
