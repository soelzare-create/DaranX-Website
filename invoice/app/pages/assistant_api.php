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
$sys = "تو دستیار مالی شرکت «" . setting('company_name') . "» هستی و از طریق چت کار می‌کنی.\n"
    . "کارهایت فقط با ابزارهای داده‌شده:\n"
    . "- فروش: پیش‌فاکتور، فاکتور، تبدیل پیش‌فاکتور به فاکتور، ثبت دریافتی از مشتری، مانده و سوابق مشتری.\n"
    . "- هزینه: ثبت خرید برای یک فاکتور (record_purchase) و ثبت پرداختی (record_expense) از دو نوع: «مستقیم» که به یک فاکتور یا خرید وصل است (پرداخت به فروشنده، پیک، نصب) و «سربار» شرکت (اجاره، حقوق، قبوض، تبلیغات، ...).\n"
    . "- گزارش: financial_report برای سود و زیان، سود هر فاکتور، بدهکاران و سن بدهی، بدهی به فروشنده‌ها، هزینه‌ها به تفکیک، فروش به تفکیک مشتری، روند ماهانه و پرفروش‌ترین اقلام؛ invoice_details برای سود و هزینه‌های یک فاکتور؛ list_records برای فهرست خریدها، پرداختی‌ها و دریافتی‌ها.\n"
    . "قواعد:\n"
    . "- همیشه فارسی، کوتاه و محترمانه پاسخ بده.\n"
    . "- واحد پول «{$unit}» است. مبالغ ورودی را عدد صحیح بده. «میلیون» را در ۱۰۰۰۰۰۰ و «هزار» را در ۱۰۰۰ ضرب کن (مثلاً «۵ میلیون» = 5000000، «۲.۵ میلیون» = 2500000).\n"
    . "- امروز «{$today}» شمسی است؛ اگر تاریخ گفته نشد همین را به‌کار ببر. ماه‌ها: فروردین=01، اردیبهشت=02، خرداد=03، تیر=04، مرداد=05، شهریور=06، مهر=07، آبان=08، آذر=09، دی=10، بهمن=11، اسفند=12.\n"
    . "- اگر چیزی لازم و مبهم است (مشتری، مبلغ، قلم‌ها، فاکتور مربوط به خرید، نوع پرداختی) اول با یک سؤال کوتاه بپرس، بعد ثبت کن. برای گزارش‌ها نپرس؛ اگر بازه گفته نشد «این ماه» را بگیر (برای روند ماهانه «امسال»).\n"
    . "- هر خرید باید به یک فاکتور وصل باشد. اگر کاربر شمارهٔ فاکتور نگفت و فقط مشتری را گفت، با list_docs فاکتورهای آن مشتری را پیدا کن؛ اگر فقط یک فاکتور فعال مناسب بود همان را بگیر، وگرنه بپرس.\n"
    . "- پرداخت بابت یک خرید را با شمارهٔ خرید (PUR-...) ثبت کن تا از بدهی آن خرید کم شود. اگر کاربر هنگام ثبت خرید گفت پولش را هم داده، paid_amount را در همان record_purchase بده.\n"
    . "- پیش‌فاکتور روی حساب مشتری اثر ندارد؛ فقط فاکتور، مشتری را بدهکار می‌کند.\n"
    . "- بعد از هر ثبت، نتیجه را با شمارهٔ سند و مبلغ و اثرش (مانده، سود فاکتور) کوتاه گزارش بده.\n"
    . "- در گزارش‌ها فقط عددهایی را بگو که ابزار برگردانده؛ هیچ عددی را حدس نزن یا خودت حساب نکن. عددها را همان‌طور که آمده‌اند (با ارقام فارسی) بنویس. «−» یعنی منفی (زیان).\n"
    . "- گزارش را با یک جملهٔ خلاصه شروع کن، بعد یک جدول Markdown کوتاه (حداکثر ۱۵ ردیف) و در پایان اگر نکتهٔ مهمی هست (مثلاً فاکتورهایی که هنوز هزینه‌شان ثبت نشده) یک خط بگو. از **پررنگ** فقط برای عدد اصلی استفاده کن.\n"
    . "- مبنای سود: فروش خالص (بدون مالیات، بعد از تخفیف) منهای خریدها و هزینه‌های مستقیم همان فاکتورها؛ سود خالص = سود ناخالص منهای سربار.\n"
    . "- ابطال، ویرایش یا حذف انجام نده؛ اگر خواستند بگو در خود برنامه دستی انجام دهند.";

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
        'model' => $model, 'max_tokens' => 4096, 'system' => $system,
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
        'model' => $model, 'max_tokens' => 4096, 'messages' => $messages,
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
        ['name' => 'list_docs', 'description' => 'فهرست آخرین پیش‌فاکتورها یا فاکتورها (با وضعیت). برای پیدا کردن فاکتورِ یک مشتری، نام او را در query بده.',
            'schema' => ['type' => 'object', 'properties' => [
                'type' => ['type' => 'string', 'enum' => ['qot', 'inv'], 'description' => 'qot=پیش‌فاکتور، inv=فاکتور'],
                'query' => ['type' => 'string', 'description' => 'جستجو در شماره یا نام مشتری (اختیاری)'],
            ], 'required' => ['type']]],
        ['name' => 'record_purchase', 'description' => 'ثبت خرید (کالا یا خدمتی که برای یک فاکتور خریده شده). مبلغش بهای تمام‌شدهٔ همان فاکتور است و تا پرداخت نشود بدهی به فروشنده حساب می‌شود.',
            'schema' => ['type' => 'object', 'properties' => [
                'invoice' => ['type' => 'string', 'description' => 'شمارهٔ فاکتور فروش مربوط، مثل INV-0003'],
                'title' => ['type' => 'string', 'description' => 'شرح خرید (چه چیزی خریده شد)'],
                'amount' => ['type' => 'number', 'description' => 'مبلغ خرید به ' . $unit],
                'supplier' => ['type' => 'string', 'description' => 'فروشنده / تأمین‌کننده (اختیاری)'],
                'date' => ['type' => 'string', 'description' => 'تاریخ شمسی (اختیاری)'],
                'note' => ['type' => 'string', 'description' => 'توضیح (اختیاری)'],
                'paid_amount' => ['type' => 'number', 'description' => 'اگر همین الان پولش (کامل یا بخشی) به فروشنده پرداخت شده، مبلغ آن (اختیاری)'],
                'payment_method' => ['type' => 'string', 'description' => 'روش پرداخت: ' . implode('، ', PAY_METHODS) . ' (اختیاری)'],
            ], 'required' => ['invoice', 'title', 'amount']]],
        ['name' => 'record_expense', 'description' => 'ثبت پرداختی (پول خارج‌شده). kind=direct برای هزینهٔ مستقیم یک فاکتور (با invoice) یا پرداخت بدهی یک خرید (با purchase)؛ kind=overhead برای هزینهٔ سربار شرکت که به هیچ فاکتوری وصل نیست.',
            'schema' => ['type' => 'object', 'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['direct', 'overhead'], 'description' => 'direct=مستقیم فاکتور، overhead=سربار شرکت'],
                'amount' => ['type' => 'number', 'description' => 'مبلغ پرداختی به ' . $unit],
                'category' => ['type' => 'string', 'description' => 'بابت. مستقیم: ' . implode('، ', DIRECT_CATEGORIES)
                    . '. سربار: ' . implode('، ', OVERHEAD_CATEGORIES) . '. (برای پرداخت خرید اختیاری)'],
                'payee' => ['type' => 'string', 'description' => 'گیرنده (اختیاری)'],
                'invoice' => ['type' => 'string', 'description' => 'برای هزینهٔ مستقیم: شمارهٔ فاکتور، مثل INV-0003'],
                'purchase' => ['type' => 'string', 'description' => 'برای پرداخت بابت یک خرید: شمارهٔ خرید، مثل PUR-0002 (فاکتورش خودکار پیدا می‌شود)'],
                'date' => ['type' => 'string', 'description' => 'تاریخ شمسی (اختیاری)'],
                'method' => ['type' => 'string', 'description' => 'روش: ' . implode('، ', PAY_METHODS) . ' (اختیاری)'],
                'note' => ['type' => 'string', 'description' => 'توضیح (اختیاری)'],
            ], 'required' => ['kind', 'amount']]],
        ['name' => 'invoice_details', 'description' => 'جزئیات مالی یک فاکتور: مبلغ، دریافت‌شده/مانده، خریدها و هزینه‌های مستقیم، سود و حاشیهٔ سود.',
            'schema' => ['type' => 'object', 'properties' => [
                'invoice' => ['type' => 'string', 'description' => 'شمارهٔ فاکتور، مثل INV-0003'],
            ], 'required' => ['invoice']]],
        ['name' => 'financial_report', 'description' => 'گزارش مالی آماده. summary=سود و زیان و جریان نقدی دوره؛ invoice_profit=سود تک‌تک فاکتورها؛ receivables=بدهکاران و سن بدهی (تا امروز)؛ payables=بدهی به فروشنده‌ها بابت خریدها (تا امروز)؛ expenses=پرداختی‌ها به تفکیک نوع و بابت؛ sales_by_customer=فروش هر مشتری؛ monthly=روند ماه‌به‌ماه؛ top_items=پرفروش‌ترین اقلام.',
            'schema' => ['type' => 'object', 'properties' => [
                'report' => ['type' => 'string', 'enum' => ['summary', 'invoice_profit', 'receivables', 'payables', 'expenses',
                    'sales_by_customer', 'monthly', 'top_items']],
                'period' => ['type' => 'string', 'enum' => REPORT_PERIODS,
                    'description' => 'بازه (receivables و payables بازه نمی‌خواهند). custom با from/to.'],
                'from' => ['type' => 'string', 'description' => 'برای custom: شروع، مثل 1405/01/01 یا 1405/04 (یک ماه) یا 1404 (یک سال)'],
                'to' => ['type' => 'string', 'description' => 'برای custom: پایان (اختیاری، پیش‌فرض امروز)'],
                'sort' => ['type' => 'string', 'enum' => ['date', 'profit_desc', 'profit_asc'], 'description' => 'فقط invoice_profit (اختیاری)'],
                'limit' => ['type' => 'integer', 'description' => 'حداکثر ردیف (اختیاری، پیش‌فرض ۳۰)'],
            ], 'required' => ['report']]],
        ['name' => 'list_records', 'description' => 'فهرست سوابق: purchases=خریدها، unpaid_purchases=خریدهای پرداخت‌نشده، expenses=پرداختی‌ها، payments=دریافتی‌ها از مشتری.',
            'schema' => ['type' => 'object', 'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['purchases', 'unpaid_purchases', 'expenses', 'payments']],
                'query' => ['type' => 'string', 'description' => 'جستجو در شماره، نام، فروشنده یا شرح (اختیاری)'],
                'period' => ['type' => 'string', 'enum' => REPORT_PERIODS, 'description' => 'بازه (اختیاری، پیش‌فرض همه)'],
                'from' => ['type' => 'string', 'description' => 'برای custom'],
                'to' => ['type' => 'string', 'description' => 'برای custom'],
                'expense_kind' => ['type' => 'string', 'enum' => ['direct', 'overhead'], 'description' => 'فقط برای expenses (اختیاری)'],
            ], 'required' => ['kind']]],
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
            case 'record_purchase':
                return ['text' => _a_record_purchase($in, $actions)];
            case 'record_expense':
                return ['text' => _a_record_expense($in, $actions)];
            case 'invoice_details':
                return ['text' => _a_invoice_details((string) ($in['invoice'] ?? ''), $actions)];
            case 'financial_report':
                return ['text' => _a_report($in)];
            case 'list_records':
                return ['text' => _a_list_records($in)];
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
        $row = ['number' => $r['number'], 'date' => $r['date'], 'customer' => $r['cust_name'], 'total' => (int) $r['total']];
        if ($type === 'inv') {
            $row['status'] = $r['status'] === 'cancelled' ? 'باطل‌شده' : 'فعال';
        } else {
            $row['converted_to'] = $r['inv_number'] ?: null;
        }
        $out[] = $row;
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

// --- Purchases, payments out and reports -----------------------------------

/** Signed money for tool output: «−۱۲٬۰۰۰» for a loss. */
function _a_money(float $n): string
{
    return ($n <= -0.5 ? '−' : '') . money($n);
}

/** Format a report for the model: floats are money, ints are counts. */
function _a_fmt($v)
{
    if (is_array($v)) {
        return array_map('_a_fmt', $v);
    }
    if (is_float($v)) {
        return _a_money($v);
    }
    if (is_int($v)) {
        return fa($v);
    }
    return $v;
}

function _a_json(array $data): string
{
    return json_encode(['unit' => setting('unit') ?: 'ریال'] + _a_fmt($data), JSON_UNESCAPED_UNICODE);
}

/** Find a document by number: «INV-0003», «inv-3», «۳» or «0003» all work. */
function _a_find_doc(string $ref, string $type = 'inv'): array
{
    $ref = strtoupper(str_replace(' ', '', en_digits(trim($ref))));
    if ($ref === '') {
        throw new UserError('شمارهٔ ' . DOC_NAMES[$type] . ' را بگویید.');
    }
    $tries = [$ref];
    if (preg_match('/(\d+)$/', $ref, $m)) {
        $tries[] = DOC_PREFIX[$type] . str_pad(ltrim($m[1], '0') ?: '0', 4, '0', STR_PAD_LEFT);
    }
    $st = db()->prepare('SELECT * FROM docs WHERE type = ? AND UPPER(number) = ? LIMIT 1');
    foreach ($tries as $n) {
        $st->execute([$type, $n]);
        if ($d = $st->fetch()) {
            return $d;
        }
    }
    throw new UserError(DOC_NAMES[$type] . ' «' . $ref . '» پیدا نشد.');
}

function _a_find_purchase(string $ref): array
{
    $ref = strtoupper(str_replace(' ', '', en_digits(trim($ref))));
    $tries = [$ref];
    if (preg_match('/(\d+)$/', $ref, $m)) {
        $tries[] = PURCHASE_PREFIX . str_pad(ltrim($m[1], '0') ?: '0', 4, '0', STR_PAD_LEFT);
    }
    $st = db()->prepare('SELECT id FROM purchases WHERE UPPER(number) = ? LIMIT 1');
    foreach ($tries as $n) {
        $st->execute([$n]);
        if ($id = (int) $st->fetchColumn()) {
            return purchase_get($id);
        }
    }
    throw new UserError('خرید «' . $ref . '» پیدا نشد.');
}

function _a_method(array $in, string $key): string
{
    $m = clean_line($in[$key] ?? '', 40);
    foreach (PAY_METHODS as $pm) {
        if ($m !== '' && (mb_strpos($pm, $m) !== false || mb_strpos($m, $pm) !== false)) {
            return $pm;
        }
    }
    return $m === '' ? PAY_METHODS[0] : 'سایر';
}

/** Short profit line of one invoice, after a cost was added. */
function _a_profit_of(int $docId): array
{
    $doc = doc_get($docId);
    $c = invoice_costs($doc);
    return ['invoice' => $doc['number'], 'net_sales' => (float) $c['net_sales'], 'cost' => (float) $c['cost'],
        'profit' => (float) $c['profit'], 'margin' => _r_margin((float) $c['profit'], (float) $c['net_sales'])];
}

function _a_record_purchase(array $in, array &$actions): string
{
    $doc = _a_find_doc((string) ($in['invoice'] ?? ''));
    $p = purchase_validate([
        'doc_id' => $doc['id'], 'supplier' => (string) ($in['supplier'] ?? ''), 'title' => (string) ($in['title'] ?? ''),
        'amount' => $in['amount'] ?? '', 'note' => (string) ($in['note'] ?? ''),
        'date' => trim((string) ($in['date'] ?? '')) !== '' ? (string) $in['date'] : jtoday(),
    ]);
    $paid = 0;
    if (isset($in['paid_amount']) && $in['paid_amount'] !== '' && (float) parse_num($in['paid_amount']) > 0) {
        $paid = amount_in($in['paid_amount'], 'مبلغ پرداخت‌شده');
        if ($paid > $p['amount']) {
            throw new UserError('مبلغ پرداخت‌شده از مبلغ خرید بیشتر است.');
        }
    }
    $pid = purchase_save($p);
    if ($paid > 0) {
        expense_save(expense_validate([
            'kind' => 'direct', 'purchase_id' => $pid, 'amount' => $paid, 'date' => $p['date'],
            'method' => _a_method($in, 'payment_method'),
        ]));
    }
    $pur = purchase_get($pid);
    $actions[] = ['label' => 'خرید ' . $pur['number'], 'url' => url('purchase', ['id' => $pid])];
    return _a_json([
        'ok' => true, 'purchase' => $pur['number'], 'invoice' => $pur['doc_number'], 'customer' => $pur['cust_name'],
        'supplier' => $pur['supplier'], 'amount' => (float) $pur['amount'], 'paid' => (float) $pur['paid'],
        'still_owed_to_supplier' => purchase_left($pur), 'invoice_after' => _a_profit_of((int) $pur['doc_id']),
    ]);
}

function _a_record_expense(array $in, array &$actions): string
{
    $form = [
        'kind' => ($in['kind'] ?? '') === 'overhead' ? 'overhead' : 'direct',
        'category' => (string) ($in['category'] ?? ''), 'payee' => (string) ($in['payee'] ?? ''),
        'amount' => $in['amount'] ?? '', 'note' => (string) ($in['note'] ?? ''),
        'date' => trim((string) ($in['date'] ?? '')) !== '' ? (string) $in['date'] : jtoday(),
        'method' => _a_method($in, 'method'),
    ];
    $pur = null;
    if (trim((string) ($in['purchase'] ?? '')) !== '') {
        $pur = _a_find_purchase((string) $in['purchase']);
        $form['kind'] = 'direct';
        $form['purchase_id'] = $pur['id'];
        if ((float) parse_num($form['amount']) > purchase_left($pur) + 0.5) {
            throw new UserError('مانده خرید ' . $pur['number'] . ' فقط ' . money(purchase_left($pur)) . ' است.');
        }
    } elseif ($form['kind'] === 'direct') {
        if (trim((string) ($in['invoice'] ?? '')) === '') {
            throw new UserError('هزینهٔ مستقیم باید به یک فاکتور وصل باشد؛ شمارهٔ فاکتور را بپرس، یا اگر هزینهٔ عمومی شرکت است kind=overhead بده.');
        }
        $form['doc_id'] = _a_find_doc((string) $in['invoice'])['id'];
    }
    $e = expense_validate($form);
    $id = expense_save($e);
    $out = ['ok' => true, 'kind' => EXPENSE_KINDS[$e['kind']], 'category' => $e['category'], 'payee' => $e['payee'],
        'amount' => (float) $e['amount'], 'date' => fa($e['date']), 'method' => $e['method']];
    if ($pur) {
        $fresh = purchase_get((int) $pur['id']);
        $out['purchase'] = $fresh['number'];
        $out['purchase_still_owed'] = purchase_left($fresh);
    }
    if ($e['doc_id']) {
        $out['invoice_after'] = _a_profit_of((int) $e['doc_id']);
    }
    $actions[] = ['label' => 'پرداخت: ' . $e['category'], 'url' => url('expense', ['id' => $id])];
    return _a_json($out);
}

function _a_invoice_details(string $ref, array &$actions): string
{
    $doc = _a_find_doc($ref);
    $c = invoice_costs($doc);
    $left = invoice_remaining((int) $doc['customer_id'])[(int) $doc['id']] ?? 0.0;
    $purchases = array_map(function ($p) {
        return ['purchase' => $p['number'], 'title' => $p['title'], 'supplier' => $p['supplier'],
            'amount' => (float) $p['amount'], 'still_owed' => purchase_left($p)];
    }, purchases_list('', (int) $doc['id'], 50));
    $direct = [];
    foreach (expenses_list('direct', '', (int) $doc['id'], 50) as $e) {
        if (!$e['purchase_id']) {
            $direct[] = ['category' => $e['category'], 'payee' => $e['payee'], 'amount' => (float) $e['amount']];
        }
    }
    $actions[] = ['label' => 'فاکتور ' . $doc['number'], 'url' => url('doc', ['id' => $doc['id']])];
    return _a_json([
        'invoice' => $doc['number'], 'customer' => $doc['cust_name'], 'date' => fa($doc['date']),
        'status' => $doc['status'] === 'cancelled' ? 'باطل‌شده' : 'فعال',
        'total_with_vat' => (float) $doc['total'], 'unpaid_by_customer' => (float) ($doc['status'] === 'active' ? $left : 0),
        'net_sales' => (float) $c['net_sales'], 'purchases' => $purchases, 'other_direct_costs' => $direct,
        'cost' => (float) $c['cost'], 'profit' => (float) $c['profit'],
        'margin' => _r_margin((float) $c['profit'], (float) $c['net_sales']),
        'owed_to_suppliers' => (float) $c['purchases_left'],
    ]);
}

function _a_report(array $in): string
{
    $type = (string) ($in['report'] ?? 'summary');
    $limit = max(1, min(100, (int) ($in['limit'] ?? 30)));
    $period = in_array($in['period'] ?? '', REPORT_PERIODS, true) ? $in['period'] : ($type === 'monthly' ? 'this_year' : 'this_month');
    $P = report_period($period, (string) ($in['from'] ?? ''), (string) ($in['to'] ?? ''));
    switch ($type) {
        case 'invoice_profit':
            return _a_json(report_invoice_profits($P, $limit, (string) ($in['sort'] ?? 'date')));
        case 'receivables':
            return _a_json(report_receivables($limit));
        case 'payables':
            return _a_json(report_payables($limit));
        case 'expenses':
            return _a_json(report_expenses($P));
        case 'sales_by_customer':
            return _a_json(report_sales_by_customer($P, $limit));
        case 'monthly':
            return _a_json(report_monthly($P));
        case 'top_items':
            return _a_json(report_top_items($P, min($limit, 30)));
    }
    return _a_json(report_summary($P));
}

function _a_list_records(array $in): string
{
    $kind = (string) ($in['kind'] ?? 'purchases');
    $q = clean_line($in['query'] ?? '', 100);
    $P = in_array($in['period'] ?? '', REPORT_PERIODS, true)
        ? report_period($in['period'], (string) ($in['from'] ?? ''), (string) ($in['to'] ?? ''))
        : report_period('all');
    $inP = function ($r) use ($P) {
        return $r['date'] >= $P['from'] && $r['date'] <= $P['to'];
    };
    $rows = [];
    if ($kind === 'expenses') {
        $ek = in_array($in['expense_kind'] ?? '', ['direct', 'overhead'], true) ? $in['expense_kind'] : '';
        foreach (array_filter(expenses_list($ek, $q, 0, 2000), $inP) as $e) {
            $rows[] = ['date' => fa($e['date']), 'kind' => EXPENSE_KIND_SHORT[$e['kind']], 'category' => $e['category'],
                'payee' => $e['payee'], 'invoice' => $e['doc_number'], 'purchase' => $e['purchase_number'],
                'method' => $e['method'], 'amount' => (float) $e['amount']];
        }
    } elseif ($kind === 'payments') {
        foreach (array_filter(payments_list($q, 2000), $inP) as $p) {
            $rows[] = ['date' => fa($p['date']), 'customer' => $p['customer_name'], 'method' => $p['method'],
                'note' => $p['note'], 'amount' => (float) $p['amount']];
        }
    } else {
        foreach (array_filter(purchases_list($q, 0, 2000), $inP) as $p) {
            if ($kind === 'unpaid_purchases' && purchase_left($p) < 0.5) {
                continue;
            }
            $rows[] = ['purchase' => $p['number'], 'date' => fa($p['date']), 'invoice' => $p['doc_number'],
                'customer' => $p['cust_name'], 'supplier' => $p['supplier'], 'title' => $p['title'],
                'amount' => (float) $p['amount'], 'still_owed' => purchase_left($p)];
        }
    }
    $out = ['kind' => $kind, 'period' => $P['label'], 'count' => count($rows),
        'total' => (float) array_sum(array_column($rows, 'amount'))];
    if ($kind === 'purchases' || $kind === 'unpaid_purchases') {
        $out['total_still_owed'] = (float) array_sum(array_column($rows, 'still_owed'));
    }
    return _a_json($out + ['rows' => array_slice($rows, 0, 25), 'shown' => min(25, count($rows))]);
}
