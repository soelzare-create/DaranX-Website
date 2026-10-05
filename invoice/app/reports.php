<?php
/**
 * Financial reports, read-only. Used by the chat assistant (and reusable by
 * pages later). Money values are returned as floats and counts as ints, so a
 * caller can format by type.
 *
 * Profit basis (same as an invoice's own cost/profit bar): an invoice's cost
 * is its purchases plus its other direct costs, counted in the period of the
 * invoice's date. Sales are net of discount and exclude VAT. Overhead counts
 * by its own payment date.
 */
defined('APP_DIR') || exit;

const JMONTHS = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

const REPORT_PERIODS = ['today', 'this_week', 'last_30_days', 'this_month', 'last_month',
    'this_year', 'last_year', 'all', 'custom'];

/** «1405/07» → «مهر ۱۴۰۵». */
function jmonth_label(string $ym): string
{
    [$y, $m] = array_map('intval', explode('/', $ym) + [0, 0]);
    return (JMONTHS[$m] ?? fa($m)) . ' ' . fa($y);
}

/** A custom bound: «1405», «1405/07» or «1405/07/12» (any digits) → a comparable date string. */
function report_bound(string $s, bool $end): ?string
{
    $s = str_replace(['-', '.'], '/', en_digits(trim($s)));
    if (!preg_match('~^(\d{4})(?:/(\d{1,2}))?(?:/(\d{1,2}))?$~', $s, $m)) {
        return null;
    }
    $y = (int) $m[1];
    $mo = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : ($end ? 12 : 1);
    $d = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : ($end ? 31 : 1);
    if ($y < 1300 || $y > 1500 || $mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
        return null;
    }
    return sprintf('%04d/%02d/%02d', $y, $mo, $d);
}

/**
 * Resolve a named period to an inclusive [from, to] range of stored dates
 * («1405/07/01»), plus a Persian label. Dates compare as strings.
 */
function report_period(string $period = 'this_month', string $from = '', string $to = ''): array
{
    $today = jtoday();
    [$y, $m] = array_map('intval', explode('/', $today));
    switch ($period) {
        case 'today':
            return ['from' => $today, 'to' => $today, 'label' => 'امروز ' . fa($today)];
        case 'this_week': // Persian week starts on Saturday
            $back = ((int) date('w') + 1) % 7;
            return ['from' => jtoday(time() - $back * 86400), 'to' => $today, 'label' => 'این هفته'];
        case 'last_30_days':
            return ['from' => jtoday(time() - 29 * 86400), 'to' => $today, 'label' => '۳۰ روز گذشته'];
        case 'last_month':
            [$y, $m] = $m === 1 ? [$y - 1, 12] : [$y, $m - 1];
            $ym = sprintf('%04d/%02d', $y, $m);
            return ['from' => $ym . '/01', 'to' => $ym . '/31', 'label' => jmonth_label($ym)];
        case 'this_year':
            return ['from' => sprintf('%04d/01/01', $y), 'to' => sprintf('%04d/12/31', $y), 'label' => 'سال ' . fa($y)];
        case 'last_year':
            return ['from' => sprintf('%04d/01/01', $y - 1), 'to' => sprintf('%04d/12/31', $y - 1),
                'label' => 'سال ' . fa($y - 1)];
        case 'all':
            return ['from' => '0000/00/00', 'to' => '9999/99/99', 'label' => 'کل دوره'];
        case 'custom':
            $f = report_bound($from, false);
            // «1404» or «1405/06» alone means that whole year/month; a full date means «since then».
            $wholeUnit = preg_match('~^\d{4}(/\d{1,2})?$~', str_replace(['-', '.'], '/', en_digits(trim($from))));
            $t = $to !== '' ? report_bound($to, true) : ($wholeUnit ? report_bound($from, true) : $today);
            if ($f === null || $t === null) {
                throw new UserError('بازهٔ تاریخ معتبر نیست؛ مثلاً از ۱۴۰۵/۰۱/۰۱ تا ۱۴۰۵/۰۳/۳۱.');
            }
            if ($f > $t) {
                [$f, $t] = [$t, $f];
            }
            if (substr($f, 0, 7) === substr($t, 0, 7) && substr($f, 8) === '01' && substr($t, 8) >= '29') {
                $label = jmonth_label(substr($f, 0, 7));
            } else {
                $label = 'از ' . fa($f) . ' تا ' . fa(min($t, $today));
            }
            return ['from' => $f, 'to' => $t, 'label' => $label];
        default: // this_month
            $ym = sprintf('%04d/%02d', $y, $m);
            return ['from' => $ym . '/01', 'to' => $ym . '/31', 'label' => jmonth_label($ym)];
    }
}

function _r_one(string $sql, array $args): array
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetch(PDO::FETCH_NUM) ?: [];
}

function _r_margin(float $profit, float $net): ?string
{
    if ($net <= 0) {
        return null;
    }
    $m = round($profit / $net * 100, 1);
    return ($m < 0 ? '−' : '') . fa(str_replace('.', '٫', (string) abs($m))) . '٪';
}

/** Profit and loss + cash in/out for a period. */
function report_summary(array $P): array
{
    $r = [$P['from'], $P['to']];
    $inv = "SELECT id FROM docs WHERE type = 'inv' AND status = 'active' AND date BETWEEN ? AND ?";
    [$invCount, $net, $vat, $gross] = _r_one("SELECT COUNT(*), COALESCE(SUM(subtotal - discount), 0),
        COALESCE(SUM(vat_amount), 0), COALESCE(SUM(total), 0)
        FROM docs WHERE type = 'inv' AND status = 'active' AND date BETWEEN ? AND ?", $r);
    [$purCost] = _r_one("SELECT COALESCE(SUM(amount), 0) FROM purchases WHERE doc_id IN ($inv)", $r);
    [$otherCost] = _r_one("SELECT COALESCE(SUM(amount), 0) FROM expenses
        WHERE kind = 'direct' AND purchase_id IS NULL AND doc_id IN ($inv)", $r);
    [$noCost] = _r_one("SELECT COUNT(*) FROM docs d WHERE d.type = 'inv' AND d.status = 'active'
        AND d.date BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM purchases p WHERE p.doc_id = d.id)
        AND NOT EXISTS (SELECT 1 FROM expenses e WHERE e.doc_id = d.id AND e.kind = 'direct')", $r);
    [$ohCount, $overhead] = _r_one("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM expenses
        WHERE kind = 'overhead' AND date BETWEEN ? AND ?", $r);
    [$rcCount, $receipts] = _r_one('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM payments WHERE date BETWEEN ? AND ?', $r);
    [$outCount, $paidOut] = _r_one('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ?', $r);
    [$purCount, $purchases] = _r_one('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM purchases WHERE date BETWEEN ? AND ?', $r);
    [$qotCount, $qotSum] = _r_one("SELECT COUNT(*), COALESCE(SUM(total), 0) FROM docs
        WHERE type = 'qot' AND date BETWEEN ? AND ?", $r);

    $cost = (float) $purCost + (float) $otherCost;
    $grossProfit = (float) $net - $cost;
    $netProfit = $grossProfit - (float) $overhead;
    return [
        'period' => $P['label'],
        'sales' => [
            'invoices' => (int) $invCount, 'net_sales' => (float) $net, 'vat' => (float) $vat,
            'total_with_vat' => (float) $gross,
        ],
        'cost_of_sales' => ['purchases' => (float) $purCost, 'other_direct' => (float) $otherCost, 'total' => $cost],
        'gross_profit' => $grossProfit, 'gross_margin' => _r_margin($grossProfit, (float) $net),
        'overhead' => (float) $overhead, 'overhead_count' => (int) $ohCount,
        'net_profit' => $netProfit, 'net_margin' => _r_margin($netProfit, (float) $net),
        'invoices_without_cost' => (int) $noCost,
        'cash' => [
            'received' => (float) $receipts, 'receipts' => (int) $rcCount,
            'paid_out' => (float) $paidOut, 'payments_out' => (int) $outCount,
            'net_cash' => (float) $receipts - (float) $paidOut,
        ],
        'purchases_registered' => ['count' => (int) $purCount, 'amount' => (float) $purchases],
        'proformas_issued' => ['count' => (int) $qotCount, 'amount' => (float) $qotSum],
        'receivable_now' => report_receivable_total(),
    ];
}

function report_receivable_total(): float
{
    $sum = 0.0;
    foreach (customers_list() as $c) {
        $sum += max(0.0, (float) $c['balance']);
    }
    return $sum;
}

/** Each invoice's sale, cost and profit. $sort: date | profit_desc | profit_asc. */
function report_invoice_profits(array $P, int $limit = 30, string $sort = 'date'): array
{
    $st = db()->prepare("SELECT d.id, d.number, d.date, d.cust_name, d.subtotal - d.discount AS net,
            COALESCE((SELECT SUM(p.amount) FROM purchases p WHERE p.doc_id = d.id), 0) AS pur,
            COALESCE((SELECT SUM(e.amount) FROM expenses e
                      WHERE e.doc_id = d.id AND e.kind = 'direct' AND e.purchase_id IS NULL), 0) AS oth
        FROM docs d WHERE d.type = 'inv' AND d.status = 'active' AND d.date BETWEEN ? AND ?
        ORDER BY d.date DESC, d.id DESC");
    $st->execute([$P['from'], $P['to']]);
    $rows = [];
    $tot = ['net_sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
    foreach ($st as $d) {
        $cost = (float) $d['pur'] + (float) $d['oth'];
        $profit = (float) $d['net'] - $cost;
        $rows[] = [
            'number' => $d['number'], 'date' => fa($d['date']), 'customer' => $d['cust_name'],
            'net_sales' => (float) $d['net'], 'cost' => $cost, 'profit' => $profit,
            'margin' => $cost < 0.5 ? null : _r_margin($profit, (float) $d['net']), 'no_cost_yet' => $cost < 0.5,
        ];
        $tot['net_sales'] += (float) $d['net'];
        $tot['cost'] += $cost;
        $tot['profit'] += $profit;
    }
    if ($sort === 'profit_desc' || $sort === 'profit_asc') {
        usort($rows, function ($a, $b) use ($sort) {
            return $sort === 'profit_desc' ? $b['profit'] <=> $a['profit'] : $a['profit'] <=> $b['profit'];
        });
    }
    return [
        'period' => $P['label'], 'invoices' => count($rows),
        'totals' => $tot + ['margin' => _r_margin($tot['profit'], $tot['net_sales'])],
        'rows' => array_slice($rows, 0, max(1, $limit)), 'shown' => min(count($rows), max(1, $limit)),
    ];
}

/** Who owes us, with the age of their unpaid invoices (as of today). */
function report_receivables(int $limit = 30): array
{
    $todayN = jdays(jtoday());
    $remaining = invoice_remaining();
    $docs = [];
    foreach (db()->query("SELECT id, customer_id, number, date FROM docs WHERE type = 'inv' AND status = 'active'") as $d) {
        $docs[(int) $d['customer_id']][] = $d;
    }
    $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'opening' => 0.0];
    $rows = [];
    foreach (customers_list('', true) as $c) {
        $cb = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $inInvoices = 0.0;
        $oldest = null;
        $unpaid = 0;
        foreach ($docs[(int) $c['id']] ?? [] as $d) {
            $left = $remaining[(int) $d['id']] ?? 0.0;
            if ($left < 0.5) {
                continue;
            }
            $age = max(0, $todayN - jdays($d['date']));
            $k = $age <= 30 ? '0_30' : ($age <= 60 ? '31_60' : ($age <= 90 ? '61_90' : 'over_90'));
            $cb[$k] += $left;
            $inInvoices += $left;
            $unpaid++;
            $oldest = $oldest === null || $d['date'] < $oldest ? $d['date'] : $oldest;
        }
        $opening = max(0.0, (float) $c['balance'] - $inInvoices);
        foreach ($cb as $k => $v) {
            $buckets[$k] += $v;
        }
        $buckets['opening'] += $opening;
        $rows[] = [
            'customer' => $c['name'], 'phone' => $c['phone'], 'balance' => (float) $c['balance'],
            'unpaid_invoices' => $unpaid, 'oldest_unpaid' => $oldest ? fa($oldest) : null,
            'days_oldest' => $oldest ? max(0, $todayN - jdays($oldest)) : null,
            'by_age' => $cb, 'from_opening_balance' => $opening,
        ];
    }
    $total = array_sum(array_column($rows, 'balance'));
    return [
        'as_of' => fa(jtoday()), 'debtors' => count($rows), 'total' => (float) $total,
        'by_age_days' => $buckets,
        'rows' => array_slice($rows, 0, max(1, $limit)), 'shown' => min(count($rows), max(1, $limit)),
    ];
}

/** What we still owe suppliers for purchases (as of today), by supplier. */
function report_payables(int $limit = 30): array
{
    $by = [];
    $total = 0.0;
    foreach (purchases_list('', 0, 100000) as $p) {
        $left = purchase_left($p);
        if ($left < 0.5) {
            continue;
        }
        $s = $p['supplier'] !== '' ? $p['supplier'] : 'بدون نام فروشنده';
        $by[$s] = $by[$s] ?? ['supplier' => $s, 'owed' => 0.0, 'purchases' => 0, 'oldest' => null, 'items' => []];
        $by[$s]['owed'] += $left;
        $by[$s]['purchases']++;
        $by[$s]['oldest'] = $by[$s]['oldest'] === null || $p['date'] < $by[$s]['oldest'] ? $p['date'] : $by[$s]['oldest'];
        $by[$s]['items'][] = ['purchase' => $p['number'], 'invoice' => $p['doc_number'], 'title' => $p['title'],
            'date' => fa($p['date']), 'amount' => (float) $p['amount'], 'left' => $left];
        $total += $left;
    }
    $rows = array_values($by);
    usort($rows, function ($a, $b) {
        return $b['owed'] <=> $a['owed'];
    });
    foreach ($rows as &$r) {
        $r['oldest'] = fa($r['oldest']);
        $r['items'] = array_slice($r['items'], 0, 8);
    }
    unset($r);
    return [
        'as_of' => fa(jtoday()), 'suppliers' => count($rows), 'total_owed' => $total,
        'rows' => array_slice($rows, 0, max(1, $limit)),
    ];
}

/** Money paid out in a period, by kind and category. */
function report_expenses(array $P): array
{
    $st = db()->prepare('SELECT kind, category, COUNT(*) AS n, SUM(amount) AS s FROM expenses
        WHERE date BETWEEN ? AND ? GROUP BY kind, category ORDER BY kind, s DESC');
    $st->execute([$P['from'], $P['to']]);
    $out = ['direct' => ['total' => 0.0, 'count' => 0, 'categories' => []],
        'overhead' => ['total' => 0.0, 'count' => 0, 'categories' => []]];
    foreach ($st as $r) {
        $out[$r['kind']]['total'] += (float) $r['s'];
        $out[$r['kind']]['count'] += (int) $r['n'];
        $out[$r['kind']]['categories'][] = ['category' => $r['category'], 'count' => (int) $r['n'], 'amount' => (float) $r['s']];
    }
    $st = db()->prepare("SELECT payee, COUNT(*) AS n, SUM(amount) AS s FROM expenses
        WHERE date BETWEEN ? AND ? AND payee <> '' GROUP BY payee ORDER BY s DESC LIMIT 8");
    $st->execute([$P['from'], $P['to']]);
    $payees = [];
    foreach ($st as $r) {
        $payees[] = ['payee' => $r['payee'], 'count' => (int) $r['n'], 'amount' => (float) $r['s']];
    }
    return [
        'period' => $P['label'], 'total' => $out['direct']['total'] + $out['overhead']['total'],
        'direct' => $out['direct'], 'overhead' => $out['overhead'], 'top_payees' => $payees,
    ];
}

/** Sales per customer in a period, with what they paid and owe now. */
function report_sales_by_customer(array $P, int $limit = 20): array
{
    $st = db()->prepare("SELECT c.id, c.name, COUNT(d.id) AS n, SUM(d.total) AS s, SUM(d.subtotal - d.discount) AS net,
            COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = c.id AND p.date BETWEEN ? AND ?), 0) AS paid,
            " . BALANCE_SQL . " AS balance
        FROM docs d JOIN customers c ON c.id = d.customer_id
        WHERE d.type = 'inv' AND d.status = 'active' AND d.date BETWEEN ? AND ?
        GROUP BY c.id ORDER BY s DESC");
    $st->execute([$P['from'], $P['to'], $P['from'], $P['to']]);
    $rows = [];
    $total = 0.0;
    foreach ($st as $r) {
        $rows[] = ['customer' => $r['name'], 'invoices' => (int) $r['n'], 'sales' => (float) $r['s'],
            'paid_in_period' => (float) $r['paid'], 'balance_now' => (float) $r['balance']];
        $total += (float) $r['s'];
    }
    foreach ($rows as &$r) {
        $r['share'] = $total > 0 ? fa(round($r['sales'] / $total * 100)) . '٪' : null;
    }
    unset($r);
    return [
        'period' => $P['label'], 'customers' => count($rows), 'total_sales' => $total,
        'rows' => array_slice($rows, 0, max(1, $limit)), 'shown' => min(count($rows), max(1, $limit)),
    ];
}

/** Month by month: sales, cost, overhead, profit, cash in/out. */
function report_monthly(array $P): array
{
    $r = [$P['from'], $P['to']];
    $m = [];
    $add = function (string $sql, string $key) use (&$m, $r) {
        $st = db()->prepare($sql);
        $st->execute($r);
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$ym, $v]) {
            $m[$ym][$key] = (float) $v;
        }
    };
    $add("SELECT substr(date, 1, 7), SUM(subtotal - discount) FROM docs
          WHERE type = 'inv' AND status = 'active' AND date BETWEEN ? AND ? GROUP BY 1", 'net_sales');
    $add("SELECT substr(d.date, 1, 7), SUM(p.amount) FROM purchases p JOIN docs d ON d.id = p.doc_id
          WHERE d.status = 'active' AND d.date BETWEEN ? AND ? GROUP BY 1", 'pur');
    $add("SELECT substr(d.date, 1, 7), SUM(e.amount) FROM expenses e JOIN docs d ON d.id = e.doc_id
          WHERE e.kind = 'direct' AND e.purchase_id IS NULL AND d.status = 'active' AND d.date BETWEEN ? AND ?
          GROUP BY 1", 'oth');
    $add("SELECT substr(date, 1, 7), SUM(amount) FROM expenses WHERE kind = 'overhead' AND date BETWEEN ? AND ? GROUP BY 1", 'overhead');
    $add('SELECT substr(date, 1, 7), SUM(amount) FROM payments WHERE date BETWEEN ? AND ? GROUP BY 1', 'received');
    $add('SELECT substr(date, 1, 7), SUM(amount) FROM expenses WHERE date BETWEEN ? AND ? GROUP BY 1', 'paid_out');
    ksort($m);
    $m = array_slice($m, -24, null, true);
    $rows = [];
    $tot = ['net_sales' => 0.0, 'cost' => 0.0, 'overhead' => 0.0, 'net_profit' => 0.0, 'received' => 0.0, 'paid_out' => 0.0];
    foreach ($m as $ym => $v) {
        $cost = ($v['pur'] ?? 0.0) + ($v['oth'] ?? 0.0);
        $row = [
            'month' => jmonth_label($ym), 'net_sales' => $v['net_sales'] ?? 0.0, 'cost' => $cost,
            'overhead' => $v['overhead'] ?? 0.0,
            'net_profit' => ($v['net_sales'] ?? 0.0) - $cost - ($v['overhead'] ?? 0.0),
            'received' => $v['received'] ?? 0.0, 'paid_out' => $v['paid_out'] ?? 0.0,
        ];
        foreach ($tot as $k => $_) {
            $tot[$k] += $row[$k];
        }
        $rows[] = $row;
    }
    return ['period' => $P['label'], 'months' => $rows, 'totals' => $tot];
}

/** Best-selling lines (by amount) on active invoices in a period. */
function report_top_items(array $P, int $limit = 15): array
{
    $st = db()->prepare("SELECT i.title, i.qty, i.line_total, i.doc_id FROM doc_items i JOIN docs d ON d.id = i.doc_id
        WHERE d.type = 'inv' AND d.status = 'active' AND d.date BETWEEN ? AND ? AND i.title <> ''");
    $st->execute([$P['from'], $P['to']]);
    $by = [];
    foreach ($st as $r) {
        $k = name_key($r['title']);
        $by[$k] = $by[$k] ?? ['item' => $r['title'], 'qty' => 0.0, 'amount' => 0.0, 'docs' => []];
        $by[$k]['qty'] += (float) ($r['qty'] ?? 1);
        $by[$k]['amount'] += (float) $r['line_total'];
        $by[$k]['docs'][(int) $r['doc_id']] = true;
    }
    $rows = array_values($by);
    usort($rows, function ($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });
    $rows = array_map(function ($r) {
        return ['item' => $r['item'], 'qty' => qty_fa($r['qty']), 'amount' => $r['amount'], 'invoices' => count($r['docs'])];
    }, array_slice($rows, 0, max(1, $limit)));
    return ['period' => $P['label'], 'rows' => $rows];
}
