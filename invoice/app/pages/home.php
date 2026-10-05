<?php
/** Dashboard: receivables, this month's numbers, debtors and recent documents. */
defined('APP_DIR') || exit;

$s = dashboard_stats();
$unit = setting('unit');
$sales = can('sales');
$costs = can('costs');
$debtors = $sales ? array_slice(customers_list('', true), 0, 10) : [];
$recentInv = $sales ? docs_list('inv', '', 0, 6) : [];
$recentQot = $sales ? docs_list('qot', '', 0, 6) : [];
$remaining = $sales ? invoice_remaining() : [];

layout_start('داشبورد', 'home');
?>
<main class="page">
  <div class="head">
    <h1>داشبورد</h1>
    <div class="actions">
      <?php if (can('sales', 'edit')): ?>
        <a class="btn btn-primary" href="<?= e(url('payments')) ?>"><?= icon('plus') ?> ثبت دریافت</a>
        <a class="btn btn-ghost" href="<?= e(url('doc', ['new' => 'inv'])) ?>"><?= icon('plus') ?> فاکتور</a>
        <a class="btn btn-ghost" href="<?= e(url('doc', ['new' => 'qot'])) ?>"><?= icon('plus') ?> پیش‌فاکتور</a>
      <?php endif; ?>
      <?php if (can('costs', 'edit')): ?>
        <a class="btn btn-ghost" href="<?= e(url('purchases')) ?>"><?= icon('plus') ?> خرید</a>
        <a class="btn btn-ghost" href="<?= e(url('expenses')) ?>"><?= icon('plus') ?> پرداخت</a>
      <?php endif; ?>
    </div>
  </div>

<?php
$tiles = [
    ['hero', 'hand-coins', url('customers', ['debtors' => 1]), 'طلب از مشتریان', money($s['receivable']), true,
        fa($s['debtors']) . ' مشتری بدهکار'],
    ['', 'receipt', url('docs', ['type' => 'inv']), 'فاکتورهای این ماه', money($s['inv_sum']), true,
        fa($s['inv_count']) . ' فاکتور'],
    ['', 'arrow-circle-down', url('payments'), 'دریافتی این ماه', money($s['pay_sum']), true,
        fa($s['pay_count']) . ' دریافت'],
    ['', 'file-text', url('docs', ['type' => 'qot']), 'پیش‌فاکتورهای باز', fa($s['open_qot']), false,
        'هنوز به فاکتور تبدیل نشده'],
    ['', 'shopping-cart', url('purchases'), 'خریدهای این ماه', money($s['pur_sum']), true,
        fa($s['pur_count']) . ' خرید برای فاکتورها'],
    ['', 'arrow-circle-up', url('expenses'), 'پرداختی این ماه', money($s['exp_direct'] + $s['exp_overhead']), true,
        'مستقیم ' . money($s['exp_direct']) . '، سربار ' . money($s['exp_overhead'])],
];
// The first four are sales figures, the last two costs.
$tiles = array_merge($sales ? array_slice($tiles, 0, 4) : [], $costs ? array_slice($tiles, 4) : []);
if (!$sales && !$costs): ?>
  <div class="card"><?= empty_state('lock', 'هنوز به هیچ بخشی دسترسی ندارید. از مدیر برنامه بخواهید دسترسی شما را تعیین کند.') ?></div>
<?php endif; ?>
<?php if ($tiles): ?>
  <div class="tiles">
<?php foreach ($tiles as [$cls, $ic, $href, $label, $value, $withUnit, $sub]): ?>
    <a class="tile <?= $cls ?>" href="<?= e($href) ?>">
      <span class="ico"><?= icon($ic) ?></span>
      <div class="k"><?= e($label) ?></div>
      <div class="v"><?= $value ?><?php if ($withUnit): ?> <small><?= e($unit) ?></small><?php endif; ?></div>
      <div class="s"><?= e($sub) ?></div>
    </a>
<?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($sales): ?>
  <section class="section">
    <div class="section-head">
      <h2>بدهکاران</h2>
      <?php if ($debtors): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('customers', ['debtors' => 1])) ?>">همه</a><?php endif; ?>
    </div>
    <?php if (!$debtors): ?>
      <div class="card"><?= empty_state('check-circle', 'هیچ مشتری بدهکاری ندارید.') ?></div>
    <?php else: ?>
      <div class="cards tight"><?php foreach ($debtors as $c) { echo customer_card($c, true); } ?></div>
    <?php endif; ?>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>آخرین فاکتورها</h2>
      <?php if ($recentInv): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('docs', ['type' => 'inv'])) ?>">همه</a><?php endif; ?>
    </div>
    <?php if (!$recentInv): ?>
      <div class="card"><?= empty_state('receipt', 'هنوز فاکتوری صادر نشده.') ?></div>
    <?php else: ?>
      <div class="cards tight"><?php foreach ($recentInv as $d) { echo doc_card($d, $remaining, true); } ?></div>
    <?php endif; ?>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>آخرین پیش‌فاکتورها</h2>
      <?php if ($recentQot): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('docs', ['type' => 'qot'])) ?>">همه</a><?php endif; ?>
    </div>
    <?php if (!$recentQot): ?>
      <div class="card"><?= empty_state('file-text', 'هنوز پیش‌فاکتوری ثبت نشده.') ?></div>
    <?php else: ?>
      <div class="cards tight"><?php foreach ($recentQot as $d) { echo doc_card($d, [], true); } ?></div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</main>
<?php
layout_end();
