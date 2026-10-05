<?php
/** Edit or delete a purchase; shows the payments made for it. */
defined('APP_DIR') || exit;

$id = (int) query('id');
$pur = purchase_get($id);
if (!$pur) {
    not_found();
}

$error = null;
$form = [
    'doc_id' => (string) $pur['doc_id'],
    'supplier' => $pur['supplier'],
    'title' => $pur['title'],
    'amount' => money($pur['amount']),
    'date' => fa($pur['date']),
    'note' => $pur['note'],
];

if (is_post()) {
    try {
        if (post('action') === 'delete') {
            purchase_delete($id);
            flash('ok', 'خرید ' . $pur['number'] . ' حذف شد.');
            redirect('purchases', ['doc' => $pur['doc_id']]);
        }
        $form = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST) + $form;
        $p = purchase_validate($_POST, $pur);
        purchase_save($p, $id);
        flash('ok', 'خرید ' . $pur['number'] . ' ویرایش شد.');
        redirect('purchases', ['doc' => $p['doc_id']]);
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

$payments = expenses_of_purchase($id);

layout_start('خرید ' . $pur['number'], 'purchases', ['error' => $error]);
?>
<main class="page narrow">
  <div class="head">
    <h1>خرید <span class="mono"><?= e($pur['number']) ?></span> <?= purchase_badge($pur) ?></h1>
    <div class="actions">
      <a class="btn btn-ghost" href="<?= e(url('purchases', ['doc' => $pur['doc_id']])) ?>">→ خریدهای فاکتور <?= e($pur['doc_number']) ?></a>
    </div>
  </div>

  <section class="card">
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php purchase_fields($form, invoice_options((int) $pur['doc_id']), suppliers_known()); ?>
      <div class="actions"><button class="btn btn-primary">ذخیره</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>پرداخت‌های این خرید</h2>
      <?php if (purchase_left($pur) >= 0.5): ?>
        <a class="btn btn-accent btn-sm" href="<?= e(url('expenses', ['kind' => 'direct', 'purchase' => $id,
            'amount' => (int) purchase_left($pur)])) ?>"><?= icon('plus') ?> ثبت پرداخت (مانده <?= money(purchase_left($pur)) ?>)</a>
      <?php endif; ?>
    </div>
    <?php if (!$payments): ?>
      <?= empty_state('arrow-circle-up', 'هنوز پرداختی بابت این خرید ثبت نشده.') ?>
    <?php else: ?>
      <div class="cards tight">
        <?php foreach ($payments as $e): ?>
          <article class="rcard compact">
            <div class="rc-top">
              <a class="rc-title stretch" href="<?= e(url('expense', ['id' => $e['id']])) ?>"><?= e($e['payee'] !== '' ? $e['payee'] : $e['category']) ?></a>
            </div>
            <div class="rc-meta">
              <span><?= icon('calendar-blank') ?><?= fa($e['date']) ?></span>
              <span><?= icon('credit-card') ?><?= e($e['method']) ?></span>
            </div>
            <div class="rc-foot"><?= amount_html($e['amount']) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <form method="post" class="mt" data-confirm="خرید <?= e($pur['number']) ?> حذف شود؟">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger btn-sm">حذف خرید</button>
  </form>
</main>
<?php
layout_end();
