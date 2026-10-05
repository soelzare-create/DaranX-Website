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
require_once APP_DIR . '/importlib.php';

$error = null;
$results = null; // list of [name, ok(bool), msg, number?, id?]

import_dir(); // make sure data/imports (+ done) exist

if (is_post()) {
    try {
        $action = post('action');
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

  <?php if ($results !== null): ?>
    <section class="card">
      <h2>نتیجهٔ ورود</h2>
      <div class="tbl"><table class="list wide">
        <thead><tr><th>فایل</th><th>وضعیت</th></tr></thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr>
              <td dir="ltr" style="text-align:right"><?= e($r['name']) ?></td>
              <td>
                <?php if ($r['ok']): ?>
                  <span class="badge ok">✓ پیش‌فاکتور
                    <a href="<?= e(url('doc', ['id' => $r['id']])) ?>" dir="ltr"><?= e($r['number']) ?></a>
                    ثبت شد</span>
                  <?php if (!empty($r['warn'])): ?><small class="muted"> — <?= e($r['warn']) ?></small><?php endif; ?>
                <?php else: ?>
                  <span class="badge danger">✗ <?= e($r['msg']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="muted">فایل‌های ثبت‌شده به پوشهٔ <code dir="ltr"><?= e($importsPath) ?>done/</code> منتقل شدند تا دوباره وارد نشوند.</p>
    </section>
  <?php endif; ?>

  <section class="card">
    <h2>فایل‌های آمادهٔ ورود</h2>
    <?php if (!$pending): ?>
      <p>هیچ فایلی در پوشهٔ ورود نیست.</p>
      <p class="muted">فایل‌های اکسل خود را (هر پیش‌فاکتور در یک فایل، با پسوند
        <code dir="ltr">.xlsx</code> یا <code dir="ltr">.csv</code>) از طریق File Manager هاست
        در پوشهٔ <code dir="ltr"><?= e($importsPath) ?></code> (کنار <code dir="ltr">index.php</code> ← پوشهٔ data) بگذارید،
        سپس این صفحه را تازه کنید.</p>
    <?php else: ?>
      <div class="tbl"><table class="list wide">
        <thead>
          <tr><th>فایل</th><th>مشتری</th><th>تاریخ</th><th>اقلام</th><th>جمع کل (<?= e($unit) ?>)</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): ?>
            <tr>
              <td dir="ltr" style="text-align:right"><?= e($p['name']) ?></td>
              <?php if ($p['ok']): ?>
                <td><?= e($p['cust']) ?></td>
                <td dir="ltr"><?= e(fa($p['date'])) ?><?php if (!$p['date_ok']): ?>
                  <small class="muted" title="تاریخ فایل خوانده نشد؛ تاریخ امروز پیشنهاد شد">⚠</small><?php endif; ?></td>
                <td><?= e(fa((string) $p['items'])) ?></td>
                <td><?= e(money($p['total'])) ?></td>
                <td>
                  <form method="post" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="name" value="<?= e($p['name']) ?>">
                    <button class="btn btn-primary btn-sm">ثبت این فایل</button>
                  </form>
                </td>
              <?php else: ?>
                <td colspan="4"><span class="badge danger"><?= e($p['error']) ?></span></td>
                <td></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>

      <?php if ($okCount > 0): ?>
        <form method="post" class="actions" style="margin-top:1rem"
              data-confirm="<?= e(fa((string) $okCount)) ?> فایل سالم به‌عنوان پیش‌فاکتور جدید ثبت شوند؟ هر کدام شمارهٔ تازه می‌گیرند.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="import_all">
          <button class="btn btn-accent">ثبت همهٔ فایل‌های سالم (<?= e(fa((string) $okCount)) ?>)</button>
        </form>
      <?php endif; ?>
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
