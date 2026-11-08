<?php $pct = $expected > 0 ? round(($present / $expected) * 100) : 0; ?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Arrivals</p>
    <h1>Check-in mode</h1>
    <p class="sub">Fast search, one-tap check-in and a logged correction trail.</p>
  </div>
  <div class="bulk-actions">
    <span class="badge badge-gold" style="font-size:12px;padding:7px 13px"><?= number_format($present) ?> / <?= number_format($expected) ?> checked in</span>
  </div>
</div>

<div class="progress" style="margin-bottom:16px;height:10px"><span style="width:<?= (int) $pct ?>%"></span></div>

<section class="admin-search-hero">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
    <div><h2>Find a delegate</h2><p class="small" style="color:var(--sage);margin-top:3px">Search by name, phone or registration ID. Payment must be complete before check-in.</p></div>
    <span class="badge badge-gold"><?= $pct ?>% arrived</span>
  </div>
  <form method="get" action="<?= e(url('admin/check-in')) ?>">
    <input type="hidden" name="r" value="admin/check-in">
    <input class="big-search" type="search" name="q" value="<?= e($query) ?>" placeholder="Search name, phone or registration ID" autofocus>
    <button class="btn btn-gold btn-block" type="submit" style="margin-top:10px">Search</button>
  </form>
</section>

<?php if ($query !== '' && !$results): ?>
  <div class="panel"><p class="empty">No matches for “<?= e($query) ?>”. Check the spelling or search by registration ID.</p></div>
<?php endif; ?>

<?php foreach ($results as $r): ?>
  <?php $presentAlready = ($r['attendance_state'] ?? 'NOT_ARRIVED') === 'PRESENT'; ?>
  <div class="checkin-card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
      <div>
        <h3><?= e(delegate_name($r)) ?></h3>
        <p class="small" style="color:var(--sage)"><?= e($r['registration_id']) ?> · <?= e($r['class_name'] ?? 'No class') ?> · <?= e($r['house_name'] ?? 'No house') ?></p>
      </div>
      <span class="<?= e(badge_class($r['payment_status'])) ?>"><?= e(payment_status_label($r['payment_status'])) ?></span>
    </div>
    <div class="checkin-meta">
      <div><p class="cm-label">Gender</p><p class="cm-value"><?= e($r['gender'] === 'MALE' ? 'Brother' : 'Sister') ?></p></div>
      <div><p class="cm-label">Attendance</p><p class="cm-value"><?= $presentAlready ? 'Present · ' . e(nice_date($r['check_in_time'], 'g:ia')) : 'Not arrived' ?></p></div>
    </div>
    <?php if ($presentAlready): ?>
      <p class="notice notice-green">✓ Checked in at <?= e(nice_datetime($r['check_in_time'])) ?>.</p>
      <?php if (admin_can(['ATTENDANCE_ADMIN'])): ?>
        <details style="margin-top:8px">
          <summary class="small" style="color:var(--gold);font-weight:800;cursor:pointer">Reverse check-in</summary>
          <form method="post" action="<?= e(url('admin/check-in/action')) ?>" style="margin-top:7px" data-confirm="Reverse this check-in? The reason is saved in the audit trail.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="undo">
            <input type="hidden" name="participant_id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="q" value="<?= e($query) ?>">
            <input class="input" name="reason" placeholder="Reason for correction (required)" required style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff;font-size:12px;padding:9px 11px">
            <button class="btn btn-sm btn-block" type="submit" style="margin-top:7px;background:rgba(255,255,255,.12);color:#fff">Reverse check-in</button>
          </form>
        </details>
      <?php endif; ?>
    <?php elseif ((int) ($r['profile_completed'] ?? 0) !== 1): ?>
      <p class="notice notice-red">Delegate details incomplete — cannot be checked in yet.</p>
    <?php elseif ($r['payment_status'] !== 'FULLY_PAID'): ?>
      <p class="notice notice-red">Payment not completed — cannot be checked in.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin/check-in/action')) ?>" style="margin-top:10px" data-confirm="Check in <?= e(delegate_name($r)) ?>?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="check_in">
        <input type="hidden" name="participant_id" value="<?= (int) $r['id'] ?>">
        <input type="hidden" name="q" value="<?= e($query) ?>">
        <button class="btn btn-gold btn-block" type="submit">CHECK IN</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="admin-grid admin-grid-2">
  <section class="panel">
    <h2>Recent arrivals</h2>
    <?php if (!$recent): ?><p class="empty">No one has checked in yet.</p><?php else: ?>
      <?php foreach ($recent as $r): ?>
        <div class="kv"><span><?= e(delegate_name($r)) ?> <span class="mono small">(<?= e($r['registration_id']) ?>)</span></span><span><?= e(nice_date($r['check_in_time'], 'g:ia')) ?></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>At a glance</h2>
    <div class="kv"><span>Expected at camp</span><span><?= number_format($expected) ?></span></div>
    <div class="kv"><span>Present</span><span style="color:#059669"><?= number_format($present) ?></span></div>
    <div class="kv"><span>Not yet arrived</span><span style="color:#d97706"><?= number_format(max(0, $expected - $present)) ?></span></div>
    <div class="kv"><span>Attendance</span><span><?= $pct ?>%</span></div>
  </section>
</div>
