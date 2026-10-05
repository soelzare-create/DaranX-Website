<?php
/** List of proformas or invoices, with search. */
defined('APP_DIR') || exit;

$type = query('type') === 'inv' ? 'inv' : 'qot';
$q = clean_line(query('q'), 100);
$docs = docs_list($type, $q);
$remaining = $type === 'inv' ? invoice_remaining() : [];
$title = $type === 'inv' ? 'فاکتورها' : 'پیش‌فاکتورها';

layout_start($title, $type);
?>
<main class="page">
  <div class="head">
    <h1><?= e($title) ?></h1>
    <div class="actions">
      <?php if (can('sales', 'edit')): ?><a class="btn btn-primary" href="<?= e(url('doc', ['new' => $type])) ?>"><?= icon('plus') ?> <?= e(DOC_NAMES[$type]) ?> جدید</a><?php endif; ?>
    </div>
  </div>

  <section>
    <div class="list-tools">
      <form class="search" method="get">
        <input type="hidden" name="p" value="docs">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در شماره یا نام مشتری…" aria-label="جستجو">
        <button class="btn btn-ghost">جستجو</button>
        <?php if ($q !== ''): ?><a class="btn btn-link" href="<?= e(url('docs', ['type' => $type])) ?>">نمایش همه</a><?php endif; ?>
      </form>
      <?php if ($docs): ?><span class="sum"><?= fa(count($docs)) ?> <?= e(DOC_NAMES[$type]) ?></span><?php endif; ?>
    </div>

    <?php if (!$docs): ?>
      <div class="card"><?= $q !== '' ? empty_state('magnifying-glass', 'موردی پیدا نشد.')
        : empty_state($type === 'inv' ? 'receipt' : 'file-text', 'هنوز ' . DOC_NAMES[$type] . 'ی ثبت نشده است.',
            can('sales', 'edit') ? '<a class="btn btn-primary btn-sm" href="' . e(url('doc', ['new' => $type])) . '">' . icon('plus') . ' ' . e(DOC_NAMES[$type]) . ' جدید</a>' : '') ?></div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($docs as $d): ?><?= doc_card($d, $remaining) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
