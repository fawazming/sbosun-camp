<?php
$query = $_GET;
unset($query['r'], $query['page']);
$exportUrl = url('admin/participants/export', $query);
$paymentStatuses = ['NOT_PAID', 'PARTIALLY_PAID', 'FULLY_PAID', 'REFUNDED'];
$attendanceStatuses = ['NOT_ARRIVED', 'PRESENT'];
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">People</p>
    <h1>Participants</h1>
    <p class="sub"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?> match the current filters.</p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-green btn-sm" href="<?= e($exportUrl) ?>">Export filtered CSV</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/check-in')) ?>">Check-in mode</a>
  </div>
</div>

<form class="filters" method="get" action="<?= e(url('admin/participants')) ?>">
  <input type="hidden" name="r" value="admin/participants">
  <input class="input search" type="search" name="q" value="<?= e((string) request('q', '')) ?>" placeholder="Search name, registration ID, email or phone…">
  <select class="select" name="gender">
    <option value="">All genders</option>
    <option value="MALE" <?= request('gender') === 'MALE' ? 'selected' : '' ?>>Male</option>
    <option value="FEMALE" <?= request('gender') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
  </select>
  <select class="select" name="category">
    <option value="">All categories</option>
    <?php foreach (categories() as $cat): ?><option value="<?= e($cat) ?>" <?= request('category') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?>
  </select>
  <select class="select" name="payment_status">
    <option value="">All payments</option>
    <?php foreach ($paymentStatuses as $s): ?><option value="<?= e($s) ?>" <?= request('payment_status') === $s ? 'selected' : '' ?>><?= e(payment_status_label($s)) ?></option><?php endforeach; ?>
  </select>
  <select class="select" name="attendance_status">
    <option value="">All attendance</option>
    <?php foreach ($attendanceStatuses as $s): ?><option value="<?= e($s) ?>" <?= request('attendance_status') === $s ? 'selected' : '' ?>><?= e(ucwords(strtolower(str_replace('_', ' ', $s)))) ?></option><?php endforeach; ?>
  </select>
  <select class="select" name="house_id">
    <option value="">All houses</option>
    <?php foreach ($houses as $h): ?><option value="<?= (int) $h['id'] ?>" <?= (string) request('house_id') === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
  </select>
  <input class="input" type="text" name="zone" value="<?= e((string) request('zone', '')) ?>" placeholder="Zone" style="width:110px">
  <input class="input" type="date" name="from" value="<?= e((string) request('from', '')) ?>" title="Registered from">
  <input class="input" type="date" name="to" value="<?= e((string) request('to', '')) ?>" title="Registered to">
  <button class="btn btn-green btn-sm" type="submit">Filter</button>
  <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/participants')) ?>">Reset</a>
</form>

<div class="table-wrap">
  <?php if (!$rows): ?>
    <p class="empty">No participants match these filters. <a href="<?= e(url('admin/participants')) ?>" style="color:var(--green);font-weight:800">Clear filters</a></p>
  <?php else: ?>
    <table class="table" style="min-width:1100px">
      <thead>
        <tr>
          <th>Reg. ID</th><th>Name</th><th>Gender</th><th>Age</th><th>Category</th><th>Class</th><th>Zone</th><th>Payment</th><th>House</th><th>Attendance</th><th>Registered</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $p): ?>
          <tr data-row-href="<?= e(url('admin/participants/' . $p['id'])) ?>">
            <td class="mono small"><?= e($p['registration_id']) ?></td>
            <td><div class="name"><?= e($p['other_name'] . ' ' . $p['surname']) ?></div><div class="sub"><?= e($p['phone']) ?></div></td>
            <td><?= e($p['gender'] === 'MALE' ? 'Male' : 'Female') ?></td>
            <td class="small"><?= e($p['age_group']) ?></td>
            <td class="small"><?= e($p['category']) ?></td>
            <td class="small"><?= e($p['class_name'] ?? '—') ?></td>
            <td class="small"><?= e($p['zone']) ?></td>
            <td><span class="<?= e(badge_class($p['payment_status'])) ?>"><?= e(payment_status_label($p['payment_status'])) ?></span></td>
            <td class="small"><?= e($p['house_name'] ?? '—') ?></td>
            <td><span class="<?= e(badge_class($p['attendance_status'])) ?>"><?= e($p['attendance_status'] === 'PRESENT' ? 'Present' : 'Not arrived') ?></span></td>
            <td class="small muted"><?= e(nice_date($p['created_at'], 'j M Y')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <div class="pagination">
    <?php
    $mk = static function (int $page) use ($query): string {
        return url('admin/participants', array_merge($query, ['page' => $page]));
    };
    ?>
    <?php if ($page > 1): ?><a href="<?= e($mk($page - 1)) ?>">← Prev</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= e($mk($i)) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e($mk($page + 1)) ?>">Next →</a><?php endif; ?>
    <span style="border:0;background:none;color:var(--muted)">Page <?= $page ?> of <?= $pages ?></span>
  </div>
<?php endif; ?>
