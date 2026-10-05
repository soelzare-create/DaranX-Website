<?php
/**
 * Import hand-made Excel/CSV proformas into the database.
 *
 * You drop your template files (one proforma per file, .xlsx or .csv) into the
 * data/imports/ folder on the host; this page reads them server-side, shows a
 * preview, and saves each one as a new proforma. The number on your sheet is
 * ignored — every imported proforma gets the next sequential QOT number, so the
 * series continues cleanly after the last proforma already in the system.
 */
defined('APP_DIR') || exit;

$GLOBALS['activity_via'] = 'ورود از اکسل';
require_once APP_DIR . '/importlib.php';

$error = null;
$results = null; // list of [name, ok(bool), msg, number?, id?]

import_dir(); // make sure data/imports (+ done) exist

if (is_post()) {
    try {
        $action = post('action');
        if ($action === 'upload') {
            [$saved, $errs] = import_save_uploads($_FILES['files'] ?? []);
            if (!$saved && !$errs) {
                throw new UserError('فایلی برای آپلود انتخاب نشده بود.');
            }
            if ($saved) {
                flash('ok', count($saved) === 1
                    ? 'فایل «' . $saved[0] . '» آپلود شد. حالا پیش‌نمایش آن را ببینید و ثبت کنید.'
                    : fa((string) count($saved)) . ' فایل آپلود شد. حالا آن‌ها را ثبت کنید.');
            }
            foreach ($errs as $m) {
                flash('err', $m);
            }
            redirect('import');
        }

        $names = [];
        if ($action === 'import_all') {
            $names = import_files();
            if (!$names) {
                throw new UserError('هیچ فایلی برای ورود در پوشهٔ imports پیدا نشد.');
            }
        } elseif ($action === 'import') {
            $one = basename((string) post('name'));
            if ($one === '') {
                throw new UserError('فایل مشخص نشده است.');
            }
            $names = [$one];
        } else {
            throw new UserError('عملیات نامعتبر است.');
        }

        $results = [];
        foreach ($names as $name) {
            try {
                $grid = import_read_grid(import_path($name));
                $form = import_parse($grid);
                $id = doc_save(doc_validate($form, 'qot'));
                $doc = doc_get($id);
                import_archive($name);
                $results[] = [
                    'name' => $name, 'ok' => true, 'id' => $id,
                    'number' => $doc ? $doc['number'] : '',
                    'msg' => 'ثبت شد',
                    'warn' => empty($form['_date_ok']) ? 'تاریخ فایل خوانده نشد؛ تاریخ امروز ثبت شد.' : null,
                ];
            } catch (UserError $ex) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => $ex->getMessage()];
            }
        }
    } catch (UserError $ex) {
        $error = $ex->getMessage();
    }
}

// Preview of the files currently waiting to be imported.
$pending = [];
foreach (import_files() as $name) {
    $row = ['name' => $name, 'ok' => false, 'error' => null,
        'cust' => '', 'date' => '', 'items' => 0, 'total' => 0, 'date_ok' => true];
    try {
        $grid = import_read_grid(import_path($name));
        $form = import_parse($grid);
        $valid = doc_validate($form, 'qot');
        $row['ok'] = true;
        $row['cust'] = $valid['cust_name'];
        $row['date'] = $valid['date'];
        $row['date_ok'] = !empty($form['_date_ok']);
        $row['items'] = count($valid['items']);
        $row['total'] = $valid['total'];
    } catch (UserError $ex) {
        $row['error'] = $ex->getMessage();
    } catch (Throwable $ex) {
        $row['error'] = 'خطا در خواندن فایل.';
    }
    $pending[] = $row;
}
$okCount = 0;
foreach ($pending as $p) {
    if ($p['ok']) {
        $okCount++;
    }
}

$unit = setting('unit');
$importsPath = 'data/imports/';

layout_start('ورود از اکسل', 'import', ['error' => $error]);
?>
<main class="page">
  <div class="head"><h1>ورود پیش‌فاکتور از اکسل</h1></div>

  <section class="card">
    <h2>آپلود فایل از کامپیوتر</h2>
    <p class="muted">فایل اکسل پیش‌فاکتور خود را (<code dir="ltr">.xlsx</code> یا <code dir="ltr">.csv</code>) انتخاب و آپلود کنید.
      می‌توانید چند فایل را با هم انتخاب کنید؛ هر فایل یک پیش‌فاکتور است.</p>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="actions" style="gap:.6rem;flex-wrap:wrap">
        <input type="file" name="files[]" accept=".xlsx,.csv" multiple required
               aria-label="انتخاب فایل اکسل">
        <button class="btn btn-primary">آپلود</button>
      </div>
    </form>
  </section>

  <?php if ($results !== null): ?>
    <section class="section">
      <div class="section-head"><h2>نتیجهٔ ورود</h2></div>
      <div class="cards">
        <?php foreach ($results as $r): ?>
          <article class="rcard static<?= $r['ok'] ? '' : ' err' ?>">
            <div class="rc-top">
              <span class="rc-file"><?= icon('file-xls') ?><span dir="ltr"><?= e($r['name']) ?></span></span>
              <?php if ($r['ok']): ?><span class="badge ok">ثبت شد</span><?php else: ?><span class="badge danger">خطا</span><?php endif; ?>
            </div>
            <?php if ($r['ok']): ?>
              <div class="rc-meta"><a href="<?= e(url('doc', ['id' => $r['id']])) ?>"><?= icon('file-text') ?>پیش‌فاکتور <span class="mono"><?= e($r['number']) ?></span></a></div>
              <?php if (!empty($r['warn'])): ?><p class="rc-note wrap"><?= icon('warning-circle') ?><?= e($r['warn']) ?></p><?php endif; ?>
            <?php else: ?>
              <p class="rc-note wrap danger"><?= icon('warning-circle') ?><?= e($r['msg']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="muted mt">فایل‌های ثبت‌شده به پوشهٔ <code dir="ltr"><?= e($importsPath) ?>done/</code> منتقل شدند تا دوباره وارد نشوند.</p>
    </section>
  <?php endif; ?>

  <section class="section">
    <div class="section-head">
      <h2>فایل‌های آمادهٔ ورود</h2>
      <?php if ($okCount > 0): ?>
        <form method="post"
              data-confirm="<?= e(fa((string) $okCount)) ?> فایل سالم به‌عنوان پیش‌فاکتور جدید ثبت شوند؟ هر کدام شمارهٔ تازه می‌گیرند.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="import_all">
          <button class="btn btn-accent btn-sm">ثبت همهٔ فایل‌های سالم (<?= e(fa((string) $okCount)) ?>)</button>
        </form>
      <?php endif; ?>
    </div>
    <?php if (!$pending): ?>
      <div class="card">
      <p>هنوز فایلی آپلود نشده است.</p>
      <p class="muted">از کادر «آپلود فایل از کامپیوتر» در بالا، فایل اکسل را انتخاب و آپلود کنید.
        (به‌جای آپلود، می‌توانید فایل‌ها را از طریق File Manager هاست در پوشهٔ
        <code dir="ltr"><?= e($importsPath) ?></code> ، کنار <code dir="ltr">index.php</code> ← پوشهٔ data، هم بگذارید.)</p>
      </div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($pending as $p): ?>
          <article class="rcard static<?= $p['ok'] ? '' : ' err' ?>">
            <span class="rc-file"><?= icon('file-xls') ?><span dir="ltr"><?= e($p['name']) ?></span></span>
            <?php if ($p['ok']): ?>
              <div class="rc-title"><?= e($p['cust']) ?></div>
              <div class="rc-meta">
                <span<?= $p['date_ok'] ? '' : ' title="تاریخ فایل خوانده نشد؛ تاریخ امروز پیشنهاد شد"' ?>><?= icon($p['date_ok'] ? 'calendar-blank' : 'warning-circle') ?><?= e(fa($p['date'])) ?></span>
                <span><?= icon('tag') ?><?= e(fa((string) $p['items'])) ?> قلم</span>
              </div>
              <div class="rc-foot">
                <?= amount_html($p['total']) ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="import">
                  <input type="hidden" name="name" value="<?= e($p['name']) ?>">
                  <button class="btn btn-primary btn-sm">ثبت این فایل</button>
                </form>
              </div>
            <?php else: ?>
              <p class="rc-note wrap danger"><?= icon('warning-circle') ?><?= e($p['error']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>راهنما</h2>
    <ul>
      <li>هر فایل باید یک پیش‌فاکتور باشد؛ همان قالب اکسل شما با برچسب‌های «نام مشتری» و «تاریخ صدور»
        و جدول اقلام با ستون‌های <code dir="ltr">Title</code> / <code dir="ltr">QTY</code> / <code dir="ltr">Price</code>.</li>
      <li>شمارهٔ روی فایل نادیده گرفته می‌شود؛ هر پیش‌فاکتور شمارهٔ ترتیبی بعدی را می‌گیرد تا دنبالهٔ شماره‌ها
        پس از آخرین پیش‌فاکتور موجود ادامه پیدا کند.</li>
      <li>پس از ثبت، فایل به پوشهٔ <code dir="ltr">done/</code> منتقل می‌شود تا دوباره وارد نشود.</li>
      <li>فایل‌های PDF مستقیماً خوانده نمی‌شوند؛ از نسخهٔ اکسل (<code dir="ltr">.xlsx</code>) یا
        <code dir="ltr">.csv</code> همان پیش‌فاکتور استفاده کنید.</li>
    </ul>
  </section>
</main>
<?php
layout_end();
