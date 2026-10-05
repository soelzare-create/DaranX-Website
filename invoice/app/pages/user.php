<?php
/** Create or edit an account: name, sign-in, admin or per-area access; switch off or delete. Admins only. */
defined('APP_DIR') || exit;

$isNew = query('new') !== '';
$id = (int) query('id');
$u = $isNew ? null : user_get($id);
if (!$isNew && !$u) {
    not_found();
}
$me = (int) current_user()['id'];
$error = null;

$form = $u ? [
    'full_name' => $u['full_name'], 'username' => $u['username'], 'is_admin' => (int) $u['is_admin'],
    'perms' => perms_decode((string) $u['perms']),
] : [
    'full_name' => '', 'username' => '', 'is_admin' => 0, 'perms' => PERM_PRESETS['sales'][1],
];

if (is_post()) {
    try {
        $action = post('action');
        if ($action === 'toggle' && $u) {
            user_set_active($id, !$u['active']);
            flash('ok', 'حساب ' . user_label($u) . ($u['active'] ? ' غیرفعال شد و از برنامه خارج شد.' : ' دوباره فعال شد.'));
            redirect('user', ['id' => $id]);
        }
        if ($action === 'delete' && $u) {
            user_delete($id);
            flash('ok', 'حساب ' . user_label($u) . ' حذف شد. کارهایی که انجام داده بود در گزارش فعالیت می‌ماند.');
            redirect('users');
        }
        $in = $_POST;
        if ($u && $id === $me) {
            $in['is_admin'] = $u['is_admin'] ? '1' : ''; // nobody demotes themselves by accident
        }
        $v = user_validate($in, $u);
        $saved = user_save($v, $u ? $id : null);
        flash('ok', $u ? 'تغییرات حساب ' . user_label($v) . ' ذخیره شد.'
            : 'حساب ' . user_label($v) . ' ساخته شد. نام کاربری و رمز را به او بدهید.');
        redirect('user', ['id' => $saved]);
    } catch (UserError $ex) {
        $error = $ex->getMessage();
        $form['full_name'] = post('full_name');
        $form['username'] = post('username');
        $form['is_admin'] = post('is_admin') !== '' ? 1 : 0;
        foreach (PERM_AREAS as $area => $_) {
            $form['perms'][$area] = (int) post('perm_' . $area);
        }
    }
}

$recent = $u ? activity_list($id, '', 12) : [];
$self = $u && $id === $me;

layout_start($u ? user_label($u) : 'کاربر جدید', 'users', ['error' => $error]);
?>
<main class="page narrow">
  <div class="head">
    <h1><?= $u ? e(user_label($u)) : 'کاربر جدید' ?>
      <?php if ($u && !$u['active']): ?><span class="badge muted">غیرفعال</span><?php endif; ?></h1>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('users')) ?>">→ کاربران</a></div>
  </div>

  <form method="post" class="card form" data-user-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="fgrid">
      <label class="field"><span>نام و نام خانوادگی</span>
        <input name="full_name" value="<?= e($form['full_name']) ?>" maxlength="80" placeholder="مثلاً سارا محمدی"></label>
      <label class="field"><span>نام کاربری <small>(برای ورود، حروف انگلیسی)</small></span>
        <input name="username" value="<?= e($form['username']) ?>" dir="ltr" required maxlength="32"
               pattern="[A-Za-z0-9_.\-]{3,32}" autocomplete="off"></label>
    </div>
    <div class="fgrid">
      <label class="field"><span><?= $u ? 'رمز جدید' : 'رمز عبور' ?> <small><?= $u ? '(خالی = بدون تغییر)' : '(حداقل ۸ کاراکتر)' ?></small></span>
        <input type="password" name="password" dir="ltr" minlength="8" autocomplete="new-password"<?= $u ? '' : ' required' ?>></label>
      <label class="field"><span>تکرار رمز</span>
        <input type="password" name="password2" dir="ltr" minlength="8" autocomplete="new-password"<?= $u ? '' : ' required' ?>></label>
    </div>
    <?php if ($u): ?><small class="muted">با تغییر رمز، این کاربر از همهٔ دستگاه‌ها خارج می‌شود.</small><?php endif; ?>

    <h2 class="mt">دسترسی</h2>
    <label class="chk"><input type="checkbox" name="is_admin" value="1" data-admin-toggle<?= $form['is_admin'] ? ' checked' : '' ?><?= $self ? ' disabled' : '' ?>>
      مدیر: همهٔ بخش‌ها، به‌علاوهٔ تنظیمات، پشتیبان، کاربران و گزارش فعالیت</label>
    <?php if ($self): ?><small class="muted">نقش مدیر را برای حساب خودتان نمی‌توانید بردارید.</small><?php endif; ?>

    <fieldset class="perm-box" data-perms<?= $form['is_admin'] ? ' disabled' : '' ?>>
      <div class="preset-row">
        <span class="muted small">آماده:</span>
        <?php foreach (PERM_PRESETS as $key => [$label, $set]): ?>
          <button type="button" class="btn btn-ghost btn-sm" data-preset="<?= e(json_encode($set)) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach (PERM_AREAS as $area => [$label, $hint, $levels]): ?>
        <div class="perm-row">
          <div class="perm-k"><b><?= e($label) ?></b><small><?= e($hint) ?></small></div>
          <div class="choice-group" role="radiogroup" aria-label="<?= e($label) ?>">
            <?php foreach ($levels as $lv => $lvLabel): ?>
              <label class="choice"><input type="radio" name="perm_<?= e($area) ?>" value="<?= (int) $lv ?>"<?= (int) $form['perms'][$area] === $lv ? ' checked' : '' ?>><span><?= e($lvLabel) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <div class="actions"><button class="btn btn-primary"><?= $u ? 'ذخیره' : 'ساخت حساب' ?></button></div>
  </form>

  <?php if ($u): ?>
    <section class="section">
      <div class="section-head">
        <h2>آخرین کارها</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('activity', ['user' => $id])) ?>">همه</a>
      </div>
      <?php if (!$recent): ?>
        <div class="card"><?= empty_state('clock-counter-clockwise', 'هنوز کاری ثبت نکرده.') ?></div>
      <?php else: ?>
        <div class="feed"><?php foreach ($recent as $a) { echo activity_item($a, false); } ?></div>
      <?php endif; ?>
    </section>

    <?php if (!$self): ?>
      <div class="danger-zone">
        <form method="post" data-confirm="<?= $u['active'] ? 'حساب ' . e(user_label($u)) . ' غیرفعال شود؟ فوراً از برنامه خارج می‌شود.' : 'حساب دوباره فعال شود؟' ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
          <button class="btn btn-ghost btn-sm"><?= icon($u['active'] ? 'prohibit' : 'check-circle') ?> <?= $u['active'] ? 'غیرفعال کردن' : 'فعال کردن' ?></button>
        </form>
        <form method="post" data-confirm="حساب <?= e(user_label($u)) ?> برای همیشه حذف شود؟ (بهتر است فقط غیرفعالش کنید)">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <button class="btn btn-danger btn-sm">حذف حساب</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php
layout_end();
