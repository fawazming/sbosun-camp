<div class="admin-page-head">
  <div>
    <p class="eyebrow">Access control</p>
    <h1>Admin users</h1>
    <p class="sub">Super Admins can add staff and control roles. Every action is audit-logged.</p>
  </div>
  <details>
    <summary class="btn btn-green btn-sm" style="list-style:none;cursor:pointer">+ Add admin</summary>
    <form method="post" action="<?= e(url('admin/users/action')) ?>" class="panel" style="position:absolute;right:28px;z-index:20;margin-top:8px;width:min(440px,90vw);box-shadow:var(--shadow-lg)">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="field span-6"><label>Name</label><input class="input" name="name" required></div>
        <div class="field span-6"><label>Email</label><input class="input" type="email" name="email" required></div>
        <div class="field span-6"><label>Temporary password (min. 8)</label><input class="input" type="password" name="password" minlength="8" required></div>
        <div class="field span-6"><label>Role</label><select class="select" name="role"><?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'VIEWER' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="field span-12"><label>Zone (optional — leave blank for org-wide)</label><input class="input" name="zone" placeholder="e.g. Ile-Ife"></div>
        <div class="span-12"><button class="btn btn-gold btn-sm btn-block" type="submit">Create admin</button></div>
      </div>
    </form>
  </details>
</div>

<div class="table-wrap">
  <table class="table" style="min-width:720px">
    <thead><tr><th>Name</th><th>Role</th><th>Zone</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><div class="name"><?= e($u['name']) ?> <?= (int) $u['is_protected'] ? '<span class="badge badge-gold">protected</span>' : '' ?></div><div class="sub"><?= e($u['email']) ?></div></td>
          <td class="small"><?= e(ucwords(strtolower(str_replace('_', ' ', $u['role'])))) ?></td>
          <td class="small"><?= e($u['zone'] ?: 'Org-wide') ?></td>
          <td><span class="<?= e($u['status'] === 'ACTIVE' ? 'badge badge-green' : 'badge badge-slate') ?>"><?= e($u['status']) ?></span></td>
          <td class="small muted"><?= e($u['last_login_at'] ? nice_date($u['last_login_at'], 'j M, g:ia') : '—') ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php if (!(int) $u['is_protected'] && (int) $u['id'] !== (int) $admin['id']): ?>
                <form method="post" action="<?= e(url('admin/users/action')) ?>" data-confirm="Change this admin's access status?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-sm <?= $u['status'] === 'ACTIVE' ? 'btn-outline-green' : 'btn-green' ?>" type="submit"><?= $u['status'] === 'ACTIVE' ? 'Deactivate' : 'Reactivate' ?></button>
                </form>
              <?php endif; ?>
              <details>
                <summary class="small" style="color:var(--green);font-weight:800;cursor:pointer;padding-top:5px">Reset password</summary>
                <form method="post" action="<?= e(url('admin/users/action')) ?>" style="margin-top:5px;display:flex;gap:5px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input class="input" type="password" name="password" minlength="8" placeholder="New password" required style="font-size:10.5px;padding:6px 8px;width:130px">
                  <button class="btn btn-green btn-sm" type="submit">Save</button>
                </form>
              </details>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Role permissions</h2>
  <div class="admin-grid admin-grid-3">
    <div class="kv"><span>Super Admin</span><span>Everything</span></div>
    <div class="kv"><span>Registration Admin</span><span>Delegates, newsletter</span></div>
    <div class="kv"><span>Finance Admin</span><span>Payments &amp; exports</span></div>
    <div class="kv"><span>Attendance Admin</span><span>Check-in desk</span></div>
    <div class="kv"><span>House Admin</span><span>House allocation</span></div>
    <div class="kv"><span>Zone Admin</span><span>Their zone only</span></div>
    <div class="kv"><span>Viewer</span><span>Read-only access</span></div>
  </div>
</section>
