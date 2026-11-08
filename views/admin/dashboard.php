<?php
$cards = [
    ['Total registered', number_format($stats['total']), '', 'All active records'],
    ['Fully paid', number_format($stats['fully']), 'text-green', 'Ready for check-in'],
    ['Partially paid', number_format($stats['partial']), 'text-amber', 'Instalment delegates'],
    ['Payment pending', number_format($stats['not_paid']), 'text-red', 'No verified payment yet'],
    ['Expected at camp', number_format($stats['expected']), '', 'Active delegates'],
    ['Present', number_format($stats['present']), 'text-green', 'Checked in'],
    ['Not yet arrived', number_format($stats['not_arrived']), 'text-amber', 'Expected arrivals'],
    ['Attendance', $stats['attendance_pct'] . '%', '', 'Present / expected'],
];
$maxZone = 1;
foreach ($byZone as $z) { $maxZone = max($maxZone, (int) $z['total']); }
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Overview · <?= e((string) setting('name', 'SB HIC 2026')) ?></p>
    <h1>Camp Dashboard</h1>
    <p class="sub">Live figures for <?= e((string) setting('year', '2026')) ?> · <?= e(camp_dates()) ?> · <?= e((string) setting('venue_name')) ?></p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-green btn-sm" href="<?= e(url('admin/check-in')) ?>">Open check-in mode</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/participants')) ?>">View participants</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/reports')) ?>">Export reports</a>
  </div>
</div>

<div class="stat-grid">
  <?php foreach ($cards as [$label, $value, $accent, $sub]): ?>
    <div class="stat-card">
      <p class="sc-label"><?= e($label) ?></p>
      <p class="sc-value <?= e($accent) ?>"><?= e($value) ?></p>
      <p class="sc-sub"><?= e($sub) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-grid admin-grid-2" style="margin-top:16px">
  <section class="panel">
    <h2>Revenue &amp; payments</h2>
    <div class="kv"><span>Verified money collected</span><span style="color:var(--green);font-size:16px"><?= money($stats['collected']) ?></span></div>
    <div class="kv"><span>Pending transactions</span><span><?= number_format($stats['pending_tx']) ?></span></div>
    <div class="kv"><span>Registrations today</span><span><?= number_format($stats['today']) ?></span></div>
    <div class="kv"><span>Early-bird deadline</span><span><?= e(nice_datetime((string) setting('early_bird_deadline'))) ?></span></div>
    <div class="kv"><span>Pricing phase</span><span><span class="badge badge-<?= phase() === 'EARLY_BIRD' ? 'gold' : 'slate' ?>"><?= e(phase_label()) ?> · <?= money(current_fee()) ?></span></span></div>
    <div class="kv"><span>Payment mode</span><span><span class="badge badge-slate"><?= e(payment_mode()) ?></span></span></div>
  </section>
  <section class="panel">
    <h2>Registrations by zone</h2>
    <?php if (!$byZone): ?><p class="empty">No registrations yet.</p><?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
        <?php foreach (array_slice($byZone, 0, 8) as $z): ?>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:11.5px;font-weight:700"><span><?= e($z['zone']) ?></span><span><?= (int) $z['total'] ?></span></div>
            <div class="progress" style="margin-top:4px;height:6px"><span style="width:<?= (int) round(((int) $z['total'] / $maxZone) * 100) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="admin-grid admin-grid-3" style="margin-top:16px">
  <section class="panel">
    <h2>Category split</h2>
    <?php if (!$byCategory): ?><p class="empty">No data.</p><?php else: foreach ($byCategory as $c): ?>
      <div class="kv"><span><?= e($c['category']) ?></span><span><?= (int) $c['total'] ?></span></div>
    <?php endforeach; endif; ?>
  </section>
  <section class="panel">
    <h2>Gender split</h2>
    <?php foreach ($byGender as $g): ?>
      <div class="kv"><span><?= e($g['gender'] === 'MALE' ? 'Brothers' : 'Sisters') ?></span><span><?= (int) $g['total'] ?></span></div>
    <?php endforeach; ?>
    <?php if (!$byGender): ?><p class="empty">No data.</p><?php endif; ?>
    <h2 style="margin-top:16px">House occupancy</h2>
    <?php foreach ($houses as $h): $pct = (int) $h['capacity'] > 0 ? round(((int) $h['occupants'] / (int) $h['capacity']) * 100) : 0; ?>
      <div style="margin-bottom:8px">
        <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:700"><span><?= e($h['name']) ?></span><span><?= (int) $h['occupants'] ?>/<?= (int) $h['capacity'] ?></span></div>
        <div class="progress" style="height:5px;margin-top:3px"><span style="width:<?= $pct ?>%"></span></div>
      </div>
    <?php endforeach; ?>
  </section>
  <section class="panel">
    <h2>Pending payments</h2>
    <?php if (!$pending): ?><p class="empty">Nothing pending.</p><?php else: foreach ($pending as $p): ?>
      <div style="border-bottom:1px solid #f1f5f0;padding:7px 0">
        <div style="display:flex;justify-content:space-between;gap:8px;font-size:11.5px;font-weight:800"><span><?= e($p['other_name'] . ' ' . $p['surname']) ?></span><span><?= money((int) $p['amount']) ?></span></div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted)"><span><?= e($p['registration_id']) ?> · <?= e($p['payment_type'] === 'FULL' ? 'Full' : 'Instalment ' . $p['installment_number']) ?></span><span><?= e(nice_date($p['created_at'], 'j M')) ?></span></div>
      </div>
    <?php endforeach; ?><a class="btn btn-outline-green btn-sm" style="margin-top:10px" href="<?= e(url('admin/payments', ['status' => 'PENDING'])) ?>">Review payments</a><?php endif; ?>
  </section>
</div>

<section class="panel" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
    <h2 style="margin:0">Latest registrations</h2>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/participants')) ?>">View all</a>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="table">
      <thead><tr><th>Delegate</th><th>Category</th><th>Zone</th><th>Class</th><th>House</th><th>Payment</th><th>Registered</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="7" class="empty">No registrations yet. Share the camp registration page to get started.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr data-row-href="<?= e(url('admin/participants/' . $r['id'])) ?>">
          <td><div class="name"><?= e($r['other_name'] . ' ' . $r['surname']) ?></div><div class="sub mono"><?= e($r['registration_id']) ?></div></td>
          <td><?= e($r['category']) ?></td>
          <td><?= e($r['zone']) ?></td>
          <td><?= e($r['class_name'] ?? '—') ?></td>
          <td><?= e($r['house_name'] ?? '—') ?></td>
          <td><span class="<?= e(badge_class($r['payment_status'])) ?>"><?= e(payment_status_label($r['payment_status'])) ?></span></td>
          <td class="small muted"><?= e(nice_date($r['created_at'], 'j M, g:ia')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
