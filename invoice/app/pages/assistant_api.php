<?php
/**
 * Assistant backend: turns a chat message into proforma/invoice/payment actions.
 *
 * The browser POSTs the running conversation; this endpoint runs an agentic
 * tool-use loop against the Claude Messages API (raw HTTPS via cURL — the app
 * has no Composer/SDK). Every tool maps to an existing model.php function, so
 * all writes go through the same validation and transactions as the UI.
 *
 * Returns JSON only. Login + CSRF are already enforced by index.php.
 */
defined('APP_DIR') || exit;

header('Content-Type: application/json; charset=utf-8');

function areply(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$key = setting('assistant_api_key');
$model = setting('assistant_model') ?: 'claude-opus-5-5';
if ($key === '') {
    areply(['ok' => false, 'reply' => 'کلید API هنوز تنظیم نشده است. از صفحهٔ «تنظیمات» کلید Anthropic را وارد کنید.']);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['messages']) || !is_array($payload['messages'])) {
    // Fallback: form-encoded `payload` field.
    $payload = json_decode(post('payload'), true);
}
$history = (is_array($payload) && isset($payload['messages'])) ? $payload['messages'] : [];

// Build the API messages from {role,text} turns (keep the last 24).
$messages = [];
foreach (array_slice($history, -24) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = trim((string) ($m['text'] ?? ''));
    if ($text !== '') {
        $messages[] = ['role' => $role, 'content' => $text];
    }
}
if (!$messages || $messages[count($messages) - 1]['role'] !== 'user') {
    areply(['ok' => false, 'reply' => 'پیامی برای پردازش نبود.']);
}

$unit = setting('unit') ?: 'ریال';
$today = jtoday();
$sys = "تو دستیار صدور سند شرکت «" . setting('company_name') . "» هستی و از طریق چت کمک می‌کنی.\n"
    . "کارهایت: ساخت پیش‌فاکتور، صدور فاکتور، تبدیل پیش‌فاکتور به فاکتور، ثبت دریافتی، و نمایش مانده/سوابق مشتری — فقط با ابزارهای داده‌شده.\n"
    . "قواعد:\n"
    . "- همیشه فارسی و کوتاه و محترمانه پاسخ بده.\n"
    . "- واحد پول «{$unit}» است. مبالغ را عدد صحیح بده. «میلیون» را در ۱۰۰۰۰۰۰ و «هزار» را در ۱۰۰۰ ضرب کن (مثلاً «۵ میلیون» = 5000000).\n"
    . "- تاریخ امروز «{$today}» (شمسی) است؛ اگر تاریخ گفته نشد همین را به‌کار ببر.\n"
    . "- اگر چیزی مبهم است (نام مشتری، مبلغ، قلم‌ها یا قیمت‌ها) اول با یک سؤال کوتاه بپرس، بعد اقدام کن.\n"
    . "- پیش از ثبت، اگر چند مشتری با آن نام هست با find_customers شفاف کن.\n"
    . "- بعد از هر اقدام، نتیجه را با شمارهٔ سند/مبلغ و خلاصهٔ مانده گزارش بده.\n"
    . "- پیش‌فاکتور روی حساب مشتری اثر ندارد؛ فقط فاکتور، مشتری را بدهکار می‌کند.\n"
    . "- ابطال/حذف انجام نده؛ اگر خواستند بگو این کارها را دستی در خود برنامه انجام دهند.";

$tools = assistant_tools();

$actions = [];
$lastText = '';
for ($i = 0; $i < 8; $i++) {
    $resp = claude_messages($key, $model, $sys, $messages, $tools);
    if (!$resp['ok']) {
        areply(['ok' => false, 'reply' => $resp['error']]);
    }
    $data = $resp['data'];
    $stop = $data['stop_reason'] ?? '';
    if ($stop === 'refusal') {
        areply(['ok' => false, 'reply' => 'این درخواست پردازش نشد. لطفاً به شکل دیگری بیان کنید.']);
    }
    $content = $data['content'] ?? [];
    // Collect any assistant text.
    foreach ($content as $b) {
        if (($b['type'] ?? '') === 'text' && trim($b['text'] ?? '') !== '') {
            $lastText = trim($b['text']);
        }
    }
    if ($stop !== 'tool_use') {
        break;
    }
    // Echo the assistant turn back, then run each tool and return results.
    $messages[] = ['role' => 'assistant', 'content' => $content];
    $results = [];
    foreach ($content as $b) {
        if (($b['type'] ?? '') !== 'tool_use') {
            continue;
        }
        $out = assistant_run_tool($b['name'] ?? '', is_array($b['input'] ?? null) ? $b['input'] : [], $actions);
        $results[] = [
            'type' => 'tool_result',
            'tool_use_id' => $b['id'] ?? '',
            'content' => $out['text'],
            'is_error' => !empty($out['error']),
        ];
    }
    $messages[] = ['role' => 'user', 'content' => $results];
}

areply(['ok' => true, 'reply' => $lastText !== '' ? $lastText : 'انجام شد.', 'actions' => array_values($actions)]);

// --- Claude Messages API (raw HTTPS) ---------------------------------------

function claude_messages(string $key, string $model, string $system, array $messages, array $tools): array
{
    $body = [
        'model' => $model,
        'max_tokens' => 2048,
        'system' => $system,
        'tools' => $tools,
        'messages' => $messages,
    ];
    // effort is supported on opus/sonnet/fable; Haiku rejects it.
    if (stripos($model, 'haiku') === false) {
        $body['output_config'] = ['effort' => 'low'];
    }
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'ارتباط با سرویس هوش مصنوعی برقرار نشد: ' . $err];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true);
    if ($code !== 200 || !is_array($data)) {
        $msg = $data['error']['message'] ?? ('کد پاسخ ' . $code);
        if ($code === 401) {
            $msg = 'کلید API معتبر نیست. در تنظیمات، کلید درست را وارد کنید.';
        } elseif ($code === 429) {
            $msg = 'محدودیت نرخ درخواست. کمی بعد دوباره تلاش کنید.';
        }
        return ['ok' => false, 'error' => 'خطای سرویس هوش مصنوعی: ' . $msg];
    }
    return ['ok' => true, 'data' => $data];
}

// --- Tool definitions -------------------------------------------------------

function assistant_tools(): array
{
    $item = [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'شرح کالا یا خدمت'],
            'qty' => ['type' => 'number', 'description' => 'تعداد (اختیاری، پیش‌فرض ۱)'],
            'unit_price' => ['type' => 'number', 'description' => 'قیمت واحد به ' . (setting('unit') ?: 'ریال')],
        ],
        'required' => ['title'],
    ];
    $docFields = [
        'customer_name' => ['type' => 'string', 'description' => 'نام مشتری'],
        'phone' => ['type' => 'string', 'description' => 'تلفن مشتری (اختیاری)'],
        'address' => ['type' => 'string', 'description' => 'آدرس مشتری (اختیاری)'],
        'items' => ['type' => 'array', 'items' => $item, 'description' => 'ردیف‌های سند'],
        'discount' => ['type' => 'number', 'description' => 'تخفیف کل (اختیاری)'],
        'vat' => ['type' => 'boolean', 'description' => 'افزودن مالیات بر ارزش افزوده (اختیاری)'],
        'date' => ['type' => 'string', 'description' => 'تاریخ شمسی مثل ۱۴۰۵/۰۱/۲۰ (اختیاری)'],
        'notes' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'یادداشت‌ها (اختیاری)'],
    ];
    return [
        [
            'name' => 'find_customers',
            'description' => 'جستجوی مشتری بر اساس نام یا تلفن. برای رفع ابهام نام از این استفاده کن.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'query' => ['type' => 'string', 'description' => 'بخشی از نام یا تلفن'],
            ], 'required' => ['query']],
        ],
        [
            'name' => 'customer_balance',
            'description' => 'نمایش مانده حساب و خلاصهٔ سوابق یک مشتری.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'customer' => ['type' => 'string', 'description' => 'نام مشتری یا شناسهٔ عددی'],
            ], 'required' => ['customer']],
        ],
        [
            'name' => 'create_proforma',
            'description' => 'ساخت پیش‌فاکتور جدید. روی حساب مشتری اثری ندارد.',
            'input_schema' => ['type' => 'object', 'properties' => $docFields, 'required' => ['customer_name', 'items']],
        ],
        [
            'name' => 'create_invoice',
            'description' => 'صدور فاکتور مستقیم. مشتری به اندازهٔ مبلغ قابل پرداخت بدهکار می‌شود.',
            'input_schema' => ['type' => 'object', 'properties' => $docFields, 'required' => ['customer_name', 'items']],
        ],
        [
            'name' => 'convert_proforma',
            'description' => 'تبدیل یک پیش‌فاکتور به فاکتور با شمارهٔ آن (مثل QOT-0007).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'number' => ['type' => 'string', 'description' => 'شمارهٔ پیش‌فاکتور'],
            ], 'required' => ['number']],
        ],
        [
            'name' => 'record_payment',
            'description' => 'ثبت دریافتی از مشتری. از بدهی او کم می‌شود (قدیمی‌ترین بدهی اول).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'customer' => ['type' => 'string', 'description' => 'نام مشتری یا شناسهٔ عددی'],
                'amount' => ['type' => 'number', 'description' => 'مبلغ دریافتی'],
                'date' => ['type' => 'string', 'description' => 'تاریخ شمسی (اختیاری)'],
                'method' => ['type' => 'string', 'description' => 'روش: نقدی، کارت به کارت، واریز / حواله، چک، سایر (اختیاری)'],
                'note' => ['type' => 'string', 'description' => 'یادداشت (اختیاری)'],
            ], 'required' => ['customer', 'amount']],
        ],
        [
            'name' => 'list_docs',
            'description' => 'فهرست آخرین پیش‌فاکتورها یا فاکتورها.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'type' => ['type' => 'string', 'enum' => ['qot', 'inv'], 'description' => 'qot=پیش‌فاکتور، inv=فاکتور'],
                'query' => ['type' => 'string', 'description' => 'جستجو در شماره یا نام (اختیاری)'],
            ], 'required' => ['type']],
        ],
    ];
}

// --- Tool execution (maps to model.php) ------------------------------------

function assistant_run_tool(string $name, array $in, array &$actions): array
{
    try {
        switch ($name) {
            case 'find_customers':
                return ['text' => _a_find_customers((string) ($in['query'] ?? ''))];
            case 'customer_balance':
                return ['text' => _a_customer_balance((string) ($in['customer'] ?? ''))];
            case 'create_proforma':
                return ['text' => _a_create_doc('qot', $in, $actions)];
            case 'create_invoice':
                return ['text' => _a_create_doc('inv', $in, $actions)];
            case 'convert_proforma':
                return ['text' => _a_convert((string) ($in['number'] ?? ''), $actions)];
            case 'record_payment':
                return ['text' => _a_record_payment($in, $actions)];
            case 'list_docs':
                return ['text' => _a_list_docs((string) ($in['type'] ?? 'inv'), (string) ($in['query'] ?? ''))];
        }
        return ['text' => 'ابزار ناشناخته.', 'error' => true];
    } catch (UserError $ex) {
        return ['text' => 'خطا: ' . $ex->getMessage(), 'error' => true];
    } catch (Throwable $ex) {
        return ['text' => 'خطای داخلی هنگام اجرای ابزار.', 'error' => true];
    }
}

function _a_resolve_customer(string $s): array
{
    $s = trim($s);
    if ($s !== '' && ctype_digit($s)) {
        $c = customer_get((int) $s);
        if ($c) {
            return $c;
        }
    }
    $list = customers_list($s);
    if (!$list) {
        throw new UserError('مشتری‌ای با «' . $s . '» پیدا نشد.');
    }
    foreach ($list as $c) {
        if (name_key($c['name']) === name_key($s)) {
            return $c;
        }
    }
    if (count($list) > 1) {
        $names = array_map(function ($c) {
            return $c['name'];
        }, array_slice($list, 0, 6));
        throw new UserError('چند مشتری پیدا شد: ' . implode('، ', $names) . '. دقیق‌تر مشخص کنید.');
    }
    return $list[0];
}

function _a_find_customers(string $q): string
{
    $list = customers_list($q);
    if (!$list) {
        return 'هیچ مشتری‌ای پیدا نشد.';
    }
    $out = [];
    foreach (array_slice($list, 0, 10) as $c) {
        $out[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone'],
            'balance' => (int) $c['balance']];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

function _a_customer_balance(string $who): string
{
    $c = _a_resolve_customer($who);
    $led = customer_ledger($c);
    $inv = 0;
    $pay = 0;
    foreach ($led as $r) {
        if (($r['kind'] ?? '') === 'inv') {
            $inv++;
        } elseif (($r['kind'] ?? '') === 'pay') {
            $pay++;
        }
    }
    return json_encode([
        'id' => (int) $c['id'], 'name' => $c['name'],
        'balance' => (int) $c['balance'],
        'balance_text' => money((float) $c['balance']) . ' ' . (setting('unit') ?: 'ریال')
            . ((float) $c['balance'] > 0 ? ' (بدهکار)' : ((float) $c['balance'] < 0 ? ' (بستانکار)' : '')),
        'invoices' => $inv, 'payments' => $pay,
    ], JSON_UNESCAPED_UNICODE);
}

function _a_create_doc(string $type, array $in, array &$actions): string
{
    $items = [];
    foreach ((is_array($in['items'] ?? null) ? $in['items'] : []) as $it) {
        if (!is_array($it)) {
            continue;
        }
        $items[] = [
            'title' => (string) ($it['title'] ?? ''),
            'qty' => isset($it['qty']) && $it['qty'] !== '' ? $it['qty'] : null,
            'price' => isset($it['unit_price']) && $it['unit_price'] !== '' ? $it['unit_price'] : null,
        ];
    }
    $form = [
        'cust_name' => (string) ($in['customer_name'] ?? ''),
        'cust_phone' => (string) ($in['phone'] ?? ''),
        'cust_address' => (string) ($in['address'] ?? ''),
        'date' => trim((string) ($in['date'] ?? '')) !== '' ? (string) $in['date'] : jtoday(),
        'number_auto' => 1,
        'items' => $items,
        'discount' => $in['discount'] ?? 0,
        'vat_on' => !empty($in['vat']),
        'notes' => is_array($in['notes'] ?? null) ? $in['notes'] : default_notes($type),
    ];
    $d = doc_validate($form, $type);
    $id = doc_save($d);
    $doc = doc_get($id);
    $actions[] = ['label' => DOC_NAMES[$type] . ' ' . $doc['number'], 'url' => url('doc', ['id' => $id])];
    return json_encode([
        'ok' => true, 'number' => $doc['number'], 'id' => $id,
        'total' => (int) $doc['total'],
        'total_text' => money((float) $doc['total']) . ' ' . (setting('unit') ?: 'ریال'),
        'type' => DOC_NAMES[$type],
        'url' => url('doc', ['id' => $id]),
    ], JSON_UNESCAPED_UNICODE);
}

function _a_convert(string $number, array &$actions): string
{
    $number = en_digits(trim($number));
    $st = db()->prepare("SELECT id FROM docs WHERE type = 'qot' AND number = ? LIMIT 1");
    $st->execute([$number]);
    $qid = (int) $st->fetchColumn();
    if (!$qid) {
        throw new UserError('پیش‌فاکتور «' . $number . '» پیدا نشد.');
    }
    $invId = doc_convert($qid);
    $inv = doc_get($invId);
    $actions[] = ['label' => 'فاکتور ' . $inv['number'], 'url' => url('doc', ['id' => $invId])];
    return json_encode([
        'ok' => true, 'invoice_number' => $inv['number'], 'id' => $invId,
        'total_text' => money((float) $inv['total']) . ' ' . (setting('unit') ?: 'ریال'),
        'url' => url('doc', ['id' => $invId]),
    ], JSON_UNESCAPED_UNICODE);
}

function _a_record_payment(array $in, array &$actions): string
{
    $c = _a_resolve_customer((string) ($in['customer'] ?? ''));
    $form = [
        'customer_id' => (int) $c['id'],
        'amount' => $in['amount'] ?? '',
        'date' => trim((string) ($in['date'] ?? '')) !== '' ? (string) $in['date'] : jtoday(),
        'method' => (string) ($in['method'] ?? 'نقدی'),
        'note' => (string) ($in['note'] ?? ''),
    ];
    $p = payment_validate($form);
    payment_save($p);
    $fresh = customer_get((int) $c['id']);
    $actions[] = ['label' => 'حساب ' . $c['name'], 'url' => url('customer', ['id' => (int) $c['id']])];
    return json_encode([
        'ok' => true, 'customer' => $c['name'],
        'amount_text' => money((float) $p['amount']) . ' ' . (setting('unit') ?: 'ریال'),
        'new_balance' => (int) $fresh['balance'],
        'new_balance_text' => money((float) $fresh['balance']) . ' ' . (setting('unit') ?: 'ریال')
            . ((float) $fresh['balance'] > 0 ? ' (بدهکار)' : ((float) $fresh['balance'] < 0 ? ' (بستانکار)' : ' (تسویه)')),
        'url' => url('customer', ['id' => (int) $c['id']]),
    ], JSON_UNESCAPED_UNICODE);
}

function _a_list_docs(string $type, string $q): string
{
    $type = $type === 'qot' ? 'qot' : 'inv';
    $rows = docs_list($type, $q, 0, 15);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['number' => $r['number'], 'date' => $r['date'], 'customer' => $r['cust_name'],
            'total' => (int) $r['total']];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
