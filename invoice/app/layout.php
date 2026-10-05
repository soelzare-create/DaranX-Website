<?php
/**
 * Page chrome: <head>, top navigation, flash messages, error pages.
 */
defined('APP_DIR') || exit;

const NAV = [
    'home' => ['داشبورد', []],
    'qot' => ['پیش‌فاکتورها', ['p' => 'docs', 'type' => 'qot']],
    'inv' => ['فاکتورها', ['p' => 'docs', 'type' => 'inv']],
    'customers' => ['مشتریان', ['p' => 'customers']],
    'payments' => ['دریافت‌ها', ['p' => 'payments']],
    'purchases' => ['خریدها', ['p' => 'purchases']],
    'expenses' => ['پرداخت‌ها', ['p' => 'expenses']],
    'assistant' => ['دستیار', ['p' => 'assistant']],
    'import' => ['ورود از اکسل', ['p' => 'import']],
    'settings' => ['تنظیمات', ['p' => 'settings']],
];

function asset(string $file): string
{
    return 'assets/' . $file . '?v=' . APP_VERSION;
}

/**
 * Inline SVG icon from assets/icons (Phosphor Icons, regular weight, MIT —
 * see assets/icons/LICENSE-phosphor.txt). Inline so it inherits currentColor
 * and needs no extra request; decorative, so hidden from screen readers.
 */
function icon(string $name, string $class = 'ic'): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = ROOT_DIR . '/assets/icons/' . basename($name) . '.svg';
        $svg = is_file($file) ? (string) file_get_contents($file) : '';
        $cache[$name] = preg_replace('/^<svg /', '<svg aria-hidden="true" focusable="false" ', trim($svg)) ?? '';
    }
    return $cache[$name] === '' ? '' : preg_replace('/^<svg /', '<svg class="' . e($class) . '" ', $cache[$name]);
}

/** A composed empty state: icon, one line of text, and optionally the next step. */
function empty_state(string $iconName, string $text, string $actionHtml = ''): string
{
    return '<div class="empty-state">' . icon($iconName, 'ic ic-lg') . '<p>' . e($text) . '</p>'
        . ($actionHtml !== '' ? '<div class="actions">' . $actionHtml . '</div>' : '') . '</div>';
}

function html_head(string $title, array $css = ['app.css']): void
{
    ?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<?php foreach ($css as $f): ?>
<link rel="stylesheet" href="<?= e(asset($f)) ?>">
<?php endforeach; ?>
<script src="<?= e(asset('app.js')) ?>"></script>
</head>
<?php
}

/**
 * Open a logged-in page. $opt: sheet (bool) = document editor page,
 * error (string) = extra error message shown with the flashes.
 */
function layout_start(string $title, string $active = '', array $opt = []): void
{
    $sheet = !empty($opt['sheet']);
    html_head($title . ' | DaranX', $sheet ? ['app.css', 'sheet.css'] : ['app.css']);
    echo '<body class="' . ($sheet ? 'sheet-page' : '') . '"' . ($opt['body_attrs'] ?? '') . ">\n";
    $user = current_user();
    ?>
<nav class="nav no-print">
  <div class="in">
    <a class="brand" href="index.php">Daran<span class="x">X</span></a>
    <div class="links">
      <?php foreach (NAV as $key => [$label, $q]): ?>
        <a href="<?= e(url($q['p'] ?? 'home', array_diff_key($q, ['p' => 1]))) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="nav-end">
      <?php if ($user): ?>
        <form method="post" action="<?= e(url('logout')) ?>">
          <?= csrf_field() ?>
          <button class="linkbtn" title="<?= e($user['username']) ?>"><?= icon('sign-out') ?><span>خروج</span></button>
        </form>
      <?php endif; ?>
      <button type="button" class="theme-toggle" id="tt" aria-label="روشن/تیره" title="روشن/تیره"><?= icon('circle-half') ?></button>
    </div>
  </div>
</nav>
<?php
    flashes($opt['error'] ?? null);
}

function flashes(?string $error = null): void
{
    $list = take_flashes();
    if ($error) {
        $list[] = ['err', $error];
    }
    if (!$list) {
        return;
    }
    echo '<div class="flashes no-print">';
    foreach ($list as [$type, $msg]) {
        $ok = $type === 'ok';
        echo '<div class="flash ' . ($ok ? 'ok' : 'err') . '" role="status">'
            . icon($ok ? 'check-circle' : 'warning-circle') . '<span>' . e($msg) . '</span></div>';
    }
    echo '</div>';
}

function layout_end(array $scripts = []): void
{
    foreach ($scripts as $s) {
        echo '<script src="' . e(asset($s)) . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

/** Centered card used by the login and first-run setup screens. */
function auth_start(string $title, string $subtitle): void
{
    html_head($title . ' | DaranX');
    ?>
<body>
<main class="auth">
  <div class="auth-card">
    <div class="auth-hd">
      <img src="<?= e(asset('logo.svg')) ?>" alt="DaranX">
      <h1><?= e($title) ?></h1>
      <p><?= e($subtitle) ?></p>
    </div>
    <div class="auth-bd">
<?php
    flashes();
}

function auth_end(): void
{
    echo "    </div>\n  </div>\n</main>\n";
    layout_end();
}

/** Minimal standalone error page (works before the DB is available). */
function fatal(string $msg, int $code = 0): void
{
    if ($code && !headers_sent()) {
        http_response_code($code);
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>DaranX</title>'
        . '<link rel="stylesheet" href="' . e(asset('app.css')) . '"></head><body>'
        . '<main class="page"><div class="card"><h2>پیام سیستم</h2><p style="white-space:pre-wrap">'
        . e($msg) . '</p><p><a class="btn btn-ghost" href="index.php">بازگشت به برنامه</a></p></div></main></body></html>';
    exit;
}

function not_found(): void
{
    fatal('صفحه یا موردی که دنبالش هستید پیدا نشد.', 404);
}

/** Shared fields of the add/edit customer form. */
function customer_fields(array $c): void
{
    // From the DB the balance is a signed number; after a failed submit it is
    // the raw typed text plus the chosen side.
    $raw = $c['opening_balance'] ?? '';
    $creditor = ($c['opening_side'] ?? '') === 'creditor';
    if (is_string($raw) && $raw !== '' && !is_numeric($raw)) {
        $obText = $raw;
    } else {
        $ob = (float) $raw;
        $obText = $ob ? money($ob) : '';
        $creditor = $creditor || $ob < 0;
    }
    ?>
  <div class="fgrid">
    <label class="field"><span>نام شخص یا شرکت</span>
      <input name="name" value="<?= e($c['name'] ?? '') ?>" required maxlength="150"></label>
    <label class="field"><span>شماره تماس</span>
      <input name="phone" value="<?= e($c['phone'] ?? '') ?>" maxlength="80"></label>
  </div>
  <label class="field"><span>آدرس</span>
    <textarea name="address" rows="2" maxlength="400"><?= e($c['address'] ?? '') ?></textarea></label>
  <div class="fgrid">
    <label class="field"><span>مانده از قبل <small>(اگر قبل از این برنامه حساب داشته)</small></span>
      <input name="opening_balance" class="money" inputmode="decimal" value="<?= e($obText) ?>" placeholder="۰"></label>
    <label class="field"><span>نوع مانده</span>
      <select name="opening_side">
        <option value="debtor"<?= $creditor ? '' : ' selected' ?>>بدهکار (مشتری به ما بدهکار است)</option>
        <option value="creditor"<?= $creditor ? ' selected' : '' ?>>بستانکار (ما به مشتری بدهکاریم)</option>
      </select></label>
  </div>
  <label class="field"><span>یادداشت</span>
    <textarea name="note" rows="2" maxlength="1000"><?= e($c['note'] ?? '') ?></textarea></label>
<?php
}

/** «INV-0003 — customer» label for an invoice in a <select>. */
function invoice_option_label(array $d): string
{
    return $d['number'] . '، ' . $d['cust_name'] . '، ' . money($d['total'])
        . ($d['status'] === 'cancelled' ? ' (باطل‌شده)' : '');
}

/** Shared fields of the add/edit purchase form. $f holds display strings. */
function purchase_fields(array $f, array $invoices, array $suppliers): void
{
    ?>
  <div class="fgrid">
    <label class="field"><span>فاکتور مربوط</span>
      <select name="doc_id" required>
        <option value="">انتخاب فاکتور…</option>
        <?php foreach ($invoices as $d): ?>
          <option value="<?= (int) $d['id'] ?>"<?= (string) $d['id'] === (string) ($f['doc_id'] ?? '') ? ' selected' : '' ?>><?= e(invoice_option_label($d)) ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="field"><span>تأمین‌کننده <small>(فروشنده)</small></span>
      <input name="supplier" value="<?= e($f['supplier'] ?? '') ?>" list="supList" maxlength="150" autocomplete="off">
      <datalist id="supList"><?php foreach ($suppliers as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></label>
  </div>
  <label class="field"><span>شرح خرید <small>(چه چیزی خریده شد)</small></span>
    <input name="title" value="<?= e($f['title'] ?? '') ?>" required maxlength="300"></label>
  <div class="fgrid">
    <label class="field"><span>مبلغ خرید (<?= e(setting('unit')) ?>)</span>
      <input name="amount" class="money" inputmode="decimal" value="<?= e($f['amount'] ?? '') ?>" required placeholder="۰"></label>
    <label class="field"><span>تاریخ</span>
      <input name="date" value="<?= e($f['date'] ?? '') ?>" required placeholder="۱۴۰۵/۰۱/۰۱"></label>
  </div>
  <label class="field"><span>توضیح <small>(اختیاری؛ مثلاً شماره فاکتور فروشنده)</small></span>
    <input name="note" value="<?= e($f['note'] ?? '') ?>" maxlength="300"></label>
<?php
}

/**
 * Shared fields of the add/edit expense (payment out) form. The direct-only
 * part (invoice + purchase) is hidden by app.js when «سربار» is chosen; the
 * server ignores it for overhead either way.
 */
function expense_fields(array $f, array $invoices, array $purchases, array $payees): void
{
    $kind = ($f['kind'] ?? 'direct') === 'overhead' ? 'overhead' : 'direct';
    ?>
  <div class="field"><span>نوع پرداخت</span>
    <div class="kind-pick">
      <?php foreach (EXPENSE_KINDS as $k => $label): ?>
        <label class="chk"><input type="radio" name="kind" value="<?= e($k) ?>"<?= $k === $kind ? ' checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="fgrid direct-only"<?= $kind === 'overhead' ? ' hidden' : '' ?>>
    <label class="field"><span>فاکتور مربوط</span>
      <select name="doc_id">
        <option value="">انتخاب فاکتور…</option>
        <?php foreach ($invoices as $d): ?>
          <option value="<?= (int) $d['id'] ?>"<?= (string) $d['id'] === (string) ($f['doc_id'] ?? '') ? ' selected' : '' ?>><?= e(invoice_option_label($d)) ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="field"><span>بابت خرید <small>(اختیاری)</small></span>
      <select name="purchase_id">
        <option value="">بدون خرید (هزینهٔ دیگر، مثل پیک)</option>
        <?php foreach ($purchases as $p): ?>
          <option value="<?= (int) $p['id'] ?>" data-doc="<?= (int) $p['doc_id'] ?>" data-supplier="<?= e($p['supplier']) ?>"
            <?= (string) $p['id'] === (string) ($f['purchase_id'] ?? '') ? ' selected' : '' ?>><?= e($p['number'] . '، ' . $p['title']
              . ($p['supplier'] !== '' ? ' (' . $p['supplier'] . ')' : '') . '، ' . $p['doc_number']
              . (purchase_left($p) >= 0.5 ? '، مانده ' . money(purchase_left($p)) : '، پرداخت‌شده')) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">پرداخت بابت یک خرید، همان خرید را تسویه می‌کند و هزینهٔ جدیدی به فاکتور اضافه نمی‌کند.</small></label>
  </div>
  <div class="fgrid">
    <label class="field"><span>بابت <small>(دستهٔ هزینه)</small></span>
      <input name="category" value="<?= e($f['category'] ?? '') ?>" list="<?= $kind === 'overhead' ? 'catOverhead' : 'catDirect' ?>"
             maxlength="80" autocomplete="off" placeholder="<?= $kind === 'overhead' ? 'مثلاً اجاره' : 'مثلاً پیک' ?>">
      <datalist id="catDirect"><?php foreach (DIRECT_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <datalist id="catOverhead"><?php foreach (OVERHEAD_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></label>
    <label class="field"><span>پرداخت به <small>(گیرنده)</small></span>
      <input name="payee" value="<?= e($f['payee'] ?? '') ?>" list="payeeList" maxlength="150" autocomplete="off">
      <datalist id="payeeList"><?php foreach ($payees as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></label>
  </div>
  <div class="fgrid">
    <label class="field"><span>مبلغ (<?= e(setting('unit')) ?>)</span>
      <input name="amount" class="money" inputmode="decimal" value="<?= e($f['amount'] ?? '') ?>" required placeholder="۰"></label>
    <label class="field"><span>تاریخ</span>
      <input name="date" value="<?= e($f['date'] ?? '') ?>" required placeholder="۱۴۰۵/۰۱/۰۱"></label>
    <label class="field"><span>روش پرداخت</span>
      <select name="method">
        <?php foreach (PAY_METHODS as $m): ?>
          <option<?= $m === ($f['method'] ?? '') ? ' selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select></label>
  </div>
  <label class="field"><span>توضیح <small>(اختیاری)</small></span>
    <input name="note" value="<?= e($f['note'] ?? '') ?>" maxlength="300"></label>
<?php
}
