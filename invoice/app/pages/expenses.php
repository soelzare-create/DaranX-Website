<?php
/**
 * Money paid out, two kinds: direct costs of an invoice (paying for a
 * purchase, a courier, …) and company overhead (rent, salaries, …).
 */
defined('APP_DIR') || exit;

$error = null;
$kind = query('kind');
$kind = isset(EXPENSE_KINDS[$kind]) ? $kind : '';
$docId = (int) query('doc');
$forDoc = $docId ? doc_get($docId) : null;
if (!$forDoc || $forDoc['type'] !== 'inv') {
    $forDoc = null;
    $docId = 0;
}

// Prefill from a link (an invoice's or a purchase's «ثبت پرداخت»).
$form = [
    'kind' => $kind ?: 'direct',
    'doc_id' => $forDoc ? (string) $forDoc['id'] : '',
    'purchase_id' => '',
    'category' => '',
    'payee' => '',
    'amount' => is_numeric(query('amount')) && (float) query('amount') > 0 ? money(query('amount')) : '',
    'date' => fa(jtoday()),
    'method' => PAY_METHODS[0],
    'note' => '',
];
$prePurchase = (int) query('purchase') ? purchase_get((int) query('purchase')) : null;
if ($prePurchase) {
    $form['kind'] = 'direct';
    $form['purchase_id'] = (string) $prePurchase['id'];
    $form['doc_id'] = (string) $prePurchase['doc_id'];
    $form['payee'] = $prePurchase['supplier'];
    $form['category'] = DIRECT_CATEGORIES[0];
}

if (is_post()) {
    $form = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST) + $form;
    try {
        $e = expense_validate($_POST);
        expense_save($e);
        $what = $e['kind'] === 'overhead' ? 'هزینهٔ سربار' : 'هزینهٔ مستقیم';
        flash('ok', $what . ' «' . $e['category'] . '» به مبلغ ' . money($e['amount']) . ' ' . setting('unit') . ' ثبت شد.');
        if ($e['purchase_id']) {
            redirect('purchase', ['id' => $e['purchase_id']]);
        }
        redirect('expenses', array_filter(['kind' => $kind, 'doc' => $docId ?: null]));
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

$q = clean_line(query('q'), 100);
$list = expenses_list($kind, $q, $docId);
$sums = ['direct' => 0.0, 'overhead' => 0.0];
foreach ($list as $e) {
    $sums[$e['kind']] += (float) $e['amount'];
}
$unit = setting('unit');
// Purchases still owed to their supplier, plus the one being paid now.
$purchases = array_values(array_filter(purchases_list('', 0, 500), function ($p) use ($form) {
    return purchase_left($p) >= 0.5 || (string) $p['id'] === (string) $form['purchase_id'];
}));

layout_start('پرداخت‌ها', 'expenses', ['error' => $error]);
?>
<main class="page">
  <div class="head">
    <h1>پرداخت‌ها<?= $forDoc ? ' — فاکتور <span class="mono">' . e($forDoc['number']) . '</span>' : '' ?></h1>
    <div class="actions">
      <?php foreach (['' => 'همه', 'direct' => 'مستقیم فاکتورها', 'overhead' => 'سربار شرکت'] as $k => $label): ?>
        <a class="btn btn-sm <?= $k === $kind ? 'btn-primary' : 'btn-ghost' ?>"
           href="<?= e(url('expenses', array_filter(['kind' => $k ?: null, 'doc' => $docId ?: null]))) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($forDoc): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('doc', ['id' => $forDoc['id']])) ?>">→ فاکتور</a>
      <?php endif; ?>
    </div>
  </div>

  <details class="card collapse"<?= $error || $prePurchase || $forDoc || $kind ? ' open' : '' ?>>
    <summary><span class="btn btn-primary btn-sm">+ ثبت پرداخت</span></summary>
    <form method="post" class="form mt" data-expense-form>
      <?= csrf_field() ?>
      <?php expense_fields($form, invoice_options((int) $form['doc_id']), $purchases, payees_known()); ?>
      <div class="actions">
        <button class="btn btn-primary">ثبت پرداخت</button>
        <small class="muted">هزینهٔ مستقیم از سود همان فاکتور کم می‌شود؛ هزینهٔ سربار به هیچ فاکتوری وصل نیست.</small>
      </div>
    </form>
  </details>

  <section class="card">
    <div class="card-head">
      <form class="search" method="get">
        <input type="hidden" name="p" value="expenses">
        <?php if ($kind): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?>
        <?php if ($docId): ?><input type="hidden" name="doc" value="<?= (int) $docId ?>"><?php endif; ?>
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در بابت، گیرنده، فاکتور یا توضیح…" aria-label="جستجو">
        <button class="btn btn-ghost">جستجو</button>
      </form>
      <span class="muted">
        <?php if ($kind !== 'overhead'): ?>مستقیم: <b class="ink"><?= money($sums['direct']) ?></b><?php endif; ?>
        <?php if ($kind === ''): ?> · <?php endif; ?>
        <?php if ($kind !== 'direct'): ?>سربار: <b class="ink"><?= money($sums['overhead']) ?></b><?php endif; ?>
        <?= e($unit) ?>
      </span>
    </div>
    <?php if (!$list): ?>
      <p class="empty"><?= $q !== '' ? 'موردی پیدا نشد.' : 'هنوز پرداختی ثبت نشده.' ?></p>
    <?php else: ?>
      <div class="tbl"><table class="list wide">
        <thead><tr><th>تاریخ</th><th>نوع</th><th>بابت</th><th>فاکتور / خرید</th><th>گیرنده</th><th>روش</th>
          <th class="num">مبلغ (<?= e($unit) ?>)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $e): ?>
          <tr>
            <td class="nowrap"><?= fa($e['date']) ?></td>
            <td class="nowrap"><span class="badge <?= $e['kind'] === 'direct' ? 'info' : 'muted' ?>"><?= e(EXPENSE_KIND_SHORT[$e['kind']]) ?></span></td>
            <td><?= e($e['category']) ?><?php if ($e['note'] !== ''): ?><br><small class="muted"><?= e($e['note']) ?></small><?php endif; ?></td>
            <td class="nowrap">
              <?php if ($e['doc_id']): ?>
                <a class="mono" href="<?= e(url('doc', ['id' => $e['doc_id']])) ?>"><?= e($e['doc_number']) ?></a>
              <?php endif; ?>
              <?php if ($e['purchase_id']): ?>
                · <a class="mono" href="<?= e(url('purchase', ['id' => $e['purchase_id']])) ?>"><?= e($e['purchase_number']) ?></a>
              <?php endif; ?>
              <?php if (!$e['doc_id']): ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td><?= e($e['payee']) ?></td>
            <td class="nowrap"><?= e($e['method']) ?></td>
            <td class="num"><?= money($e['amount']) ?></td>
            <td class="row-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('expense', ['id' => $e['id']])) ?>">ویرایش</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
