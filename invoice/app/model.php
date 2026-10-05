<?php
/**
 * Business logic: settings, customers, proformas/invoices, payments, balances.
 *
 * Money is stored as whole numbers in the configured unit (default ریال).
 * A customer's balance = opening balance + active invoices − payments;
 * positive means the customer owes us (بدهکار). Proformas never touch it.
 */
defined('APP_DIR') || exit;

const DOC_NAMES = ['qot' => 'پیش‌فاکتور', 'inv' => 'فاکتور'];
const DOC_PREFIX = ['qot' => 'QOT-', 'inv' => 'INV-'];
const PAY_METHODS = ['نقدی', 'کارت به کارت', 'واریز / حواله', 'چک', 'سایر'];
const MAX_AMOUNT = 1e15;

const PURCHASE_PREFIX = 'PUR-';
const EXPENSE_KINDS = ['direct' => 'هزینهٔ مستقیم فاکتور', 'overhead' => 'هزینهٔ سربار شرکت'];
const EXPENSE_KIND_SHORT = ['direct' => 'مستقیم', 'overhead' => 'سربار'];
// Suggestions only (the category field is free text).
const DIRECT_CATEGORIES = ['پرداخت بابت خرید', 'پیک / حمل‌ونقل', 'نصب و اجرا', 'سایر'];
const OVERHEAD_CATEGORIES = ['اجاره', 'حقوق و دستمزد', 'قبوض و شارژ', 'تبلیغات', 'ملزومات اداری',
    'ایاب و ذهاب', 'سایر'];

const SETTING_DEFAULTS = [
    'company_name' => 'شرکت فناوری اطلاعات داران',
    'company_tagline' => 'همه چیز سرِ جای درستش.',
    'phones' => "۰۲۱-۸۸۹۶۴۱۱۶\n۸۸۹۶۶۹۰۴\n۰۹۳۵-۹۳۷۰۹۱۰",
    'address' => 'تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲',
    'website' => 'www.DaranX.com',
    'signature' => 'شرکت داران',
    'unit' => 'ریال',
    'vat_rate' => '10',
    'notes_qot' => "قیمت‌های اعلامی تا پایان وقت اداری تاریخ صدور اعتبار دارد.\nاز اعتماد شما به مجموعه داران‌ایکس سپاسگزاریم.",
    'notes_inv' => "تسویه به صورت نقدی می‌باشد.\nاز اعتماد شما به مجموعه داران‌ایکس سپاسگزاریم.",
    // AI assistant (chat page). The key is stored server-side only, never sent to the browser.
    // base_url picks the API: a host containing "anthropic" uses the native
    // Anthropic Messages API; anything else (e.g. GapGPT) uses the
    // OpenAI-compatible /chat/completions format.
    'assistant_api_key' => '',
    'assistant_base_url' => 'https://api.anthropic.com',
    'assistant_model' => 'claude-opus-5-5',
];

const BALANCE_SQL = "(c.opening_balance
    + COALESCE((SELECT SUM(d.total) FROM docs d
                WHERE d.customer_id = c.id AND d.type = 'inv' AND d.status = 'active'), 0)
    - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = c.id), 0))";

// --- Settings ---------------------------------------------------------------

function settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = SETTING_DEFAULTS;
        foreach (db()->query('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache;
}

function setting(string $k): string
{
    return (string) (settings()[$k] ?? '');
}

function settings_save(array $values): void
{
    tx(function (PDO $pdo) use ($values) {
        $st = $pdo->prepare('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)');
        foreach ($values as $k => $v) {
            if (array_key_exists($k, SETTING_DEFAULTS)) {
                $st->execute([$k, (string) $v]);
            }
        }
    });
    settings(true);
}

function default_notes(string $type): array
{
    return array_values(array_filter(explode("\n", setting('notes_' . $type)), 'strlen'));
}

// --- Users & login throttling ----------------------------------------------

function has_users(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function user_by_name(string $username): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    return $st->fetch() ?: null;
}

const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW = 900; // seconds

function login_locked(string $key): bool
{
    db()->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - LOGIN_WINDOW]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE k = ?');
    $st->execute([$key]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FAILS;
}

function login_failed(string $key): void
{
    db()->prepare('INSERT INTO login_attempts (k, ts) VALUES (?, ?)')->execute([$key, time()]);
}

function login_clear(string $key): void
{
    db()->prepare('DELETE FROM login_attempts WHERE k = ?')->execute([$key]);
}

// --- Customers --------------------------------------------------------------

function customer_get(int $id): ?array
{
    $st = db()->prepare('SELECT c.*, ' . BALANCE_SQL . ' AS balance FROM customers c WHERE c.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function customers_list(string $q = '', bool $debtorsOnly = false): array
{
    $sql = 'SELECT c.*, ' . BALANCE_SQL . ' AS balance FROM customers c';
    $args = [];
    if ($q !== '') {
        $sql .= " WHERE (c.name LIKE ? ESCAPE '\\' OR c.phone LIKE ? ESCAPE '\\' OR c.phone LIKE ? ESCAPE '\\')";
        $digits = like_escape(en_digits($q));
        $args = ['%' . like_escape(fa_norm($q)) . '%', '%' . $digits . '%', '%' . fa($digits) . '%'];
    }
    $sql .= $debtorsOnly ? ' ORDER BY balance DESC, c.name' : ' ORDER BY c.name';
    $st = db()->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    if ($debtorsOnly) {
        $rows = array_values(array_filter($rows, function ($r) {
            return (float) $r['balance'] > 0;
        }));
    }
    return $rows;
}

function like_escape(string $s): string
{
    return strtr($s, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
}

/** Validate the customer form. */
function customer_validate(array $in): array
{
    $c = [
        'name' => clean_line($in['name'] ?? '', 150),
        'phone' => clean_line($in['phone'] ?? '', 80),
        'address' => clean_text($in['address'] ?? '', 400),
        'note' => clean_text($in['note'] ?? '', 1000),
    ];
    if ($c['name'] === '') {
        throw new UserError('نام مشتری را وارد کنید.');
    }
    $ob = parse_num($in['opening_balance'] ?? '');
    if ($ob === null && trim((string) ($in['opening_balance'] ?? '')) !== '') {
        throw new UserError('مانده از قبل باید عدد باشد.');
    }
    if ($ob !== null && abs($ob) > MAX_AMOUNT) {
        throw new UserError('مانده از قبل بیش از حد بزرگ است.');
    }
    $ob = (int) round(abs($ob ?? 0));
    if (($in['opening_side'] ?? 'debtor') === 'creditor') {
        $ob = -$ob;
    }
    $c['opening_balance'] = $ob;
    return $c;
}

function customer_save(array $c, ?int $id = null): int
{
    $rid = tx(function (PDO $pdo) use ($c, $id) {
        $key = name_key($c['name']);
        $st = $pdo->prepare('SELECT id FROM customers WHERE name_key = ? AND id <> ?');
        $st->execute([$key, $id ?? 0]);
        if ($st->fetchColumn()) {
            throw new UserError('مشتری دیگری با نام «' . $c['name'] . '» وجود دارد.');
        }
        if ($id === null) {
            $pdo->prepare('INSERT INTO customers (name, name_key, phone, address, opening_balance, note, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$c['name'], $key, $c['phone'], $c['address'], $c['opening_balance'], $c['note'], now()]);
            return (int) $pdo->lastInsertId();
        }
        $pdo->prepare('UPDATE customers SET name = ?, name_key = ?, phone = ?, address = ?,
                       opening_balance = ?, note = ? WHERE id = ?')
            ->execute([$c['name'], $key, $c['phone'], $c['address'], $c['opening_balance'], $c['note'], $id]);
        return $id;
    });
    activity_log($id === null ? 'مشتری جدید' : 'ویرایش مشتری', $c['name'], url('customer', ['id' => $rid]));
    return $rid;
}

function customer_delete(int $id): void
{
    $old = customer_get($id);
    tx(function (PDO $pdo) use ($id) {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM docs WHERE customer_id = ?)
                                  + (SELECT COUNT(*) FROM payments WHERE customer_id = ?)');
        $st->execute([$id, $id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new UserError('این مشتری سند یا دریافتی ثبت‌شده دارد و حذف نمی‌شود.');
        }
        $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    });
    activity_log('حذف مشتری', $old ? $old['name'] : '');
}

/**
 * Find the customer typed on a document by name, or create them. Fills in a
 * missing phone/address on an existing customer. Call inside tx().
 */
function customer_resolve(PDO $pdo, string $name, string $phone, string $address): int
{
    $st = $pdo->prepare('SELECT id, phone, address FROM customers WHERE name_key = ?');
    $st->execute([name_key($name)]);
    $c = $st->fetch();
    if ($c) {
        if ($c['phone'] === '' && $phone !== '') {
            $pdo->prepare('UPDATE customers SET phone = ? WHERE id = ?')->execute([$phone, $c['id']]);
        }
        if ($c['address'] === '' && $address !== '') {
            $pdo->prepare('UPDATE customers SET address = ? WHERE id = ?')->execute([$address, $c['id']]);
        }
        return (int) $c['id'];
    }
    $pdo->prepare('INSERT INTO customers (name, name_key, phone, address, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, name_key($name), $phone, $address, now()]);
    return (int) $pdo->lastInsertId();
}

/**
 * The customer's statement: opening balance, active invoices (debit) and
 * payments (credit) in date order, each with the running balance.
 */
function customer_ledger(array $c): array
{
    $rows = [];
    $st = db()->prepare("SELECT id, number, date, total, created_at FROM docs
                         WHERE customer_id = ? AND type = 'inv' AND status = 'active'");
    $st->execute([$c['id']]);
    foreach ($st as $r) {
        $rows[] = ['kind' => 'inv', 'id' => (int) $r['id'], 'date' => $r['date'], 'at' => $r['created_at'],
            'label' => 'فاکتور ' . $r['number'], 'note' => '',
            'debit' => (float) $r['total'], 'credit' => 0.0];
    }
    $st = db()->prepare('SELECT id, amount, date, method, note, created_at FROM payments WHERE customer_id = ?');
    $st->execute([$c['id']]);
    foreach ($st as $r) {
        $rows[] = ['kind' => 'pay', 'id' => (int) $r['id'], 'date' => $r['date'], 'at' => $r['created_at'],
            'label' => 'دریافت' . ($r['method'] !== '' ? ' (' . $r['method'] . ')' : ''), 'note' => $r['note'],
            'debit' => 0.0, 'credit' => (float) $r['amount']];
    }
    usort($rows, function ($a, $b) {
        return [$a['date'], $a['at'], $a['kind'], $a['id']] <=> [$b['date'], $b['at'], $b['kind'], $b['id']];
    });

    $out = [];
    $bal = (float) $c['opening_balance'];
    if ($bal != 0) {
        $out[] = ['kind' => 'open', 'id' => 0, 'date' => '', 'label' => 'مانده از قبل', 'note' => '',
            'debit' => max($bal, 0), 'credit' => max(-$bal, 0), 'balance' => $bal];
    }
    foreach ($rows as $r) {
        $bal += $r['debit'] - $r['credit'];
        $r['balance'] = $bal;
        $out[] = $r;
    }
    return $out;
}

// --- Documents (proforma / invoice) ----------------------------------------

function doc_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM docs WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function doc_items(int $id): array
{
    $st = db()->prepare('SELECT title, qty, price FROM doc_items WHERE doc_id = ? ORDER BY pos');
    $st->execute([$id]);
    $items = [];
    foreach ($st as $r) {
        $items[] = [
            'title' => $r['title'],
            'qty' => $r['qty'] === null ? null : (float) $r['qty'],
            'price' => $r['price'] === null ? null : (float) $r['price'],
        ];
    }
    return $items;
}

/** The invoice a proforma was converted to, if any. */
function doc_invoice_of(int $qotId): ?array
{
    $st = db()->prepare("SELECT id, number, status FROM docs WHERE source_id = ? AND type = 'inv' LIMIT 1");
    $st->execute([$qotId]);
    return $st->fetch() ?: null;
}

function doc_next_number(string $type): string
{
    $max = 0;
    $st = db()->prepare('SELECT number FROM docs WHERE type = ?');
    $st->execute([$type]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
        if (preg_match('/(\d+)\s*$/', $n, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return DOC_PREFIX[$type] . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

function num_or_null($v): ?float
{
    return ($v === null || $v === '') ? null : parse_num($v);
}

/**
 * Validate the editor payload and compute the totals server-side (the
 * browser's numbers are never trusted). Throws UserError on bad input.
 */
function doc_validate(array $in, string $type): array
{
    $d = ['type' => $type];
    $d['cust_name'] = clean_line($in['cust_name'] ?? '', 150);
    $d['cust_address'] = clean_text($in['cust_address'] ?? '', 400);
    $d['cust_phone'] = clean_line($in['cust_phone'] ?? '', 80);
    if ($d['cust_name'] === '') {
        throw new UserError('نام مشتری را وارد کنید.');
    }
    $d['date'] = jdate_norm($in['date'] ?? '');
    if ($d['date'] === null) {
        throw new UserError('تاریخ صدور معتبر نیست؛ آن را مثل ۱۴۰۵/۰۱/۲۰ وارد کنید.');
    }
    $d['number_auto'] = !empty($in['number_auto']);
    $d['number'] = clean_line(en_digits($in['number'] ?? ''), 30);
    if ($d['number'] === '' && !$d['number_auto']) {
        throw new UserError('شمارهٔ سند را وارد کنید.');
    }

    $raw = (isset($in['items']) && is_array($in['items'])) ? $in['items'] : [];
    if (count($raw) > 300) {
        throw new UserError('تعداد ردیف‌ها بیش از حد مجاز است.');
    }
    $items = [];
    $subtotal = 0;
    foreach ($raw as $it) {
        if (!is_array($it)) {
            continue;
        }
        $title = clean_text($it['title'] ?? '', 500);
        $qty = num_or_null($it['qty'] ?? null);
        $price = num_or_null($it['price'] ?? null);
        if ($title === '' && $price === null) {
            continue; // empty row
        }
        $label = $title !== '' ? '«' . $title . '»' : 'ردیف ' . fa(count($items) + 1);
        if ($qty !== null && ($qty <= 0 || $qty > 1e6)) {
            throw new UserError('تعداد ' . $label . ' معتبر نیست.');
        }
        if ($price !== null && ($price < 0 || $price > MAX_AMOUNT)) {
            throw new UserError('قیمت ' . $label . ' معتبر نیست.');
        }
        $price = $price === null ? null : round($price);
        $line = $price === null ? 0.0 : round($price * ($qty ?? 1));
        if ($line > MAX_AMOUNT) {
            throw new UserError('مبلغ ' . $label . ' بیش از حد بزرگ است.');
        }
        $items[] = ['title' => $title, 'qty' => $qty, 'price' => $price === null ? null : (int) $price,
            'line_total' => (int) $line];
        $subtotal += (int) $line;
    }
    if (!$items) {
        throw new UserError('حداقل یک ردیف کالا یا خدمت وارد کنید.');
    }
    if ($subtotal > MAX_AMOUNT) {
        throw new UserError('جمع کل بیش از حد بزرگ است.');
    }

    $discount = num_or_null($in['discount'] ?? null);
    $discount = (int) round($discount ?? 0);
    if ($discount < 0) {
        throw new UserError('تخفیف نمی‌تواند منفی باشد.');
    }
    if ($discount > $subtotal) {
        throw new UserError('تخفیف نمی‌تواند از جمع کل بیشتر باشد.');
    }
    $vatOn = !empty($in['vat_on']);
    $rate = num_or_null($in['vat_rate'] ?? null);
    if ($rate === null) {
        $rate = (float) setting('vat_rate');
    }
    if ($rate < 0 || $rate > 100) {
        throw new UserError('درصد مالیات باید بین ۰ تا ۱۰۰ باشد.');
    }
    $vat = $vatOn ? (int) round(($subtotal - $discount) * $rate / 100) : 0;

    $notes = [];
    foreach (array_slice(is_array($in['notes'] ?? null) ? $in['notes'] : [], 0, 30) as $n) {
        $n = clean_line($n, 300);
        if ($n !== '') {
            $notes[] = $n;
        }
    }

    return $d + [
        'items' => $items,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'vat_on' => $vatOn ? 1 : 0,
        'vat_rate' => $rate,
        'vat_amount' => $vat,
        'total' => $subtotal - $discount + $vat,
        'notes' => implode("\n", $notes),
    ];
}

/** Insert (id = null) or update a document with its items. Returns its id. */
function doc_save(array $d, ?int $id = null): int
{
    $rid = tx(function (PDO $pdo) use ($d, $id) {
        $cid = 0;
        if ($id !== null) {
            // Name on the sheet unchanged → same customer, even if they were
            // renamed on their own page since this document was issued.
            $st = $pdo->prepare('SELECT customer_id, cust_name FROM docs WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch();
            if ($old && name_key($old['cust_name']) === name_key($d['cust_name'])) {
                $cid = (int) $old['customer_id'];
            }
        }
        if (!$cid) {
            $cid = customer_resolve($pdo, $d['cust_name'], $d['cust_phone'], $d['cust_address']);
        }
        $number = ($id === null && ($d['number_auto'] || $d['number'] === ''))
            ? doc_next_number($d['type']) : $d['number'];
        $st = $pdo->prepare('SELECT id FROM docs WHERE type = ? AND number = ? AND id <> ?');
        $st->execute([$d['type'], $number, $id ?? 0]);
        if ($st->fetchColumn()) {
            throw new UserError('شمارهٔ «' . $number . '» قبلاً برای ' . DOC_NAMES[$d['type']] . ' دیگری ثبت شده است.');
        }
        $vals = [$number, $d['date'], $cid, $d['cust_name'], $d['cust_address'], $d['cust_phone'],
            $d['subtotal'], $d['discount'], $d['vat_on'], $d['vat_rate'], $d['vat_amount'], $d['total'],
            $d['notes'], now()];
        if ($id === null) {
            $pdo->prepare('INSERT INTO docs (number, date, customer_id, cust_name, cust_address, cust_phone,
                               subtotal, discount, vat_on, vat_rate, vat_amount, total, notes, updated_at,
                               type, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($vals, [$d['type'], now()]));
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE docs SET number = ?, date = ?, customer_id = ?, cust_name = ?, cust_address = ?,
                               cust_phone = ?, subtotal = ?, discount = ?, vat_on = ?, vat_rate = ?, vat_amount = ?,
                               total = ?, notes = ?, updated_at = ?
                           WHERE id = ?')
                ->execute(array_merge($vals, [$id]));
        }
        doc_write_items($pdo, $id, $d['items']);
        return $id;
    });
    $saved = doc_get($rid);
    activity_log(($id === null ? ($d['type'] === 'inv' ? 'صدور ' : 'ثبت ') : 'ویرایش ') . DOC_NAMES[$d['type']],
        $saved['number'] . '، ' . $saved['cust_name'] . '، ' . money($saved['total']) . ' ' . setting('unit'),
        url('doc', ['id' => $rid]));
    return $rid;
}

function doc_write_items(PDO $pdo, int $docId, array $items): void
{
    $pdo->prepare('DELETE FROM doc_items WHERE doc_id = ?')->execute([$docId]);
    $st = $pdo->prepare('INSERT INTO doc_items (doc_id, pos, title, qty, price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (array_values($items) as $i => $it) {
        $st->execute([$docId, $i + 1, $it['title'], $it['qty'], $it['price'], $it['line_total']]);
    }
}

/** Issue an invoice from a proforma (copy of its lines and totals). Returns the invoice id. */
function doc_convert(int $qotId): int
{
    $rid = tx(function (PDO $pdo) use ($qotId) {
        $q = doc_get($qotId);
        if (!$q || $q['type'] !== 'qot') {
            throw new UserError('پیش‌فاکتور پیدا نشد.');
        }
        if (doc_invoice_of($qotId)) {
            throw new UserError('این پیش‌فاکتور قبلاً به فاکتور تبدیل شده است.');
        }
        $pdo->prepare("INSERT INTO docs (type, number, date, customer_id, cust_name, cust_address, cust_phone,
                           subtotal, discount, vat_on, vat_rate, vat_amount, total, notes, status, source_id,
                           created_at, updated_at)
                       VALUES ('inv', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)")
            ->execute([doc_next_number('inv'), jtoday(), $q['customer_id'], $q['cust_name'], $q['cust_address'],
                $q['cust_phone'], $q['subtotal'], $q['discount'], $q['vat_on'], $q['vat_rate'], $q['vat_amount'],
                $q['total'], implode("\n", default_notes('inv')), $qotId, now(), now()]);
        $invId = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('SELECT title, qty, price, line_total FROM doc_items WHERE doc_id = ? ORDER BY pos');
        $st->execute([$qotId]);
        doc_write_items($pdo, $invId, $st->fetchAll());
        return $invId;
    });
    $inv = doc_get($rid);
    activity_log('تبدیل پیش‌فاکتور به فاکتور', doc_get($qotId)['number'] . ' ← ' . $inv['number'] . '، ' . $inv['cust_name'],
        url('doc', ['id' => $rid]));
    return $rid;
}

function doc_set_status(int $id, string $status): void
{
    db()->prepare("UPDATE docs SET status = ?, updated_at = ? WHERE id = ? AND type = 'inv'")
        ->execute([$status, now(), $id]);
    $d = doc_get($id);
    if ($d) {
        activity_log($status === 'cancelled' ? 'ابطال فاکتور' : 'بازگردانی فاکتور', $d['number'] . '، ' . $d['cust_name'],
            url('doc', ['id' => $id]));
    }
}

function doc_delete(int $id): void
{
    $old = doc_get($id);
    tx(function (PDO $pdo) use ($id) {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM purchases WHERE doc_id = ?)
                                  + (SELECT COUNT(*) FROM expenses WHERE doc_id = ?)');
        $st->execute([$id, $id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new UserError('این فاکتور خرید یا هزینهٔ ثبت‌شده دارد؛ اول آن‌ها را حذف کنید یا به فاکتور دیگری بدهید.');
        }
        $pdo->prepare('DELETE FROM docs WHERE id = ?')->execute([$id]);
    });
    if ($old) {
        activity_log('حذف ' . DOC_NAMES[$old['type']], $old['number'] . '، ' . $old['cust_name'] . '، ' . money($old['total']));
    }
}

function docs_list(string $type, string $q = '', int $customerId = 0, int $limit = 300): array
{
    $sql = "SELECT d.*, x.id AS inv_id, x.number AS inv_number
            FROM docs d LEFT JOIN docs x ON x.source_id = d.id AND x.type = 'inv'
            WHERE d.type = ?";
    $args = [$type];
    if ($customerId) {
        $sql .= ' AND d.customer_id = ?';
        $args[] = $customerId;
    }
    if ($q !== '') {
        $sql .= " AND (d.number LIKE ? ESCAPE '\\' OR d.cust_name LIKE ? ESCAPE '\\')";
        $args[] = '%' . like_escape(en_digits($q)) . '%';
        $args[] = '%' . like_escape(fa_norm($q)) . '%';
    }
    $sql .= ' ORDER BY d.date DESC, d.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/**
 * How much of each active invoice is still unpaid. Payments are not tied to a
 * specific invoice: they settle the customer's oldest debt first (opening
 * balance, then invoices by date). Returns [invoice id => remaining].
 */
function invoice_remaining(int $customerId = 0): array
{
    $credit = [];
    $sql = 'SELECT c.id, c.opening_balance,
                   COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = c.id), 0) AS paid
            FROM customers c' . ($customerId ? ' WHERE c.id = ' . $customerId : '');
    foreach (db()->query($sql) as $r) {
        $credit[(int) $r['id']] = max(0.0, (float) $r['paid'] - (float) $r['opening_balance']);
    }
    $sql = "SELECT id, customer_id, total FROM docs WHERE type = 'inv' AND status = 'active'"
        . ($customerId ? ' AND customer_id = ' . $customerId : '') . ' ORDER BY customer_id, date, id';
    $out = [];
    foreach (db()->query($sql) as $r) {
        $cid = (int) $r['customer_id'];
        $applied = min($credit[$cid] ?? 0.0, (float) $r['total']);
        $credit[$cid] = ($credit[$cid] ?? 0.0) - $applied;
        $out[(int) $r['id']] = (float) $r['total'] - $applied;
    }
    return $out;
}

/** Status badge for an invoice row. */
function invoice_badge(array $doc, array $remaining): string
{
    if ($doc['status'] === 'cancelled') {
        return '<span class="badge muted">باطل‌شده</span>';
    }
    $left = $remaining[(int) $doc['id']] ?? (float) $doc['total'];
    if ($left < 0.5) {
        return '<span class="badge ok">تسویه‌شده</span>';
    }
    if ($left < (float) $doc['total']) {
        return '<span class="badge warn">مانده ' . money($left) . '</span>';
    }
    return '<span class="badge danger">پرداخت‌نشده</span>';
}

// --- Payments ---------------------------------------------------------------

function payment_get(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, c.name AS customer_name FROM payments p
                         JOIN customers c ON c.id = p.customer_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function payment_validate(array $in): array
{
    $cid = (int) ($in['customer_id'] ?? 0);
    if (!$cid || !customer_get($cid)) {
        throw new UserError('مشتری را انتخاب کنید.');
    }
    $amount = parse_num($in['amount'] ?? '');
    if ($amount === null || $amount <= 0) {
        throw new UserError('مبلغ دریافتی را درست وارد کنید.');
    }
    if ($amount > MAX_AMOUNT) {
        throw new UserError('مبلغ بیش از حد بزرگ است.');
    }
    $date = jdate_norm($in['date'] ?? '');
    if ($date === null) {
        throw new UserError('تاریخ معتبر نیست؛ آن را مثل ۱۴۰۵/۰۱/۲۰ وارد کنید.');
    }
    $method = clean_line($in['method'] ?? '', 40);
    if (!in_array($method, PAY_METHODS, true)) {
        $method = 'سایر';
    }
    return ['customer_id' => $cid, 'amount' => (int) round($amount), 'date' => $date, 'method' => $method,
        'note' => clean_line($in['note'] ?? '', 300)];
}

function payment_save(array $p, ?int $id = null): int
{
    $pdo = db();
    if ($id === null) {
        $pdo->prepare('INSERT INTO payments (customer_id, amount, date, method, note, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$p['customer_id'], $p['amount'], $p['date'], $p['method'], $p['note'], now()]);
        $id2 = (int) $pdo->lastInsertId();
        payment_log('ثبت دریافت', $p, $id2);
        return $id2;
    }
    $pdo->prepare('UPDATE payments SET customer_id = ?, amount = ?, date = ?, method = ?, note = ? WHERE id = ?')
        ->execute([$p['customer_id'], $p['amount'], $p['date'], $p['method'], $p['note'], $id]);
    payment_log('ویرایش دریافت', $p, $id);
    return $id;
}

function payment_delete(int $id): void
{
    $old = payment_get($id);
    db()->prepare('DELETE FROM payments WHERE id = ?')->execute([$id]);
    if ($old) {
        activity_log('حذف دریافت', $old['customer_name'] . '، ' . money($old['amount']) . ' ' . setting('unit'));
    }
}

function payment_log(string $action, array $p, int $id): void
{
    $c = customer_get((int) $p['customer_id']);
    activity_log($action, ($c ? $c['name'] : '') . '، ' . money($p['amount']) . ' ' . setting('unit') . '، ' . $p['method'],
        url('payment', ['id' => $id]));
}

function payments_list(string $q = '', int $limit = 300): array
{
    $sql = 'SELECT p.*, c.name AS customer_name FROM payments p JOIN customers c ON c.id = p.customer_id';
    $args = [];
    if ($q !== '') {
        $sql .= " WHERE (c.name LIKE ? ESCAPE '\\' OR p.note LIKE ? ESCAPE '\\')";
        $like = '%' . like_escape(fa_norm($q)) . '%';
        $args = [$like, $like];
    }
    $sql .= ' ORDER BY p.date DESC, p.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

// --- Shared input checks for purchases / expenses --------------------------

function amount_in($v, string $what): int
{
    $amount = parse_num($v ?? '');
    if ($amount === null || $amount <= 0) {
        throw new UserError($what . ' را درست وارد کنید.');
    }
    if ($amount > MAX_AMOUNT) {
        throw new UserError($what . ' بیش از حد بزرگ است.');
    }
    return (int) round($amount);
}

function date_in($v): string
{
    $date = jdate_norm($v ?? '');
    if ($date === null) {
        throw new UserError('تاریخ معتبر نیست؛ آن را مثل ۱۴۰۵/۰۱/۲۰ وارد کنید.');
    }
    return $date;
}

/** Invoices a purchase or a direct cost can be assigned to (newest first). */
function invoice_options(int $keepId = 0): array
{
    $st = db()->prepare("SELECT id, number, cust_name, total, status FROM docs
                         WHERE type = 'inv' AND (status = 'active' OR id = ?)
                         ORDER BY date DESC, id DESC LIMIT 500");
    $st->execute([$keepId]);
    return $st->fetchAll();
}

/** Check an invoice reference. A cancelled invoice can be kept, but not newly chosen. */
function invoice_ref(int $docId, int $currentId = 0): int
{
    $d = $docId ? doc_get($docId) : null;
    if (!$d || $d['type'] !== 'inv') {
        throw new UserError('فاکتور مربوط را انتخاب کنید.');
    }
    if ($d['status'] !== 'active' && $docId !== $currentId) {
        throw new UserError('فاکتور ' . $d['number'] . ' باطل شده است؛ یک فاکتور فعال انتخاب کنید.');
    }
    return $docId;
}

// --- Purchases (the cost side of an invoice) --------------------------------

const PURCHASE_SQL = "SELECT p.*, d.number AS doc_number, d.cust_name, d.status AS doc_status,
        COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.purchase_id = p.id), 0) AS paid
    FROM purchases p JOIN docs d ON d.id = p.doc_id";

function purchase_get(int $id): ?array
{
    $st = db()->prepare(PURCHASE_SQL . ' WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function purchase_next_number(): string
{
    $max = 0;
    foreach (db()->query('SELECT number FROM purchases')->fetchAll(PDO::FETCH_COLUMN) as $n) {
        if (preg_match('/(\d+)\s*$/', $n, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return PURCHASE_PREFIX . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

/** An invoice's lines as purchase choices, with what was already bought for each (by title). */
function invoice_buy_lines(int $docId, int $exceptPurchase = 0): array
{
    $st = db()->prepare('SELECT pos, title, qty, price, line_total FROM doc_items WHERE doc_id = ? AND title <> \'\' ORDER BY pos');
    $st->execute([$docId]);
    $bought = [];
    $sb = db()->prepare('SELECT i.title, i.qty, i.amount, p.number FROM purchase_items i JOIN purchases p ON p.id = i.purchase_id
                         WHERE p.doc_id = ? AND p.id <> ? AND i.from_invoice = 1');
    $sb->execute([$docId, $exceptPurchase]);
    foreach ($sb as $r) {
        $bought[name_key($r['title'])][] = ['number' => $r['number'], 'qty' => $r['qty'] === null ? null : (float) $r['qty'],
            'amount' => (float) $r['amount']];
    }
    $out = [];
    foreach ($st as $r) {
        $out[] = [
            'pos' => (int) $r['pos'], 'title' => $r['title'],
            'qty' => $r['qty'] === null ? null : (float) $r['qty'],
            'price' => $r['price'] === null ? null : (float) $r['price'],
            'sale' => (float) $r['line_total'],
            'bought' => $bought[name_key($r['title'])] ?? [],
        ];
    }
    return $out;
}

function purchase_items(int $purchaseId): array
{
    $st = db()->prepare('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY pos');
    $st->execute([$purchaseId]);
    return $st->fetchAll();
}

/**
 * A purchase: the invoice it is for, the invoice lines bought (quantity ×
 * unit purchase price each) and/or one free line. Title and amount are
 * derived from the lines. Without any lines, a plain title + amount (older
 * callers) becomes the free line.
 */
function purchase_validate(array $in, ?array $old = null): array
{
    $p = [
        'doc_id' => invoice_ref((int) ($in['doc_id'] ?? 0), $old ? (int) $old['doc_id'] : 0),
        'supplier' => clean_line($in['supplier'] ?? '', 150),
        'date' => date_in($in['date'] ?? ''),
        'note' => clean_line($in['note'] ?? '', 300),
        'items' => [],
    ];
    $docLines = [];
    $st = db()->prepare('SELECT pos, title, qty FROM doc_items WHERE doc_id = ?');
    $st->execute([$p['doc_id']]);
    foreach ($st as $r) {
        $docLines[(int) $r['pos']] = $r;
    }
    foreach ((is_array($in['items'] ?? null) ? $in['items'] : []) as $row) {
        if (!is_array($row) || empty($row['on'])) {
            continue;
        }
        $line = $docLines[(int) ($row['pos'] ?? 0)] ?? null;
        if (!$line) {
            throw new UserError('یکی از اقلام انتخاب‌شده در این فاکتور نیست؛ صفحه را دوباره باز کنید.');
        }
        $qty = parse_num($row['qty'] ?? '');
        $qty = $qty ?? ($line['qty'] !== null ? (float) $line['qty'] : 1.0);
        if ($qty <= 0 || $qty > 1e9) {
            throw new UserError('تعداد «' . $line['title'] . '» را درست وارد کنید.');
        }
        $price = amount_in($row['price'] ?? '', 'قیمت خرید «' . $line['title'] . '»');
        $p['items'][] = ['title' => $line['title'], 'qty' => $qty, 'unit_price' => $price,
            'amount' => (int) round($qty * $price), 'from_invoice' => 1];
    }
    $extraAmount = trim((string) ($in['extra_amount'] ?? ''));
    $extraTitle = clean_line($in['extra_title'] ?? '', 200);
    if ($extraAmount === '' && !$p['items'] && trim((string) ($in['amount'] ?? '')) !== '') {
        $extraAmount = (string) $in['amount']; // plain title + amount
        $extraTitle = clean_line($in['title'] ?? '', 200);
        if ($extraTitle === '') {
            throw new UserError('شرح خرید را بنویسید (چه چیزی خریده شد).');
        }
    }
    if ($extraAmount !== '') {
        $p['items'][] = ['title' => $extraTitle !== '' ? $extraTitle : 'سایر', 'qty' => null, 'unit_price' => null,
            'amount' => amount_in($extraAmount, 'مبلغ «' . ($extraTitle !== '' ? $extraTitle : 'سایر') . '»'), 'from_invoice' => 0];
    } elseif ($extraTitle !== '') {
        throw new UserError('مبلغ «' . $extraTitle . '» را بنویسید.');
    }
    if (!$p['items']) {
        throw new UserError('اقلامی را که خریده‌اید علامت بزنید و قیمت خرید هر کدام را بنویسید.');
    }
    $p['title'] = str_cut(implode('، ', array_column($p['items'], 'title')), 300);
    $p['amount'] = (int) array_sum(array_column($p['items'], 'amount'));
    if ($p['amount'] > MAX_AMOUNT) {
        throw new UserError('مبلغ خرید بیش از حد بزرگ است.');
    }
    return $p;
}

function purchase_save(array $p, ?int $id = null): int
{
    $rid = tx(function (PDO $pdo) use ($p, $id) {
        if ($id === null) {
            $pdo->prepare('INSERT INTO purchases (number, date, doc_id, supplier, title, amount, note, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([purchase_next_number(), $p['date'], $p['doc_id'], $p['supplier'], $p['title'],
                    $p['amount'], $p['note'], now()]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE purchases SET date = ?, doc_id = ?, supplier = ?, title = ?, amount = ?, note = ?
                           WHERE id = ?')
                ->execute([$p['date'], $p['doc_id'], $p['supplier'], $p['title'], $p['amount'], $p['note'], $id]);
            // Payments made for this purchase follow it to its (possibly new) invoice.
            $pdo->prepare('UPDATE expenses SET doc_id = ? WHERE purchase_id = ?')->execute([$p['doc_id'], $id]);
        }
        $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);
        $st = $pdo->prepare('INSERT INTO purchase_items (purchase_id, pos, title, qty, unit_price, amount, from_invoice)
                             VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach (array_values($p['items']) as $i => $it) {
            $st->execute([$id, $i + 1, $it['title'], $it['qty'], $it['unit_price'], $it['amount'], $it['from_invoice']]);
        }
        return $id;
    });
    $saved = purchase_get($rid);
    activity_log($id === null ? 'ثبت خرید' : 'ویرایش خرید', $saved['number'] . '، ' . $saved['title'] . '، '
        . money($saved['amount']) . ' ' . setting('unit') . '، فاکتور ' . $saved['doc_number'], url('purchase', ['id' => $rid]));
    return $rid;
}

function purchase_delete(int $id): void
{
    $old = purchase_get($id);
    tx(function (PDO $pdo) use ($id) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE purchase_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new UserError('برای این خرید پرداخت ثبت شده است؛ اول پرداخت‌های آن را حذف کنید.');
        }
        $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);
    });
    if ($old) {
        activity_log('حذف خرید', $old['number'] . '، ' . $old['title'] . '، ' . money($old['amount']));
    }
}

function purchases_list(string $q = '', int $docId = 0, int $limit = 300): array
{
    $sql = PURCHASE_SQL . ' WHERE 1 = 1';
    $args = [];
    if ($docId) {
        $sql .= ' AND p.doc_id = ?';
        $args[] = $docId;
    }
    if ($q !== '') {
        $sql .= " AND (p.number LIKE ? ESCAPE '\\' OR d.number LIKE ? ESCAPE '\\' OR p.supplier LIKE ? ESCAPE '\\'
                       OR p.title LIKE ? ESCAPE '\\' OR d.cust_name LIKE ? ESCAPE '\\')";
        $num = '%' . like_escape(en_digits($q)) . '%';
        $txt = '%' . like_escape(fa_norm($q)) . '%';
        array_push($args, $num, $num, $txt, $txt, $txt);
    }
    $sql .= ' ORDER BY p.date DESC, p.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** How much of a purchase is still unpaid to its supplier. */
function purchase_left(array $p): float
{
    return max(0.0, (float) $p['amount'] - (float) $p['paid']);
}

function purchase_badge(array $p): string
{
    $left = purchase_left($p);
    if ($left < 0.5) {
        return '<span class="badge ok">پرداخت‌شده</span>';
    }
    if ((float) $p['paid'] > 0) {
        return '<span class="badge warn">مانده ' . money($left) . '</span>';
    }
    return '<span class="badge danger">پرداخت‌نشده</span>';
}

function suppliers_known(): array
{
    return db()->query("SELECT DISTINCT supplier FROM purchases WHERE supplier <> '' ORDER BY supplier")
        ->fetchAll(PDO::FETCH_COLUMN);
}

// --- Expenses (money paid out: direct per invoice, or company overhead) ----

const EXPENSE_SQL = "SELECT e.*, d.number AS doc_number, d.cust_name, p.number AS purchase_number
    FROM expenses e LEFT JOIN docs d ON d.id = e.doc_id LEFT JOIN purchases p ON p.id = e.purchase_id";

function expense_get(int $id): ?array
{
    $st = db()->prepare(EXPENSE_SQL . ' WHERE e.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function expense_validate(array $in, ?array $old = null): array
{
    $kind = ($in['kind'] ?? '') === 'overhead' ? 'overhead' : 'direct';
    $method = clean_line($in['method'] ?? '', 40);
    $e = [
        'kind' => $kind,
        'doc_id' => null,
        'purchase_id' => null,
        'category' => clean_line($in['category'] ?? '', 80),
        'payee' => clean_line($in['payee'] ?? '', 150),
        'amount' => amount_in($in['amount'] ?? '', 'مبلغ پرداختی'),
        'date' => date_in($in['date'] ?? ''),
        'method' => in_array($method, PAY_METHODS, true) ? $method : 'سایر',
        'note' => clean_line($in['note'] ?? '', 300),
    ];
    if ($kind === 'direct') {
        $pid = (int) ($in['purchase_id'] ?? 0);
        if ($pid) {
            $p = purchase_get($pid);
            if (!$p) {
                throw new UserError('خرید انتخاب‌شده پیدا نشد.');
            }
            // A purchase payment always belongs to that purchase's invoice.
            $e['purchase_id'] = $pid;
            $e['doc_id'] = (int) $p['doc_id'];
            if ($e['category'] === '') {
                $e['category'] = DIRECT_CATEGORIES[0];
            }
            if ($e['payee'] === '') {
                $e['payee'] = $p['supplier'];
            }
        } else {
            $e['doc_id'] = invoice_ref((int) ($in['doc_id'] ?? 0), $old ? (int) $old['doc_id'] : 0);
        }
    }
    if ($e['category'] === '') {
        throw new UserError('بابت چه بوده؟ دستهٔ پرداخت را بنویسید (مثلاً «پیک» یا «اجاره»).');
    }
    return $e;
}

function expense_save(array $e, ?int $id = null): int
{
    $pdo = db();
    $vals = [$e['kind'], $e['doc_id'], $e['purchase_id'], $e['category'], $e['payee'], $e['amount'],
        $e['date'], $e['method'], $e['note']];
    if ($id === null) {
        $pdo->prepare('INSERT INTO expenses (kind, doc_id, purchase_id, category, payee, amount, date, method, note,
                           created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(array_merge($vals, [now()]));
        $id = (int) $pdo->lastInsertId();
        activity_log('ثبت پرداختی', expense_summary($e), url('expense', ['id' => $id]));
        return $id;
    }
    $pdo->prepare('UPDATE expenses SET kind = ?, doc_id = ?, purchase_id = ?, category = ?, payee = ?, amount = ?,
                       date = ?, method = ?, note = ? WHERE id = ?')
        ->execute(array_merge($vals, [$id]));
    activity_log('ویرایش پرداختی', expense_summary($e), url('expense', ['id' => $id]));
    return $id;
}

function expense_delete(int $id): void
{
    $old = expense_get($id);
    db()->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
    if ($old) {
        activity_log('حذف پرداختی', expense_summary($old));
    }
}

function expense_summary(array $e): string
{
    return EXPENSE_KIND_SHORT[$e['kind']] . '، ' . $e['category'] . ($e['payee'] !== '' ? '، ' . $e['payee'] : '')
        . '، ' . money($e['amount']) . ' ' . setting('unit');
}

function expenses_list(string $kind = '', string $q = '', int $docId = 0, int $limit = 300): array
{
    $sql = EXPENSE_SQL . ' WHERE 1 = 1';
    $args = [];
    if (isset(EXPENSE_KINDS[$kind])) {
        $sql .= ' AND e.kind = ?';
        $args[] = $kind;
    }
    if ($docId) {
        $sql .= ' AND e.doc_id = ?';
        $args[] = $docId;
    }
    if ($q !== '') {
        $sql .= " AND (e.category LIKE ? ESCAPE '\\' OR e.payee LIKE ? ESCAPE '\\' OR e.note LIKE ? ESCAPE '\\'
                       OR d.number LIKE ? ESCAPE '\\' OR p.number LIKE ? ESCAPE '\\')";
        $txt = '%' . like_escape(fa_norm($q)) . '%';
        $num = '%' . like_escape(en_digits($q)) . '%';
        array_push($args, $txt, $txt, $txt, $num, $num);
    }
    $sql .= ' ORDER BY e.date DESC, e.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function expenses_of_purchase(int $purchaseId): array
{
    $st = db()->prepare(EXPENSE_SQL . ' WHERE e.purchase_id = ? ORDER BY e.date, e.id');
    $st->execute([$purchaseId]);
    return $st->fetchAll();
}

/** Payees seen before (suppliers included), for the form's suggestions. */
function payees_known(): array
{
    return db()->query("SELECT payee AS n FROM expenses WHERE payee <> ''
                        UNION SELECT supplier FROM purchases WHERE supplier <> '' ORDER BY n")
        ->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Cost and gross profit of one invoice. Cost = its purchases + its other
 * direct costs (a payment linked to a purchase only settles that purchase,
 * so it is not counted twice). Profit is on the net sale, VAT excluded.
 */
function invoice_costs(array $doc): array
{
    $id = (int) $doc['id'];
    $st = db()->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM purchases WHERE doc_id = ?');
    $st->execute([$id]);
    [$pCount, $pSum] = $st->fetch(PDO::FETCH_NUM);
    $st = db()->prepare("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM expenses
                         WHERE doc_id = ? AND kind = 'direct' AND purchase_id IS NULL");
    $st->execute([$id]);
    [$oCount, $oSum] = $st->fetch(PDO::FETCH_NUM);
    $st = db()->prepare('SELECT COALESCE(SUM(e.amount), 0) FROM expenses e
                         JOIN purchases p ON p.id = e.purchase_id WHERE p.doc_id = ?');
    $st->execute([$id]);
    $paid = (float) $st->fetchColumn();

    $net = (float) $doc['subtotal'] - (float) $doc['discount'];
    $cost = (float) $pSum + (float) $oSum;
    return [
        'purchase_count' => (int) $pCount, 'purchases' => (float) $pSum,
        'purchases_left' => max(0.0, (float) $pSum - $paid),
        'other_count' => (int) $oCount, 'other' => (float) $oSum,
        'cost' => $cost, 'net_sales' => $net, 'profit' => $net - $cost,
        'margin' => $net > 0 ? ($net - $cost) / $net * 100 : null,
    ];
}

// --- Dashboard --------------------------------------------------------------

function dashboard_stats(): array
{
    $pdo = db();
    $month = substr(jtoday(), 0, 8) . '%'; // «1405/07/%»
    $receivable = 0.0;
    $debtors = 0;
    foreach (customers_list() as $c) {
        if ((float) $c['balance'] > 0) {
            $receivable += (float) $c['balance'];
            $debtors++;
        }
    }
    $st = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total), 0) FROM docs
                         WHERE type = 'inv' AND status = 'active' AND date LIKE ?");
    $st->execute([$month]);
    [$invCount, $invSum] = $st->fetch(PDO::FETCH_NUM);
    $st = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM payments WHERE date LIKE ?');
    $st->execute([$month]);
    [$payCount, $paySum] = $st->fetch(PDO::FETCH_NUM);
    $openQot = (int) $pdo->query("SELECT COUNT(*) FROM docs d WHERE d.type = 'qot'
        AND NOT EXISTS (SELECT 1 FROM docs x WHERE x.source_id = d.id AND x.type = 'inv')")->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM purchases WHERE date LIKE ?');
    $st->execute([$month]);
    [$purCount, $purSum] = $st->fetch(PDO::FETCH_NUM);
    $exp = ['direct' => 0.0, 'overhead' => 0.0];
    $st = $pdo->prepare('SELECT kind, COALESCE(SUM(amount), 0) FROM expenses WHERE date LIKE ? GROUP BY kind');
    $st->execute([$month]);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$k, $sum]) {
        $exp[$k] = (float) $sum;
    }
    return [
        'receivable' => $receivable, 'debtors' => $debtors,
        'inv_count' => (int) $invCount, 'inv_sum' => (float) $invSum,
        'pay_count' => (int) $payCount, 'pay_sum' => (float) $paySum,
        'open_qot' => $openQot,
        'pur_count' => (int) $purCount, 'pur_sum' => (float) $purSum,
        'exp_direct' => $exp['direct'], 'exp_overhead' => $exp['overhead'],
    ];
}
