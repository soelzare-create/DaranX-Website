<?php
/** Who did what: every sign-in and change, newest first, filterable by person. Admins only. */
defined('APP_DIR') || exit;

const ACTIVITY_PAGE = 100;

$userId = (int) query('user');
$q = clean_line(query('q'), 100);
$page = max(1, (int) query('page', '1'));
$rows = activity_list($userId, $q, ACTIVITY_PAGE + 1, ($page - 1) * ACTIVITY_PAGE);
$more = count($rows) > ACTIVITY_PAGE;
$rows = array_slice($rows, 0, ACTIVITY_PAGE);
$users = users_list();

layout_start('گزارش فعالیت', 'users');
?>
<main class="page narrow">
  <div class="head">
    <h1>گزارش فعالیت</h1>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('users')) ?>">→ کاربران</a></div>
  </div>

  <section>
    <div class="list-tools">
      <form class="search" method="get">
        <input type="hidden" name="p" value="activity">
        <select class="input" name="user" aria-label="کاربر" data-autosubmit>
          <option value="">همهٔ کاربران</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === $userId ? ' selected' : '' ?>><?= e(user_label($u)) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در کار یا جزئیات…" aria-label="جستجو">
        <button class="btn btn-ghost">جستجو</button>
      </form>
    </div>
    <?php if (!$rows): ?>
      <div class="card"><?= empty_state('clock-counter-clockwise', $q !== '' || $userId ? 'موردی پیدا نشد.' : 'هنوز فعالیتی ثبت نشده.') ?></div>
    <?php else: ?>
      <div class="feed"><?php foreach ($rows as $a) { echo activity_item($a, !$userId); } ?></div>
      <?php if ($page > 1 || $more): ?>
        <div class="actions mt">
          <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('activity', array_filter(['user' => $userId ?: null, 'q' => $q ?: null, 'page' => $page - 1]))) ?>">جدیدترها</a><?php endif; ?>
          <?php if ($more): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('activity', array_filter(['user' => $userId ?: null, 'q' => $q ?: null, 'page' => $page + 1]))) ?>">قدیمی‌ترها</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<?php
layout_end();
