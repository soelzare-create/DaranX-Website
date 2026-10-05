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
    <h1>پرداخت‌ها<?php if ($forDoc): ?> <span class="badge info">فاکتور <span class="mono"><?= e($forDoc['number']) ?></span></span><?php endif; ?></h1>
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
    <summary><span class="btn btn-primary btn-sm"><?= icon('plus') ?> ثبت پرداخت</span></summary>
    <form method="post" class="form mt" data-expense-form>
      <?= csrf_field() ?>
      <?php expense_fields($form, invoice_options((int) $form['doc_id']), $purchases, payees_known()); ?>
      <div class="actions">
        <button class="btn btn-primary">ثبت پرداخت</button>
        <small class="muted">هزینهٔ مستقیم از سود همان فاکتور کم می‌شود؛ هزینهٔ سربار به هیچ فاکتوری وصل نیست.</small>
      </div>
    </form>
  </details>

  <section>
    <div class="list-tools">
      <form class="search" method="get">
        <input type="hidden" name="p" value="expenses">
        <?php if ($kind): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?>
        <?php if ($docId): ?><input type="hidden" name="doc" value="<?= (int) $docId ?>"><?php endif; ?>
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در بابت، گیرنده، فاکتور یا توضیح…" aria-label="جستجو">
        <button class="btn btn-ghost">جستجو</button>
      </form>
      <span class="sum">
        <?php if ($kind !== 'overhead'): ?>مستقیم: <b class="ink"><?= money($sums['direct']) ?></b><?php endif; ?>
        <?php if ($kind === ''): ?> · <?php endif; ?>
        <?php if ($kind !== 'direct'): ?>سربار: <b class="ink"><?= money($sums['overhead']) ?></b><?php endif; ?>
        <?= e($unit) ?>
      </span>
    </div>
    <?php if (!$list): ?>
      <div class="card"><?= $q !== '' ? empty_state('magnifying-glass', 'موردی پیدا نشد.') : empty_state('arrow-circle-up', 'هنوز پرداختی ثبت نشده.') ?></div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($list as $e): ?>
          <article class="rcard">
            <div class="rc-top">
              <a class="rc-title stretch" href="<?= e(url('expense', ['id' => $e['id']])) ?>"><?= e($e['category']) ?></a>
              <span class="badge <?= $e['kind'] === 'direct' ? 'info' : 'muted' ?>"><?= e(EXPENSE_KIND_SHORT[$e['kind']]) ?></span>
            </div>
            <div class="rc-meta">
              <?php if ($e['payee'] !== ''): ?><span><?= icon('user') ?><?= e($e['payee']) ?></span><?php endif; ?>
              <span><?= icon('calendar-blank') ?><?= fa($e['date']) ?></span>
              <span><?= icon('credit-card') ?><?= e($e['method']) ?></span>
            </div>
            <?php if ($e['doc_id']): ?>
              <div class="rc-meta">
                <a href="<?= e(url('doc', ['id' => $e['doc_id']])) ?>"><?= icon('receipt') ?><span class="mono"><?= e($e['doc_number']) ?></span></a>
                <?php if ($e['purchase_id']): ?>
                  <a href="<?= e(url('purchase', ['id' => $e['purchase_id']])) ?>"><?= icon('shopping-cart') ?><span class="mono"><?= e($e['purchase_number']) ?></span></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($e['note'] !== ''): ?><p class="rc-note"><?= e($e['note']) ?></p><?php endif; ?>
            <div class="rc-foot">
              <?= amount_html($e['amount']) ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
