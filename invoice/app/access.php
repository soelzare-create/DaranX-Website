<?php
/**
 * User accounts, permissions and the activity log.
 *
 * Every account sees the same company data; what it may do is set per area:
 *   sales     (proformas, invoices, customers, receipts, Excel import): none / view / edit
 *   costs     (purchases and payments out, an invoice's cost and profit): none / view / edit
 *   reports   (financial reports in the assistant):                     none / view
 *   assistant (the chat assistant; its tools still follow the rest):     none / use
 * An admin can do everything, plus settings, backup, users and the activity log.
 * index.php enforces this per route; pages only hide what a user cannot use.
 */
defined('APP_DIR') || exit;

const PERM_AREAS = [
    'sales' => ['فروش', 'پیش‌فاکتور، فاکتور، مشتریان، دریافت‌ها و ورود از اکسل', ['بدون دسترسی', 'مشاهده', 'مشاهده و ثبت']],
    'costs' => ['خرید و هزینه', 'خریدها، پرداخت‌ها و سود هر فاکتور', ['بدون دسترسی', 'مشاهده', 'مشاهده و ثبت']],
    'reports' => ['گزارش‌های مالی', 'سود و زیان، بدهکاران و سایر گزارش‌های دستیار', ['بدون دسترسی', 'مشاهده']],
    'assistant' => ['دستیار', 'کار با چت؛ هر کاری فقط در حد دسترسی‌های بالا', ['بدون دسترسی', 'استفاده']],
];

const PERM_LEVELS = ['view' => 1, 'use' => 1, 'edit' => 2];

/** Ready-made sets for the user form. */
const PERM_PRESETS = [
    'accountant' => ['حسابدار', ['sales' => 2, 'costs' => 2, 'reports' => 1, 'assistant' => 1]],
    'sales' => ['کارشناس فروش', ['sales' => 2, 'costs' => 0, 'reports' => 0, 'assistant' => 1]],
    'buyer' => ['کارشناس خرید', ['sales' => 1, 'costs' => 2, 'reports' => 0, 'assistant' => 1]],
    'viewer' => ['فقط مشاهده', ['sales' => 1, 'costs' => 1, 'reports' => 1, 'assistant' => 0]],
];

/** What each route needs: null = any signed-in user, 'admin', or [area, level]. POST to a view route needs edit. */
const ROUTE_PERMS = [
    'logout' => null, 'home' => null, 'settings' => null,
    'docs' => ['sales', 'view'], 'doc' => ['sales', 'view'], 'doc_action' => ['sales', 'edit'],
    'customers' => ['sales', 'view'], 'customer' => ['sales', 'view'],
    'payments' => ['sales', 'view'], 'payment' => ['sales', 'view'],
    'import' => ['sales', 'edit'],
    'purchases' => ['costs', 'view'], 'purchase' => ['costs', 'view'], 'purchase_lines' => ['costs', 'view'],
    'expenses' => ['costs', 'view'], 'expense' => ['costs', 'view'],
    'assistant' => ['assistant', 'use'], 'assistant_api' => ['assistant', 'use'],
    'backup' => 'admin', 'users' => 'admin', 'user' => 'admin', 'activity' => 'admin',
];

/** Routes whose POST is not a data write of their area (checked inside the page). */
const ROUTE_POST_SELF_CHECKED = ['logout', 'settings', 'assistant_api'];

function perms_decode(string $json): array
{
    $p = json_decode($json, true);
    $out = [];
    foreach (PERM_AREAS as $area => [, , $levels]) {
        $v = is_array($p) ? (int) ($p[$area] ?? 0) : 0;
        $out[$area] = max(0, min(count($levels) - 1, $v));
    }
    return $out;
}

function is_admin(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u !== null && !empty($u['is_admin']);
}

/** Can the signed-in user do $level ('view' | 'use' | 'edit') in $area? */
function can(string $area, string $level = 'view'): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (is_admin($u)) {
        return true;
    }
    return (perms_decode((string) $u['perms'])[$area] ?? 0) >= (PERM_LEVELS[$level] ?? 2);
}

function deny(string $msg = 'به این بخش دسترسی ندارید. اگر لازم است، از مدیر بخواهید دسترسی شما را تغییر دهد.'): void
{
    http_response_code(403);
    if (query('p') === 'assistant_api') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'reply' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    layout_start('بدون دسترسی');
    echo '<main class="page narrow"><div class="card">'
        . empty_state('lock', $msg, '<a class="btn btn-ghost" href="' . e(url('home')) . '">داشبورد</a>')
        . '</div></main>';
    layout_end();
    exit;
}

/** Central check, called by index.php before a page runs. */
function route_guard(string $page): void
{
    $need = array_key_exists($page, ROUTE_PERMS) ? ROUTE_PERMS[$page] : 'admin';
    if ($need === null) {
        return;
    }
    if ($need === 'admin') {
        if (!is_admin()) {
            deny();
        }
        return;
    }
    [$area, $level] = $need;
    if (is_post() && !in_array($page, ROUTE_POST_SELF_CHECKED, true)) {
        $level = 'edit';
    }
    if (!can($area, $level)) {
        deny(is_post() ? 'اجازهٔ ثبت یا تغییر در این بخش را ندارید.' : 'به این بخش دسترسی ندارید. اگر لازم است، از مدیر بخواهید دسترسی شما را تغییر دهد.');
    }
}

/** Short Persian summary of a user's access, for cards. */
function perms_summary(array $u): string
{
    if (!empty($u['is_admin'])) {
        return 'مدیر (همهٔ دسترسی‌ها)';
    }
    $p = perms_decode((string) $u['perms']);
    foreach (PERM_PRESETS as [$label, $set]) {
        if ($set === $p) {
            return $label;
        }
    }
    $parts = [];
    foreach (PERM_AREAS as $area => [$label, , $levels]) {
        if ($p[$area] > 0) {
            $parts[] = $label . ($area === 'sales' || $area === 'costs' ? ' (' . ($p[$area] === 2 ? 'ثبت' : 'مشاهده') . ')' : '');
        }
    }
    return $parts ? implode('، ', $parts) : 'بدون دسترسی';
}

// --- Users ------------------------------------------------------------------

function user_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function users_list(): array
{
    return db()->query('SELECT * FROM users ORDER BY is_admin DESC, active DESC, full_name, username')->fetchAll();
}

function active_admins(int $exceptId = 0): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND active = 1 AND id <> ?');
    $st->execute([$exceptId]);
    return (int) $st->fetchColumn();
}

function user_label(array $u): string
{
    return $u['full_name'] !== '' ? $u['full_name'] : $u['username'];
}

function username_check(string $username): string
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $username)) {
        throw new UserError('نام کاربری باید ۳ تا ۳۲ کاراکتر از حروف انگلیسی، عدد یا . _ - باشد.');
    }
    return $username;
}

function password_check(string $pass, string $repeat): string
{
    if (strlen($pass) < 8) {
        throw new UserError('رمز عبور باید حداقل ۸ کاراکتر باشد.');
    }
    if ($pass !== $repeat) {
        throw new UserError('تکرار رمز عبور با خودش یکسان نیست.');
    }
    return $pass;
}

/** Validate the user form. $old = the user being edited (null = new). */
function user_validate(array $in, ?array $old = null): array
{
    $u = [
        'full_name' => clean_line($in['full_name'] ?? '', 80),
        'username' => username_check((string) ($in['username'] ?? '')),
        'is_admin' => !empty($in['is_admin']) ? 1 : 0,
        'perms' => [],
        'password' => null,
    ];
    foreach (PERM_AREAS as $area => [, , $levels]) {
        $u['perms'][$area] = max(0, min(count($levels) - 1, (int) ($in['perm_' . $area] ?? 0)));
    }
    $st = db()->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ?');
    $st->execute([$u['username'], $old ? (int) $old['id'] : 0]);
    if ($st->fetchColumn()) {
        throw new UserError('نام کاربری «' . $u['username'] . '» قبلاً استفاده شده است.');
    }
    $pass = (string) ($in['password'] ?? '');
    if (!$old || $pass !== '') {
        $u['password'] = password_check($pass, (string) ($in['password2'] ?? ''));
    }
    if ($old && !empty($old['is_admin']) && !$u['is_admin'] && active_admins((int) $old['id']) === 0) {
        throw new UserError('این تنها مدیر فعال است؛ اول یک مدیر دیگر تعریف کنید.');
    }
    return $u;
}

function user_save(array $u, ?int $id = null): int
{
    $perms = json_encode($u['perms']);
    if ($id === null) {
        db()->prepare('INSERT INTO users (username, full_name, pass_hash, is_admin, perms, active, created_at)
                       VALUES (?, ?, ?, ?, ?, 1, ?)')
            ->execute([$u['username'], $u['full_name'], password_hash($u['password'], PASSWORD_DEFAULT),
                $u['is_admin'], $perms, now()]);
        $id = (int) db()->lastInsertId();
        activity_log('کاربر جدید', user_label($u) . ' (' . $u['username'] . ')، ' . perms_summary(array_merge($u, ['perms' => $perms])),
            url('user', ['id' => $id]));
        return $id;
    }
    db()->prepare('UPDATE users SET username = ?, full_name = ?, is_admin = ?, perms = ? WHERE id = ?')
        ->execute([$u['username'], $u['full_name'], $u['is_admin'], $perms, $id]);
    if ($u['password'] !== null) {
        user_set_password($id, $u['password'], false);
    }
    activity_log('ویرایش کاربر', user_label($u) . '، ' . perms_summary(array_merge($u, ['perms' => $perms]))
        . ($u['password'] !== null ? '، رمز عبور عوض شد' : ''), url('user', ['id' => $id]));
    return $id;
}

/** New password; ends the user's other sessions. */
function user_set_password(int $id, string $pass, bool $log = true): void
{
    db()->prepare('UPDATE users SET pass_hash = ?, session_ver = session_ver + 1 WHERE id = ?')
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
    if ($id === (int) ($_SESSION['uid'] ?? 0)) {
        $_SESSION['ver'] = (int) user_get($id)['session_ver'];
    }
    if ($log) {
        activity_log('تغییر رمز عبور', user_label(user_get($id)));
    }
}

function user_set_active(int $id, bool $active): void
{
    $u = user_get($id);
    if (!$u) {
        throw new UserError('کاربر پیدا نشد.');
    }
    if (!$active && $id === (int) current_user()['id']) {
        throw new UserError('حساب خودتان را نمی‌توانید غیرفعال کنید.');
    }
    if (!$active && $u['is_admin'] && active_admins($id) === 0) {
        throw new UserError('این تنها مدیر فعال است و غیرفعال نمی‌شود.');
    }
    db()->prepare('UPDATE users SET active = ?, session_ver = session_ver + 1 WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    activity_log($active ? 'فعال کردن کاربر' : 'غیرفعال کردن کاربر', user_label($u), url('user', ['id' => $id]));
}

function user_delete(int $id): void
{
    $u = user_get($id);
    if (!$u) {
        throw new UserError('کاربر پیدا نشد.');
    }
    if ($id === (int) current_user()['id']) {
        throw new UserError('حساب خودتان را نمی‌توانید حذف کنید.');
    }
    if ($u['is_admin'] && active_admins($id) === 0) {
        throw new UserError('این تنها مدیر فعال است و حذف نمی‌شود.');
    }
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    activity_log('حذف کاربر', user_label($u) . ' (' . $u['username'] . ')');
}

// --- Activity log -------------------------------------------------------------

/** Record who did what. Never breaks the action it records. */
function activity_log(string $action, string $detail = '', string $link = ''): void
{
    try {
        $u = current_user();
        $via = $GLOBALS['activity_via'] ?? '';
        db()->prepare('INSERT INTO activity (user_id, user_name, at, action, detail, url) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$u ? (int) $u['id'] : null, $u ? user_label($u) : '', now(),
                $action . ($via !== '' ? ' (' . $via . ')' : ''), str_cut($detail, 300), $link]);
    } catch (Throwable $ex) {
        error_log('activity_log: ' . $ex->getMessage());
    }
}

function activity_list(int $userId = 0, string $q = '', int $limit = 200, int $offset = 0): array
{
    $sql = 'SELECT * FROM activity WHERE 1 = 1';
    $args = [];
    if ($userId) {
        $sql .= ' AND user_id = ?';
        $args[] = $userId;
    }
    if ($q !== '') {
        $sql .= " AND (action LIKE ? ESCAPE '\\' OR detail LIKE ? ESCAPE '\\' OR user_name LIKE ? ESCAPE '\\')";
        $like = '%' . like_escape(fa_norm($q)) . '%';
        array_push($args, $like, $like, '%' . like_escape(en_digits($q)) . '%');
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** «1405/07/13 14:05» from a stored Y-m-d H:i:s timestamp (server time). */
function jstamp(string $at): string
{
    $ts = strtotime($at);
    return $ts ? fa(jtoday($ts) . ' ' . date('H:i', $ts)) : '';
}
