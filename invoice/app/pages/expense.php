<?php
/** Edit or delete a recorded payment out (direct cost or overhead). */
defined('APP_DIR') || exit;

$id = (int) query('id');
$exp = expense_get($id);
if (!$exp) {
    not_found();
}

$error = null;
$form = [
    'kind' => $exp['kind'],
    'doc_id' => (string) ($exp['doc_id'] ?? ''),
    'purchase_id' => (string) ($exp['purchase_id'] ?? ''),
    'category' => $exp['category'],
    'payee' => $exp['payee'],
    'amount' => money($exp['amount']),
    'date' => fa($exp['date']),
    'method' => $exp['method'],
    'note' => $exp['note'],
];

if (is_post()) {
    if (post('action') === 'delete') {
        expense_delete($id);
        flash('ok', 'پرداخت حذف شد.');
        redirect('expenses', ['kind' => $exp['kind']]);
    }
    $form = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST) + $form;
    try {
        $e = expense_validate($_POST, $exp);
        expense_save($e, $id);
        flash('ok', 'پرداخت ویرایش شد.');
        redirect('expenses', ['kind' => $e['kind']]);
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

$purchases = array_values(array_filter(purchases_list('', 0, 500), function ($p) use ($form) {
    return purchase_left($p) >= 0.5 || (string) $p['id'] === (string) $form['purchase_id'];
}));

layout_start('ویرایش پرداخت', 'expenses', ['error' => $error]);
?>
<main class="page narrow">
  <div class="head">
    <h1><?= can('costs', 'edit') ? 'ویرایش پرداخت' : 'پرداخت' ?></h1>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('expenses', ['kind' => $exp['kind']])) ?>">→ پرداخت‌ها</a></div>
  </div>
  <section class="card">
    <form method="post" class="form" data-expense-form>
      <fieldset class="plain"<?= can('costs', 'edit') ? '' : ' disabled' ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php expense_fields($form, invoice_options((int) $form['doc_id']), $purchases, payees_known()); ?>
      <div class="actions"><button class="btn btn-primary">ذخیره</button></div>
    </fieldset>
    </form>
    <?php if (can('costs', 'edit')): ?>
    <form method="post" class="mt" data-confirm="این پرداخت حذف شود؟">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger btn-sm">حذف پرداخت</button>
    </form>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
