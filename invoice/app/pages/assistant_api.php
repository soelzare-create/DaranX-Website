<?php
/**
 * Assistant backend: turns a chat message into proforma/invoice/payment actions.
 *
 * Runs an agentic tool-use loop against an AI service. It supports two wire
 * formats, chosen by the Base URL in settings:
 *   - a host containing "anthropic"  → native Anthropic Messages API
 *   - anything else (e.g. GapGPT)     → OpenAI-compatible /chat/completions
 * Every tool maps to an existing model.php function, so all writes go through
 * the same validation and transactions as the UI.
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
$base = rtrim(setting('assistant_base_url') ?: 'https://api.anthropic.com', '/');
$model = setting('assistant_model') ?: 'claude-opus-5-5';
$provider = stripos($base, 'anthropic') !== false ? 'anthropic' : 'openai';
if ($key === '') {
    areply(['ok' => false, 'reply' => 'کلید API هنوز تنظیم نشده است. از صفحهٔ «تنظیمات» کلید را وارد کنید.']);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['messages'])) {
    $payload = json_decode(post('payload'), true); // form-encoded fallback
}
$incoming = (is_array($payload) && isset($payload['messages']) && is_array($payload['messages'])) ? $payload['messages'] : [];

// Provider-neutral conversation. Each turn is one of:
//   ['kind'=>'text', 'role'=>'user'|'assistant', 'text'=>...]
//   ['kind'=>'calls', 'text'=>..., 'calls'=>[['id','name','input'=>array], ...]]
//   ['kind'=>'result', 'id'=>, 'name'=>, 'result'=>string]
$turns = [];
foreach (array_slice($incoming, -24) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = trim((string) ($m['text'] ?? ''));
    if ($text !== '') {
        $turns[] = ['kind' => 'text', 'role' => $role, 'text' => $text];
    }
}
if (!$turns || end($turns)['role'] !== 'user') {
    areply(['ok' => false, 'reply' => 'پیامی برای پردازش نبود.']);
}

$unit = setting('unit') ?: 'ریال';
$today = jtoday();
$sys = "تو دستیار صدور سند شرکت «" . setting('company_name') . "» هستی و از طریق چت کمک می‌کنی.\n"
    . "کارهایت: ساخت پیش‌فاکتور، صدور فاکتور، تبدیل پیش‌فاکتور به فاکتور، ثبت دریافتی، و نمایش مانده/سوابق مشتری؛ فقط با ابزارهای داده‌شده.\n"
    . "قواعد:\n"
    . "- همیشه فارسی و کوتاه و محترمانه پاسخ بده.\n"
    . "- واحد پول «{$unit}» است. مبالغ را عدد صحیح بده. «میلیون» را در ۱۰۰۰۰۰۰ و «هزار» را در ۱۰۰۰ ضرب کن (مثلاً «۵ میلیون» = 5000000).\n"
    . "- تاریخ امروز «{$today}» (شمسی) است؛ اگر تاریخ گفته نشد همین را به‌کار ببر.\n"
    . "- اگر چیزی مبهم است (نام مشتری، مبلغ، قلم‌ها یا قیمت‌ها) اول با یک سؤال کوتاه بپرس، بعد اقدام کن.\n"
    . "- پیش از ثبت، اگر چند مشتری با آن نام هست با find_customers شفاف کن.\n"
    . "- بعد از هر اقدام، نتیجه را با شمارهٔ سند/مبلغ و خلاصهٔ مانده گزارش بده.\n"
    . "- پیش‌فاکتور روی حساب مشتری اثر ندارد؛ فقط فاکتور، مشتری را بدهکار می‌کند.\n"
    . "- ابطال/حذف انجام نده؛ اگر خواستند بگو این کارها را دستی در خود برنامه انجام دهند.";

$actions = [];
$lastText = '';
for ($i = 0; $i < 8; $i++) {
    $r = ai_complete($provider, $base, $key, $model, $sys, $turns);
    if (!$r['ok']) {
        areply(['ok' => false, 'reply' => $r['error']]);
    }
    if (trim($r['text']) !== '') {
        $lastText = trim($r['text']);
    }
    if (empty($r['calls'])) {
        break;
    }
    $turns[] = ['kind' => 'calls', 'text' => $r['text'], 'calls' => $r['calls']];
    foreach ($r['calls'] as $call) {
        $out = assistant_run_tool($call['name'], is_array($call['input'] ?? null) ? $call['input'] : [], $actions);
        $turns[] = ['kind' => 'result', 'id' => $call['id'], 'name' => $call['name'], 'result' => $out['text']];
    }
}

areply(['ok' => true, 'reply' => $lastText !== '' ? $lastText : 'انجام شد.', 'actions' => array_values($actions)]);

// --- AI service (two wire formats, one normalized interface) ----------------

function ai_complete(string $provider, string $base, string $key, string $model, string $system, array $turns): array
{
    if ($provider === 'anthropic') {
        return ai_anthropic($base, $key, $model, $system, $turns);
    }
    return ai_openai($base, $key, $model, $system, $turns);
}

function http_json(string $url, array $headers, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $err = $raw === false ? curl_error($ch) : '';
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['raw' => $raw, 'err' => $err, 'code' => $code, 'data' => is_string($raw) ? json_decode($raw, true) : null];
}

function ai_err(int $code, $data): string
{
    if ($code === 401 || $code === 403) {
        return 'کلید API یا آدرس سرویس درست نیست. در تنظیمات بررسی کنید.';
    }
    if ($code === 429) {
        return 'محدودیت نرخ درخواست. کمی بعد دوباره تلاش کنید.';
    }
    $m = (is_array($data) && isset($data['error'])) ? ($data['error']['message'] ?? (is_string($data['error']) ? $data['error'] : '')) : '';
    return 'خطای سرویس هوش مصنوعی' . ($m !== '' ? ': ' . $m : (' (کد ' . $code . ')'));
}

function ai_anthropic(string $base, string $key, string $model, string $system, array $turns): array
{
    $url = strpos($base, '/messages') !== false ? $base : $base . '/v1/messages';
    $messages = [];
    foreach ($turns as $t) {
        if ($t['kind'] === 'text') {
            $messages[] = ['role' => $t['role'], 'content' => $t['text']];
        } elseif ($t['kind'] === 'calls') {
            $content = [];
            if (trim($t['text']) !== '') {
                $content[] = ['type' => 'text', 'text' => $t['text']];
            }
            foreach ($t['calls'] as $c) {
                $content[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['name'], 'input' => (object) $c['input']];
            }
            $messages[] = ['role' => 'assistant', 'content' => $content];
        } else { // result
            $messages[] = ['role' => 'user', 'content' => [[
                'type' => 'tool_result', 'tool_use_id' => $t['id'], 'content' => $t['result'],
            ]]];
        }
    }
    $body = [
        'model' => $model, 'max_tokens' => 2048, 'system' => $system,
        'tools' => assistant_tools('anthropic'), 'messages' => $messages,
    ];
    if (stripos($model, 'haiku') === false) {
        $body['output_config'] = ['effort' => 'low'];
    }
    $res = http_json($url, [
        'content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01',
    ], $body);
    if ($res['err']) {
        return ['ok' => false, 'error' => 'ارتباط با سرویس برقرار نشد: ' . $res['err']];
    }
    $data = $res['data'];
    if ($res['code'] !== 200 || !is_array($data)) {
        return ['ok' => false, 'error' => ai_err($res['code'], $data)];
    }
    if (($data['stop_reason'] ?? '') === 'refusal') {
        return ['ok' => false, 'error' => 'این درخواست پردازش نشد. لطفاً به شکل دیگری بیان کنید.'];
    }
    $text = '';
    $calls = [];
    foreach ($data['content'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'text') {
            $text .= $b['text'] ?? '';
        } elseif (($b['type'] ?? '') === 'tool_use') {
            $calls[] = ['id' => $b['id'] ?? '', 'name' => $b['name'] ?? '', 'input' => is_array($b['input'] ?? null) ? $b['input'] : []];
        }
    }
    return ['ok' => true, 'text' => $text, 'calls' => $calls];
}

function ai_openai(string $base, string $key, string $model, string $system, array $turns): array
{
    $url = strpos($base, '/chat/completions') !== false ? $base : $base . '/chat/completions';
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($turns as $t) {
        if ($t['kind'] === 'text') {
            $messages[] = ['role' => $t['role'], 'content' => $t['text']];
        } elseif ($t['kind'] === 'calls') {
            $tc = [];
            foreach ($t['calls'] as $c) {
                $tc[] = ['id' => $c['id'], 'type' => 'function',
                    'function' => ['name' => $c['name'], 'arguments' => json_encode($c['input'], JSON_UNESCAPED_UNICODE)]];
            }
            $messages[] = ['role' => 'assistant', 'content' => trim($t['text']) !== '' ? $t['text'] : null, 'tool_calls' => $tc];
        } else { // result
            $messages[] = ['role' => 'tool', 'tool_call_id' => $t['id'], 'content' => $t['result']];
        }
    }
    $body = [
        'model' => $model, 'max_tokens' => 2048, 'messages' => $messages,
        'tools' => assistant_tools('openai'), 'tool_choice' => 'auto',
    ];
    $res = http_json($url, [
        'content-type: application/json', 'authorization: Bearer ' . $key,
    ], $body);
    if ($res['err']) {
        return ['ok' => false, 'error' => 'ارتباط با سرویس برقرار نشد: ' . $res['err']];
    }
    $data = $res['data'];
    if ($res['code'] !== 200 || !is_array($data)) {
        return ['ok' => false, 'error' => ai_err($res['code'], $data)];
    }
    $msg = $data['choices'][0]['message'] ?? [];
    $text = is_string($msg['content'] ?? null) ? $msg['content'] : '';
    $calls = [];
    foreach ($msg['tool_calls'] ?? [] as $c) {
        $args = json_decode($c['function']['arguments'] ?? '{}', true);
        $calls[] = ['id' => $c['id'] ?? ('call_' . count($calls)), 'name' => $c['function']['name'] ?? '',
            'input' => is_array($args) ? $args : []];
    }
    return ['ok' => true, 'text' => $text, 'calls' => $calls];
}

// --- Tool definitions (same tools, wrapped per wire format) -----------------

function assistant_tools(string $provider): array
{
    $defs = assistant_tool_defs();
    if ($provider === 'anthropic') {
        return array_map(function ($t) {
            return ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['schema']];
        }, $defs);
    }
    return array_map(function ($t) {
        return ['type' => 'function', 'function' => [
            'name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['schema'],
        ]];
    }, $defs);
}

function assistant_tool_defs(): array
{
    $unit = setting('unit') ?: 'ریال';
    $item = ['type' => 'object', 'properties' => [
        'title' => ['type' => 'string', 'description' => 'شرح کالا یا خدمت'],
        'qty' => ['type' => 'number', 'description' => 'تعداد (اختیاری، پیش‌فرض ۱)'],
        'unit_price' => ['type' => 'number', 'description' => 'قیمت واحد به ' . $unit],
    ], 'required' => ['title']];
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
        ['name' => 'find_customers', 'description' => 'جستجوی مشتری بر اساس نام یا تلفن. برای رفع ابهام نام از این استفاده کن.',
            'schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'بخشی از نام یا تلفن']], 'required' => ['query']]],
        ['name' => 'customer_balance', 'description' => 'نمایش مانده حساب و خلاصهٔ سوابق یک مشتری.',
            'schema' => ['type' => 'object', 'properties' => ['customer' => ['type' => 'string', 'description' => 'نام مشتری یا شناسهٔ عددی']], 'required' => ['customer']]],
        ['name' => 'create_proforma', 'description' => 'ساخت پیش‌فاکتور جدید. روی حساب مشتری اثری ندارد.',
            'schema' => ['type' => 'object', 'properties' => $docFields, 'required' => ['customer_name', 'items']]],
        ['name' => 'create_invoice', 'description' => 'صدور فاکتور مستقیم. مشتری به اندازهٔ مبلغ قابل پرداخت بدهکار می‌شود.',
            'schema' => ['type' => 'object', 'properties' => $docFields, 'required' => ['customer_name', 'items']]],
        ['name' => 'convert_proforma', 'description' => 'تبدیل یک پیش‌فاکتور به فاکتور با شمارهٔ آن (مثل QOT-0007).',
            'schema' => ['type' => 'object', 'properties' => ['number' => ['type' => 'string', 'description' => 'شمارهٔ پیش‌فاکتور']], 'required' => ['number']]],
        ['name' => 'record_payment', 'description' => 'ثبت دریافتی از مشتری. از بدهی او کم می‌شود (قدیمی‌ترین بدهی اول).',
            'schema' => ['type' => 'object', 'properties' => [
                'customer' => ['type' => 'string', 'description' => 'نام مشتری یا شناسهٔ عددی'],
                'amount' => ['type' => 'number', 'description' => 'مبلغ دریافتی'],
                'date' => ['type' => 'string', 'description' => 'تاریخ شمسی (اختیاری)'],
                'method' => ['type' => 'string', 'description' => 'روش: نقدی، کارت به کارت، واریز / حواله، چک، سایر (اختیاری)'],
                'note' => ['type' => 'string', 'description' => 'یادداشت (اختیاری)'],
            ], 'required' => ['customer', 'amount']]],
        ['name' => 'list_docs', 'description' => 'فهرست آخرین پیش‌فاکتورها یا فاکتورها.',
            'schema' => ['type' => 'object', 'properties' => [
                'type' => ['type' => 'string', 'enum' => ['qot', 'inv'], 'description' => 'qot=پیش‌فاکتور، inv=فاکتور'],
                'query' => ['type' => 'string', 'description' => 'جستجو در شماره یا نام (اختیاری)'],
            ], 'required' => ['type']]],
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
        $out[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone'], 'balance' => (int) $c['balance']];
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
    $u = setting('unit') ?: 'ریال';
    return json_encode([
        'id' => (int) $c['id'], 'name' => $c['name'], 'balance' => (int) $c['balance'],
        'balance_text' => money((float) $c['balance']) . ' ' . $u
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
        'number_auto' => 1, 'items' => $items,
        'discount' => $in['discount'] ?? 0, 'vat_on' => !empty($in['vat']),
        'notes' => is_array($in['notes'] ?? null) ? $in['notes'] : default_notes($type),
    ];
    $d = doc_validate($form, $type);
    $id = doc_save($d);
    $doc = doc_get($id);
    $u = setting('unit') ?: 'ریال';
    $actions[] = ['label' => DOC_NAMES[$type] . ' ' . $doc['number'], 'url' => url('doc', ['id' => $id])];
    return json_encode([
        'ok' => true, 'number' => $doc['number'], 'id' => $id, 'total' => (int) $doc['total'],
        'total_text' => money((float) $doc['total']) . ' ' . $u, 'type' => DOC_NAMES[$type],
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
    $u = setting('unit') ?: 'ریال';
    $actions[] = ['label' => 'فاکتور ' . $inv['number'], 'url' => url('doc', ['id' => $invId])];
    return json_encode([
        'ok' => true, 'invoice_number' => $inv['number'], 'id' => $invId,
        'total_text' => money((float) $inv['total']) . ' ' . $u, 'url' => url('doc', ['id' => $invId]),
    ], JSON_UNESCAPED_UNICODE);
}

function _a_record_payment(array $in, array &$actions): string
{
    $c = _a_resolve_customer((string) ($in['customer'] ?? ''));
    $form = [
        'customer_id' => (int) $c['id'], 'amount' => $in['amount'] ?? '',
        'date' => trim((string) ($in['date'] ?? '')) !== '' ? (string) $in['date'] : jtoday(),
        'method' => (string) ($in['method'] ?? 'نقدی'), 'note' => (string) ($in['note'] ?? ''),
    ];
    $p = payment_validate($form);
    payment_save($p);
    $fresh = customer_get((int) $c['id']);
    $u = setting('unit') ?: 'ریال';
    $actions[] = ['label' => 'حساب ' . $c['name'], 'url' => url('customer', ['id' => (int) $c['id']])];
    return json_encode([
        'ok' => true, 'customer' => $c['name'], 'amount_text' => money((float) $p['amount']) . ' ' . $u,
        'new_balance' => (int) $fresh['balance'],
        'new_balance_text' => money((float) $fresh['balance']) . ' ' . $u
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
        $out[] = ['number' => $r['number'], 'date' => $r['date'], 'customer' => $r['cust_name'], 'total' => (int) $r['total']];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
