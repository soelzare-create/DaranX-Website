<?php
/** Customers with their current balance; add a customer. */
defined('APP_DIR') || exit;

$error = null;
$form = [];
if (is_post()) {
    $form = $_POST;
    try {
        $id = customer_save(customer_validate($_POST));
        flash('ok', 'مشتری ثبت شد.');
        redirect('customer', ['id' => $id]);
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

$q = clean_line(query('q'), 100);
$debtorsOnly = query('debtors') === '1';
$list = customers_list($q, $debtorsOnly);
$total = 0.0;
foreach ($list as $c) {
    $total += max(0, (float) $c['balance']);
}

layout_start('مشتریان', 'customers', ['error' => $error]);
?>
<main class="page">
  <div class="head">
    <h1>مشتریان</h1>
    <div class="actions">
      <span class="muted">جمع طلب: <b class="ink"><?= money($total) ?> <?= e(setting('unit')) ?></b></span>
    </div>
  </div>

  <details class="card collapse"<?= $error ? ' open' : '' ?>>
    <summary><span class="btn btn-primary btn-sm"><?= icon('plus') ?> مشتری جدید</span></summary>
    <form method="post" class="form mt">
      <?= csrf_field() ?>
      <?php customer_fields($form ? array_map(function ($v) { return is_string($v) ? $v : ''; }, $form) + ['opening_balance' => 0] : []); ?>
      <div class="actions"><button class="btn btn-primary">ثبت مشتری</button></div>
    </form>
  </details>

  <section>
    <div class="list-tools">
      <form class="search" method="get">
        <input type="hidden" name="p" value="customers">
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در نام یا شماره تماس…" aria-label="جستجو">
        <label class="chk"><input type="checkbox" name="debtors" value="1"<?= $debtorsOnly ? ' checked' : '' ?> data-autosubmit> فقط بدهکاران</label>
        <button class="btn btn-ghost">جستجو</button>
      </form>
      <?php if ($list): ?><span class="sum"><?= fa(count($list)) ?> مشتری</span><?php endif; ?>
    </div>

    <?php if (!$list): ?>
      <div class="card"><?= ($q !== '' || $debtorsOnly) ? empty_state('magnifying-glass', 'موردی پیدا نشد.')
        : empty_state('users', 'هنوز مشتری‌ای ثبت نشده. با اولین پیش‌فاکتور یا فاکتور، مشتری خودکار ساخته می‌شود.') ?></div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($list as $c): ?><?= customer_card($c) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
