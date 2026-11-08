<?php
$success = flash('success');
$error = flash('error');
$info = flash('info');
$admin = current_admin();
$current = $_GET['r'] ?? '';
if ($current === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $uri = str_replace(base_path(), '', $uri);
    $current = trim(str_replace('index.php', '', $uri), '/');
}
if ($current === '') {
    $current = 'admin/dashboard';
}
$isActive = static fn(string $route): bool => $current === $route || str_starts_with($current, $route . '/');
$nav = [
    ['admin/dashboard', 'Dashboard', '▦'],
    ['admin/participants', 'Participants', '☷'],
    ['admin/payments', 'Payments', '₦'],
    ['admin/bulk', 'Bulk orders', '⧉'],
    ['admin/check-in', 'Check-in mode', '✓'],
    ['admin/houses', 'Houses', '⌂'],
    ['admin/reports', 'Reports', '⇩'],
    ['admin/newsletter', 'Newsletter', '✉'],
    ['admin/users', 'Admin users', '⚙'],
    ['admin/settings', 'Settings', '☰'],
    ['admin/audit', 'Audit log', '≣'],
    ['admin/database', 'Database', '⛁'],
];
$visible = static function (string $route) use ($admin): bool {
    if (!$admin) return false;
    if ($admin['role'] === 'SUPER_ADMIN') return true;
    return match ($route) {
        'admin/users', 'admin/settings', 'admin/audit' => false,
        'admin/check-in' => in_array($admin['role'], ['ATTENDANCE_ADMIN', 'REGISTRATION_ADMIN', 'VIEWER'], true),
        'admin/payments' => in_array($admin['role'], ['FINANCE_ADMIN', 'VIEWER', 'REGISTRATION_ADMIN'], true),
        'admin/bulk' => in_array($admin['role'], ['FINANCE_ADMIN', 'VIEWER', 'REGISTRATION_ADMIN'], true),
        'admin/houses' => in_array($admin['role'], ['HOUSE_ADMIN', 'REGISTRATION_ADMIN', 'VIEWER'], true),
        'admin/newsletter' => in_array($admin['role'], ['REGISTRATION_ADMIN', 'VIEWER'], true),
        'admin/database' => false,
        default => true,
    };
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Admin') . ' — SB Osun Admin') ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='30' fill='%230F3D2E'/%3E%3Ctext x='32' y='40' font-family='Arial' font-size='22' font-weight='900' fill='%23E8B923' text-anchor='middle'%3ESB%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar" aria-label="Admin navigation">
    <a class="admin-brand" href="<?= e(url('admin/dashboard')) ?>">
      <span class="brand-mark" style="width:32px;height:32px;font-size:10px">SB</span>
      <span>SB HIC Admin<small style="display:block;font-size:9px;color:var(--sage);font-weight:500">Osun State · <?= e((string) setting('year', date('Y'))) ?></small></span>
    </a>
    <nav class="admin-nav">
      <?php foreach ($nav as [$route, $label, $icon]): ?>
        <?php if ($visible($route)): ?>
          <a href="<?= e(url($route)) ?>" class="<?= $isActive($route) ? 'active' : '' ?>">
            <span class="nav-icon"><?= e($icon) ?></span><span><?= e($label) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php if ($admin): ?>
    <div class="admin-user">
      <strong><?= e($admin['name']) ?></strong>
      <?= e(ucwords(strtolower(str_replace('_', ' ', $admin['role'])))) ?><?= $admin['zone'] ? ' · ' . e($admin['zone']) : '' ?>
      <form method="post" action="<?= e(url('admin/logout')) ?>" style="margin-top:10px">
        <?= csrf_field() ?>
        <button type="submit" style="background:none;border:0;color:var(--gold);text-decoration:underline;font-size:11px;font-weight:700;cursor:pointer;padding:0">Log out</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex;align-items:center;gap:10px">
        <button class="admin-mobile-toggle" type="button" data-admin-toggle aria-label="Toggle sidebar">☰</button>
        <span class="at-title">SB HIC <?= e((string) setting('year', '2026')) ?> · Camp Management</span>
      </div>
      <div class="at-actions">
        <a class="btn btn-outline-green btn-sm" href="<?= e(url('home')) ?>" target="_blank" rel="noopener">View site ↗</a>
        <?php if (in_array((string) setting('maintenance_mode', '0'), ['1'], true)): ?><span class="badge badge-amber">Maintenance on</span><?php endif; ?>
      </div>
    </div>

    <?php if ($success || $error || $info): ?>
      <div style="padding:14px 20px 0">
        <?php if ($success): ?><div class="flash flash-success">✓ <span><?= e($success) ?></span></div><?php endif; ?>
        <?php if ($error): ?><div class="flash flash-error">! <span><?= e($error) ?></span></div><?php endif; ?>
        <?php if ($info): ?><div class="flash flash-info">i <span><?= e($info) ?></span></div><?php endif; ?>
      </div>
    <?php endif; ?>

    <main class="admin-content">
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
