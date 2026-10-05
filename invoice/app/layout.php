<?php
/**
 * Page chrome: <head>, top navigation, flash messages, error pages.
 */
defined('APP_DIR') || exit;

// key => [label, query, what it needs: null | 'admin' | [area, level], icon, group, short label for the phone tab bar]
const NAV = [
    'home' => ['داشبورد', [], null, 'house', '', 'خانه'],
    'qot' => ['پیش‌فاکتورها', ['p' => 'docs', 'type' => 'qot'], ['sales', 'view'], 'file-text', 'فروش', 'پیش‌فاکتور'],
    'inv' => ['فاکتورها', ['p' => 'docs', 'type' => 'inv'], ['sales', 'view'], 'receipt', 'فروش', 'فاکتور'],
    'customers' => ['مشتریان', ['p' => 'customers'], ['sales', 'view'], 'users', 'فروش', 'مشتریان'],
    'payments' => ['دریافت‌ها', ['p' => 'payments'], ['sales', 'view'], 'arrow-circle-down', 'فروش', 'دریافت'],
    'purchases' => ['خریدها', ['p' => 'purchases'], ['costs', 'view'], 'shopping-cart', 'خرید و هزینه', 'خرید'],
    'expenses' => ['پرداخت‌ها', ['p' => 'expenses'], ['costs', 'view'], 'arrow-circle-up', 'خرید و هزینه', 'پرداخت'],
    'assistant' => ['دستیار', ['p' => 'assistant'], ['assistant', 'use'], 'chat-circle-text', 'ابزارها', 'دستیار'],
    'import' => ['ورود از اکسل', ['p' => 'import'], ['sales', 'edit'], 'file-xls', 'ابزارها', 'اکسل'],
    'users' => ['کاربران', ['p' => 'users'], 'admin', 'user-gear', 'مدیریت', 'کاربران'],
    'settings' => ['تنظیمات', ['p' => 'settings'], null, 'gear-six', 'مدیریت', 'تنظیمات'],
];

/** Phone tab bar: the first four of these the user may open, then «منو». */
const TAB_ORDER = ['home', 'inv', 'qot', 'customers', 'purchases', 'expenses', 'assistant'];

function nav_allowed($need): bool
{
    if ($need === null) {
        return true;
    }
    return $need === 'admin' ? is_admin() : can($need[0], $need[1]);
}

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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#F3F5F9">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0A1522">
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
    echo '<body class="shell' . ($sheet ? ' sheet-page' : '') . '"' . ($opt['body_attrs'] ?? '') . ">\n";
    $user = current_user();
    $items = [];
    foreach (NAV as $key => [$label, $q, $need, $ic, $group, $short]) {
        if ($user && nav_allowed($need)) {
            $items[$key] = [
                'label' => $key === 'settings' && !is_admin() ? 'حساب من' : $label,
                'short' => $key === 'settings' && !is_admin() ? 'حساب من' : $short,
                'href' => url($q['p'] ?? 'home', array_diff_key($q, ['p' => 1])),
                'icon' => $ic, 'group' => $group,
            ];
        }
    }
    $tabs = array_slice(array_values(array_filter(TAB_ORDER, function ($k) use ($items) {
        return isset($items[$k]);
    })), 0, 4);
    $cur = function (string $key) use ($active): string {
        return $key === $active ? ' aria-current="page"' : '';
    };
    ?>
<a class="skip" href="#main">رفتن به محتوا</a>
<aside class="side no-print" id="side" aria-label="منوی اصلی" data-menu>
  <div class="side-hd">
    <a class="brand" href="index.php">Daran<span class="x">X</span></a>
    <button type="button" class="icon-btn side-close" data-menu-close aria-label="بستن منو"><?= icon('x') ?></button>
  </div>
  <nav class="side-nav">
    <?php $group = null; foreach ($items as $key => $it): ?>
      <?php if ($it['group'] !== $group): $group = $it['group']; ?>
        <?php if ($group !== ''): ?><div class="side-group"><?= e($group) ?></div><?php endif; ?>
      <?php endif; ?>
      <a href="<?= e($it['href']) ?>"<?= $cur($key) ?>><?= icon($it['icon']) ?><span><?= e($it['label']) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($user): ?>
  <div class="side-ft">
    <a class="me" href="<?= e(url('settings')) ?>">
      <span class="rc-mono" aria-hidden="true"><?= e(str_cut(user_label($user), 1)) ?></span>
      <span class="me-txt"><b><?= e(user_label($user)) ?></b><small><?= e(is_admin($user) ? 'مدیر' : 'حساب من') ?></small></span>
    </a>
    <div class="side-tools">
      <button type="button" class="icon-btn" id="tt" aria-label="روشن یا تیره" title="روشن یا تیره"><?= icon('circle-half') ?></button>
      <form method="post" action="<?= e(url('logout')) ?>">
        <?= csrf_field() ?>
        <button class="icon-btn" aria-label="خروج از حساب" title="خروج"><?= icon('sign-out') ?></button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</aside>
<div class="scrim no-print" data-menu-close></div>

<header class="topbar no-print">
  <a class="brand" href="index.php">Daran<span class="x">X</span></a>
</header>

<div class="app-main" id="main">
<?php
    flashes($opt['error'] ?? null);
    if ($user && $tabs) {
        // Rendered here (before the page) so it is in the DOM early; CSS pins it to the bottom on phones.
        echo '<nav class="tabbar no-print" aria-label="دسترسی سریع">';
        foreach ($tabs as $k) {
            echo '<a href="' . e($items[$k]['href']) . '"' . $cur($k) . '>' . icon($items[$k]['icon'])
                . '<span>' . e($items[$k]['short']) . '</span></a>';
        }
        $inTabs = in_array($active, $tabs, true);
        echo '<button type="button" data-menu-open aria-controls="side" aria-expanded="false"'
            . (!$inTabs && $active !== '' ? ' aria-current="page"' : '') . '>' . icon('list') . '<span>منو</span></button>';
        echo '</nav>';
    }
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
    echo "</div>\n"; // .app-main
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
    echo "    </div>\n  </div>\n</main>\n</body>\n</html>\n";
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
/**
 * Purchase form: pick the invoice, tick the lines bought and give quantity and
 * unit purchase price for each (assets/app.js draws the lines and keeps the
 * total); an optional free line covers anything not on the invoice.
 * $f: doc_id, supplier, date, note, picked [pos => [qty, price]], extra_title, extra_amount.
 */
function purchase_fields(array $f, array $invoices, array $suppliers, int $exceptPurchase = 0): void
{
    $lines = !empty($f['doc_id']) ? invoice_buy_lines((int) $f['doc_id'], $exceptPurchase) : [];
    $hasExtra = ($f['extra_amount'] ?? '') !== '' || ($f['extra_title'] ?? '') !== '';
    ?>
  <div class="fgrid">
    <label class="field"><span>فاکتور فروش</span>
      <select name="doc_id" required data-buy-doc>
        <option value="">انتخاب فاکتور…</option>
        <?php foreach ($invoices as $d): ?>
          <option value="<?= (int) $d['id'] ?>"<?= (string) $d['id'] === (string) ($f['doc_id'] ?? '') ? ' selected' : '' ?>><?= e(invoice_option_label($d)) ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="field"><span>تأمین‌کننده <small>(فروشنده)</small></span>
      <input name="supplier" value="<?= e($f['supplier'] ?? '') ?>" list="supList" maxlength="150" autocomplete="off">
      <datalist id="supList"><?php foreach ($suppliers as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></label>
  </div>

  <div class="field">
    <span>اقلام خریداری‌شده <small>(هر قلمی را که خریده‌اید علامت بزنید و قیمت خرید را بنویسید)</small></span>
    <div class="buy-box" data-buy data-unit="<?= e(setting('unit')) ?>"
         data-url="<?= e(url('purchase_lines', ['except' => $exceptPurchase])) ?>"
         data-lines="<?= e(json_encode($lines, JSON_UNESCAPED_UNICODE)) ?>"
         data-picked="<?= e(json_encode((object) ($f['picked'] ?? []), JSON_UNESCAPED_UNICODE)) ?>">
      <p class="muted buy-empty">اول فاکتور را انتخاب کنید تا اقلامش اینجا بیاید.</p>
    </div>
    <details class="buy-extra"<?= $hasExtra ? ' open' : '' ?>>
      <summary>چیز دیگری هم خریدید که در فاکتور نیست؟</summary>
      <div class="fgrid">
        <label class="field"><span>شرح</span>
          <input name="extra_title" value="<?= e($f['extra_title'] ?? '') ?>" maxlength="200" placeholder="مثلاً کابل و لوازم جانبی نصب"></label>
        <label class="field"><span>مبلغ (<?= e(setting('unit')) ?>)</span>
          <input name="extra_amount" class="money" inputmode="decimal" value="<?= e($f['extra_amount'] ?? '') ?>" placeholder="۰" data-buy-extra></label>
      </div>
    </details>
    <div class="buy-total" data-buy-total aria-live="polite"></div>
  </div>

  <div class="fgrid">
    <label class="field"><span>تاریخ خرید</span>
      <input name="date" value="<?= e($f['date'] ?? '') ?>" required placeholder="۱۴۰۵/۰۱/۰۱"></label>
    <label class="field"><span>توضیح <small>(اختیاری؛ مثلاً شماره فاکتور فروشنده)</small></span>
      <input name="note" value="<?= e($f['note'] ?? '') ?>" maxlength="300"></label>
  </div>
<?php
}

/** Ticked lines from a posted purchase form, to show them again after an error. */
function purchase_picked_from_post(array $post): array
{
    $out = [];
    foreach ((is_array($post['items'] ?? null) ? $post['items'] : []) as $row) {
        if (is_array($row) && !empty($row['on'])) {
            $out[(int) ($row['pos'] ?? 0)] = ['qty' => (string) ($row['qty'] ?? ''), 'price' => (string) ($row['price'] ?? '')];
        }
    }
    return $out;
}

/** A saved purchase as form values: its lines matched back to the invoice's lines by title. */
function purchase_form_from(array $pur): array
{
    $byKey = [];
    foreach (invoice_buy_lines((int) $pur['doc_id'], (int) $pur['id']) as $l) {
        $byKey[name_key($l['title'])] = $byKey[name_key($l['title'])] ?? $l['pos'];
    }
    $f = ['doc_id' => (string) $pur['doc_id'], 'supplier' => $pur['supplier'], 'date' => fa($pur['date']),
        'note' => $pur['note'], 'picked' => [], 'extra_title' => '', 'extra_amount' => ''];
    $loose = [];
    foreach (purchase_items((int) $pur['id']) as $it) {
        $pos = $it['from_invoice'] ? ($byKey[name_key($it['title'])] ?? null) : null;
        if ($pos !== null && !isset($f['picked'][$pos])) {
            $f['picked'][$pos] = ['qty' => qty_fa($it['qty']), 'price' => money($it['unit_price'])];
        } else {
            $loose[] = $it;
        }
    }
    if (!purchase_items((int) $pur['id'])) {
        $loose[] = ['title' => $pur['title'], 'amount' => $pur['amount']]; // bought before items existed
    }
    if ($loose) {
        $f['extra_title'] = str_cut(implode('، ', array_column($loose, 'title')), 200);
        $f['extra_amount'] = money(array_sum(array_column($loose, 'amount')));
    }
    return $f;
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

// --- Record cards (shared by the list pages and the dashboard) -------------

/** «۱۲۰٬۰۰۰ ریال» with the unit set smaller, for card footers. */
function amount_html($n, string $class = 'rc-amt'): string
{
    return '<span class="' . e($class) . '">' . money($n) . '<small>' . e(setting('unit')) . '</small></span>';
}

/** Status badge of a proforma: converted (links to its invoice) or still open. */
function qot_badge(array $d): string
{
    if (!empty($d['inv_id'])) {
        return '<a class="badge ok" href="' . e(url('doc', ['id' => $d['inv_id']])) . '">فاکتور شد: <span dir="ltr">'
            . e($d['inv_number']) . '</span></a>';
    }
    return '<span class="badge info">باز</span>';
}

/** One proforma/invoice as a card; the whole card opens the document. */
function doc_card(array $d, array $remaining = [], bool $compact = false, bool $withCustomer = true): string
{
    $isInv = $d['type'] === 'inv';
    $void = $d['status'] === 'cancelled';
    $badge = $isInv ? invoice_badge($d, $remaining) : qot_badge($d);
    return '<article class="rcard' . ($compact ? ' compact' : '') . ($void ? ' is-void' : '') . '">'
        . '<div class="rc-top"><a class="rc-num mono stretch" href="' . e(url('doc', ['id' => $d['id']])) . '">'
        . e($d['number']) . '</a>' . $badge . '</div>'
        . ($withCustomer ? '<a class="rc-title" href="' . e(url('customer', ['id' => $d['customer_id']])) . '">'
            . e($d['cust_name']) . '</a>' : '')
        . '<div class="rc-meta"><span>' . icon('calendar-blank') . fa($d['date']) . '</span></div>'
        . '<div class="rc-foot">' . amount_html($d['total']) . '</div>'
        . '</article>';
}

/** One customer as a card: monogram, name, phone, balance and the receipt shortcut. */
function customer_card(array $c, bool $compact = false): string
{
    $initial = str_cut(trim((string) $c['name']), 1) ?: '؟';
    return '<article class="rcard' . ($compact ? ' compact' : '') . '">'
        . '<div class="rc-head"><span class="rc-mono" aria-hidden="true">' . e($initial) . '</span>'
        . '<div><a class="rc-title stretch" href="' . e(url('customer', ['id' => $c['id']])) . '">'
        . e($c['name']) . '</a>'
        . ($c['phone'] !== '' ? '<div class="rc-sub tel">' . e($c['phone']) . '</div>' : '')
        . '</div></div>'
        . '<div class="rc-foot">' . balance_html($c['balance'])
        . ($compact || !can('sales', 'edit') ? '' : '<a class="btn btn-ghost btn-sm" href="' . e(url('payments', ['customer' => $c['id']])) . '">ثبت دریافت</a>')
        . '</div></article>';
}

/** One activity-log entry as a compact card: what, details, who and when. */
function activity_item(array $a, bool $withUser = true): string
{
    $act = e($a['action']);
    if ($a['url'] !== '') {
        $act = '<a href="' . e($a['url']) . '">' . $act . '</a>';
    }
    return '<article class="feed-item">'
        . '<div class="feed-main"><b>' . $act . '</b>'
        . ($a['detail'] !== '' ? '<span class="feed-detail">' . e($a['detail']) . '</span>' : '') . '</div>'
        . '<div class="rc-meta">'
        . ($withUser ? '<span>' . icon('user') . e($a['user_name'] !== '' ? $a['user_name'] : 'سیستم') . '</span>' : '')
        . '<span>' . icon('clock-counter-clockwise') . jstamp($a['at']) . '</span></div>'
        . '</article>';
}
