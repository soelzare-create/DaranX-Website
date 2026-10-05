<?php
/**
 * Import proformas from the company's Excel/CSV template files.
 *
 * One file = one proforma. Files are dropped into data/imports/ (not web
 * reachable) and read server-side. Parsing is deterministic (no AI): it scans
 * for the labelled header cells (نام مشتری / تاریخ صدور / …) and the item table
 * (Title / QTY / Price), so it tolerates merged cells and column offsets.
 *
 * .xlsx is read with ZipArchive + DOM (no Composer / PhpSpreadsheet needed).
 */
defined('APP_DIR') || exit;

function import_dir(): string
{
    $d = DATA_DIR . '/imports';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    if (!is_dir($d . '/done')) {
        @mkdir($d . '/done', 0755, true);
    }
    if (!is_file($d . '/.htaccess')) {
        @file_put_contents($d . '/.htaccess', DENY_ALL_HTACCESS);
    }
    return $d;
}

/** Files waiting to be imported (xlsx / csv), newest first. */
function import_files(): array
{
    $d = import_dir();
    $out = [];
    foreach (glob($d . '/*.{xlsx,csv,XLSX,CSV}', GLOB_BRACE) ?: [] as $f) {
        if (is_file($f)) {
            $out[] = basename($f);
        }
    }
    sort($out);
    return $out;
}

/**
 * Save browser-uploaded files into the imports dir. Accepts the $_FILES entry
 * of a `name="files[]" multiple` field. Returns [savedNames[], errors[]].
 * Only .xlsx / .csv are kept; names are basename-sanitised and de-duplicated.
 */
function import_save_uploads(array $files): array
{
    $dir = import_dir();
    $saved = [];
    $errors = [];
    $names = $files['name'] ?? null;
    if (!is_array($names)) {
        // A single (non-array) file field — normalise to the array shape.
        $files = [
            'name' => [$files['name'] ?? ''], 'tmp_name' => [$files['tmp_name'] ?? ''],
            'error' => [$files['error'] ?? UPLOAD_ERR_NO_FILE], 'size' => [$files['size'] ?? 0],
        ];
        $names = $files['name'];
    }
    $n = count($names);
    for ($i = 0; $i < $n; $i++) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue; // empty slot
        }
        $name = basename((string) ($files['name'][$i] ?? ''));
        $label = $name !== '' ? $name : 'فایل ' . ($i + 1);
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = $label . ': آپلود کامل نشد'
                . ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? ' (حجم زیاد)' : '');
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            $errors[] = $label . ': فقط فایل xlsx یا csv پذیرفته می‌شود';
            continue;
        }
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $errors[] = $label . ': فایل نامعتبر';
            continue;
        }
        $dest = $dir . '/' . $name;
        if (is_file($dest) || is_file($dir . '/done/' . $name)) {
            $dest = $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '-' . date('His') . '.' . $ext;
        }
        if (@move_uploaded_file($tmp, $dest)) {
            $saved[] = basename($dest);
        } else {
            $errors[] = $label . ': ذخیره روی سرور ناموفق بود';
        }
    }
    return [$saved, $errors];
}

/** A safe absolute path for a file name inside the imports dir (no traversal). */
function import_path(string $name): string
{
    $name = basename($name);
    $p = import_dir() . '/' . $name;
    if (!is_file($p)) {
        throw new UserError('فایل پیدا نشد: ' . $name);
    }
    return $p;
}

// --- File readers → a 2D grid [row][col] of strings -------------------------

function import_read_grid(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return import_read_csv($path);
    }
    if ($ext === 'xlsx') {
        return import_read_xlsx($path);
    }
    throw new UserError('فقط فایل‌های xlsx یا csv پشتیبانی می‌شوند.');
}

function import_read_csv(string $path): array
{
    $grid = [];
    if (($h = fopen($path, 'r')) !== false) {
        // Strip a UTF-8 BOM if present.
        $bom = fread($h, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($h);
        }
        while (($row = fgetcsv($h, 0, ",", "\"", "\\")) !== false) {
            $grid[] = array_map(function ($v) {
                return (string) $v;
            }, $row);
        }
        fclose($h);
    }
    return $grid;
}

function import_col_index(string $ref): int
{
    if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
        return 0;
    }
    $n = 0;
    foreach (str_split($m[1]) as $c) {
        $n = $n * 26 + (ord($c) - 64);
    }
    return $n - 1;
}

function import_read_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new UserError('فایل xlsx باز نشد؛ شاید خراب است.');
    }
    // Shared strings table.
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false && $ss !== '') {
        $dom = new DOMDocument();
        @$dom->loadXML($ss);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $shared[] = $text;
        }
    }
    // First worksheet.
    $sheetXml = false;
    for ($i = 1; $i <= 20; $i++) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml');
        if ($sheetXml !== false && $sheetXml !== '') {
            break;
        }
    }
    $zip->close();
    if ($sheetXml === false || $sheetXml === '') {
        throw new UserError('برگهٔ اکسل خوانده نشد.');
    }
    $dom = new DOMDocument();
    @$dom->loadXML($sheetXml);
    $grid = [];
    $r = 0;
    foreach ($dom->getElementsByTagName('row') as $row) {
        $cells = [];
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            $col = $ref !== '' ? import_col_index($ref) : count($cells);
            $t = $c->getAttribute('t');
            $val = '';
            if ($t === 'inlineStr') {
                foreach ($c->getElementsByTagName('t') as $tn) {
                    $val .= $tn->textContent;
                }
            } else {
                $v = $c->getElementsByTagName('v')->item(0);
                $raw = $v ? $v->textContent : '';
                if ($t === 's') {
                    $val = $shared[(int) $raw] ?? '';
                } else {
                    $val = $raw;
                }
            }
            $cells[$col] = $val;
        }
        // normalise to a dense, 0-based array
        $max = $cells ? max(array_keys($cells)) : -1;
        $line = [];
        for ($j = 0; $j <= $max; $j++) {
            $line[$j] = $cells[$j] ?? '';
        }
        $grid[$r++] = $line;
    }
    return $grid;
}

// --- Grid → a doc form (for doc_validate) -----------------------------------

function _imp_norm(string $s): string
{
    return trim(fa_norm($s));
}

/** First non-empty cell to the right of ($r,$c) in the same row. */
function _imp_right(array $grid, int $r, int $c): string
{
    $row = $grid[$r] ?? [];
    $n = count($row);
    for ($j = $c + 1; $j < $n; $j++) {
        if (trim((string) ($row[$j] ?? '')) !== '') {
            return trim((string) $row[$j]);
        }
    }
    return '';
}

/** Find the first cell whose normalised text contains $needle; [r,c] or null. */
function _imp_find(array $grid, string $needle): ?array
{
    foreach ($grid as $r => $row) {
        foreach ($row as $c => $v) {
            if ($v !== '' && mb_strpos(_imp_norm((string) $v), $needle) !== false) {
                return [$r, $c];
            }
        }
    }
    return null;
}

/** Parse one proforma file into a form array for doc_validate('qot'). */
function import_parse(array $grid): array
{
    // Header fields by label.
    $cust = '';
    if ($p = _imp_find($grid, 'نام مشتری')) {
        $cust = _imp_right($grid, $p[0], $p[1]);
    }
    if ($cust === '') {
        throw new UserError('نام مشتری در فایل پیدا نشد.');
    }
    $date = '';
    if ($p = _imp_find($grid, 'تاریخ صدور')) {
        $date = _imp_right($grid, $p[0], $p[1]);
    }
    $addr = '';
    if ($p = _imp_find($grid, 'آدرس')) {
        $addr = _imp_right($grid, $p[0], $p[1]);
    }
    $phone = '';
    if ($p = _imp_find($grid, 'شماره تماس')) {
        $phone = _imp_right($grid, $p[0], $p[1]);
    }

    // Item table: locate the header row (has Title / QTY / Price columns).
    $titleCol = $qtyCol = $priceCol = null;
    $headRow = null;
    foreach ($grid as $r => $row) {
        $cols = [];
        foreach ($row as $c => $v) {
            $cols[strtolower(trim((string) $v))] = $c;
        }
        if (isset($cols['title']) && isset($cols['qty']) && isset($cols['price'])) {
            $headRow = $r;
            $titleCol = $cols['title'];
            $qtyCol = $cols['qty'];
            $priceCol = $cols['price']; // "price" is unit price; "total price" is a different header
            break;
        }
    }
    if ($headRow === null) {
        throw new UserError('ردیف سرستون اقلام (Title/QTY/Price) پیدا نشد.');
    }

    $items = [];
    $nrows = count($grid);
    for ($r = $headRow + 1; $r < $nrows; $r++) {
        $row = $grid[$r];
        // Stop at the totals row.
        $joined = _imp_norm(implode(' ', array_map('strval', $row)));
        if (mb_strpos($joined, 'جمع کل') !== false) {
            break;
        }
        $title = trim((string) ($row[$titleCol] ?? ''));
        if ($title === '') {
            // allow one blank gap, but stop after it to avoid reading footer text
            continue;
        }
        $qty = $row[$qtyCol] ?? '';
        $price = $row[$priceCol] ?? '';
        $items[] = ['title' => $title, 'qty' => $qty === '' ? null : $qty, 'price' => $price === '' ? null : $price];
    }
    if (!$items) {
        throw new UserError('هیچ قلم کالایی در جدول پیدا نشد.');
    }

    // Notes (توضیحات) → lines; fall back to the defaults.
    $notes = default_notes('qot');
    if ($p = _imp_find($grid, 'توضیحات')) {
        $cell = (string) ($grid[$p[0]][$p[1]] ?? '');
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $cell) as $ln) {
            $ln = trim($ln, " \t*•-");
            if ($ln !== '' && mb_strpos(_imp_norm($ln), 'توضیحات') !== 0) {
                $lines[] = $ln;
            }
        }
        if ($lines) {
            $notes = $lines;
        }
    }

    $normDate = jdate_norm($date);
    return [
        'cust_name' => $cust,
        'cust_phone' => $phone,
        'cust_address' => $addr,
        'date' => $normDate ?? jtoday(),
        'number_auto' => 1,
        'items' => $items,
        'discount' => 0,
        'vat_on' => false,
        'notes' => $notes,
        '_src_date' => $date,
        '_date_ok' => $normDate !== null,
    ];
}

/** Move an imported file into data/imports/done/. */
function import_archive(string $name): void
{
    $name = basename($name);
    $from = import_dir() . '/' . $name;
    $to = import_dir() . '/done/' . $name;
    if (is_file($to)) {
        $to = import_dir() . '/done/' . pathinfo($name, PATHINFO_FILENAME) . '-' . date('His') . '.' . pathinfo($name, PATHINFO_EXTENSION);
    }
    @rename($from, $to);
}
