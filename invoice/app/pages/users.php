<?php
/** Accounts: one card per person, with their access and last sign-in. Admins only. */
defined('APP_DIR') || exit;

$list = users_list();
$me = (int) current_user()['id'];

layout_start('کاربران', 'users');
?>
<main class="page">
  <div class="head">
    <h1>کاربران</h1>
    <div class="actions">
      <a class="btn btn-primary" href="<?= e(url('user', ['new' => 1])) ?>"><?= icon('user-plus') ?> کاربر جدید</a>
      <a class="btn btn-ghost" href="<?= e(url('activity')) ?>"><?= icon('clock-counter-clockwise') ?> گزارش فعالیت</a>
    </div>
  </div>

  <p class="muted lead-note">هر نفر با نام کاربری و رمز خودش وارد می‌شود و فقط بخش‌هایی را می‌بیند که به او داده‌اید. اطلاعات شرکت برای همه یکی است.</p>

  <div class="cards">
    <?php foreach ($list as $u): ?>
      <article class="rcard<?= $u['active'] ? '' : ' is-off' ?>">
        <div class="rc-top">
          <div class="rc-head">
            <span class="rc-mono" aria-hidden="true"><?= e(str_cut(user_label($u), 1)) ?></span>
            <div>
              <a class="rc-title stretch" href="<?= e(url('user', ['id' => $u['id']])) ?>"><?= e(user_label($u)) ?></a>
              <div class="rc-sub tel"><?= e($u['username']) ?></div>
            </div>
          </div>
          <?php if (!$u['active']): ?>
            <span class="badge muted">غیرفعال</span>
          <?php elseif ($u['is_admin']): ?>
            <span class="badge info">مدیر</span>
          <?php elseif ((int) $u['id'] === $me): ?>
            <span class="badge ok">شما</span>
          <?php endif; ?>
        </div>
        <div class="rc-meta"><span><?= icon('shield-check') ?><?= e(perms_summary($u)) ?></span></div>
        <div class="rc-foot">
          <span class="rc-meta"><span><?= icon('clock-counter-clockwise') ?><?= $u['last_login'] ? 'آخرین ورود ' . jstamp($u['last_login']) : 'هنوز وارد نشده' ?></span></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</main>
<?php
layout_end();
