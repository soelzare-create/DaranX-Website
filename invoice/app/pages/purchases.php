<?php
/** Purchases assigned to invoices (their cost) + the list, optionally for one invoice. */
defined('APP_DIR') || exit;

$error = null;
$docId = (int) query('doc');
$forDoc = $docId ? doc_get($docId) : null;
if ($forDoc && $forDoc['type'] !== 'inv') {
    $forDoc = null;
    $docId = 0;
}
$form = [
    'doc_id' => $forDoc ? (string) $forDoc['id'] : '',
    'supplier' => '',
    'title' => '',
    'amount' => '',
    'date' => fa(jtoday()),
    'note' => '',
];

if (is_post()) {
    $form = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST) + $form;
    try {
        $p = purchase_validate($_POST);
        $pid = purchase_save($p);
        $saved = purchase_get($pid);
        flash('ok', 'خرید ' . $saved['number'] . ' به مبلغ ' . money($p['amount']) . ' ' . setting('unit')
            . ' برای فاکتور ' . $saved['doc_number'] . ' ثبت شد.');
        redirect('purchases', $docId ? ['doc' => $docId] : []);
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

$invoices = invoice_options();
$q = clean_line(query('q'), 100);
$list = purchases_list($q, $docId);
$sum = 0.0;
$left = 0.0;
foreach ($list as $p) {
    $sum += (float) $p['amount'];
    $left += purchase_left($p);
}
$unit = setting('unit');

layout_start('خریدها', 'purchases', ['error' => $error]);
?>
<main class="page">
  <div class="head">
    <h1>خریدها<?php if ($forDoc): ?> <span class="badge info">فاکتور <span class="mono"><?= e($forDoc['number']) ?></span></span><?php endif; ?></h1>
    <div class="actions">
      <span class="muted">جمع: <b class="ink"><?= money($sum) ?> <?= e($unit) ?></b>
        <?php if ($left >= 0.5): ?> · پرداخت‌نشده: <b class="ink"><?= money($left) ?></b><?php endif; ?></span>
      <?php if ($forDoc): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('doc', ['id' => $forDoc['id']])) ?>">→ فاکتور</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('purchases')) ?>">همهٔ خریدها</a>
      <?php endif; ?>
    </div>
  </div>

  <details class="card collapse"<?= $error || $forDoc ? ' open' : '' ?>>
    <summary><span class="btn btn-primary btn-sm"><?= icon('plus') ?> ثبت خرید</span></summary>
    <?php if (!$invoices): ?>
      <p class="empty">هنوز فاکتور فعالی نیست. خرید همیشه به یک فاکتور وصل می‌شود؛ اول فاکتور را صادر کنید.</p>
    <?php else: ?>
      <form method="post" class="form mt">
        <?= csrf_field() ?>
        <?php purchase_fields($form, $invoices, suppliers_known()); ?>
        <div class="actions">
          <button class="btn btn-primary">ثبت خرید</button>
          <small class="muted">مبلغ خرید، بهای تمام‌شدهٔ همان فاکتور حساب می‌شود. پرداخت به فروشنده را از «پرداخت‌ها» ثبت کنید.</small>
        </div>
      </form>
    <?php endif; ?>
  </details>

  <section class="card">
    <form class="search" method="get">
      <input type="hidden" name="p" value="purchases">
      <?php if ($docId): ?><input type="hidden" name="doc" value="<?= (int) $docId ?>"><?php endif; ?>
      <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در شماره، فاکتور، فروشنده یا شرح…" aria-label="جستجو">
      <button class="btn btn-ghost">جستجو</button>
    </form>
    <?php if (!$list): ?>
      <?= $q !== '' ? empty_state('magnifying-glass', 'موردی پیدا نشد.') : empty_state('shopping-cart', 'هنوز خریدی ثبت نشده. هر خرید به یک فاکتور وصل می‌شود.') ?>
    <?php else: ?>
      <div class="tbl"><table class="list wide">
        <thead><tr><th>شماره</th><th>تاریخ</th><th>فاکتور</th><th>فروشنده</th><th>شرح</th>
          <th class="num">مبلغ (<?= e($unit) ?>)</th><th>پرداخت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $p): ?>
          <tr>
            <td class="mono nowrap"><?= e($p['number']) ?></td>
            <td class="nowrap"><?= fa($p['date']) ?></td>
            <td class="nowrap"><a class="mono" href="<?= e(url('doc', ['id' => $p['doc_id']])) ?>"><?= e($p['doc_number']) ?></a>
              <small class="muted"><?= e($p['cust_name']) ?></small></td>
            <td><?= e($p['supplier']) ?></td>
            <td class="small"><?= e($p['title']) ?></td>
            <td class="num"><?= money($p['amount']) ?></td>
            <td class="nowrap"><?= purchase_badge($p) ?></td>
            <td class="row-actions nowrap">
              <?php if (purchase_left($p) >= 0.5): ?>
                <a class="btn btn-accent btn-sm" href="<?= e(url('expenses', ['kind' => 'direct', 'purchase' => $p['id'],
                    'amount' => (int) purchase_left($p)])) ?>">ثبت پرداخت</a>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('purchase', ['id' => $p['id']])) ?>">ویرایش</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
