<?php
$fullName = trim($participant['other_name'] . ' ' . $participant['surname']);
$balance = max(0, (int) $participant['amount_owed'] - (int) $participant['amount_paid']);
$isPresent = ($attendance['status'] ?? 'NOT_ARRIVED') === 'PRESENT';
?>
<a class="admin-back" href="<?= e(url('admin/participants')) ?>">← Back to participants</a>
<div class="admin-page-head" style="margin-top:8px">
  <div>
    <p class="eyebrow">Participant record</p>
    <h1><?= e($fullName) ?></h1>
    <p class="sub"><span class="mono"><?= e($participant['registration_id']) ?></span> · <?= e($participant['category']) ?> · <?= e($participant['zone']) ?></p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('id-card/' . $participant['token'])) ?>" target="_blank" rel="noopener">ID card ↗</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('portal/' . $participant['token'])) ?>" target="_blank" rel="noopener">Delegate portal ↗</a>
    <span class="<?= e(badge_class($participant['payment_status'])) ?>"><?= e(payment_status_label($participant['payment_status'])) ?></span>
    <span class="<?= e(badge_class($participant['attendance_status'])) ?>"><?= e($isPresent ? 'Present' : 'Not arrived') ?></span>
  </div>
</div>

<div class="admin-grid admin-grid-3">
  <section class="panel" style="grid-column:span 2">
    <h2>Delegate details</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">
      <div class="kv"><span>Registration ID</span><span class="mono"><?= e($participant['registration_id']) ?></span></div>
      <div class="kv"><span>Gender</span><span><?= e($participant['gender'] === 'MALE' ? 'Brother (Male)' : 'Sister (Female)') ?></span></div>
      <div class="kv"><span>Age bracket</span><span><?= e($participant['age_group']) ?></span></div>
      <div class="kv"><span>Category</span><span><?= e($participant['category']) ?></span></div>
      <div class="kv"><span>Class</span><span><?= e($class['name'] ?? '—') ?></span></div>
      <div class="kv"><span>House</span><span><?= e($house['name'] ?? 'Not assigned') ?></span></div>
      <div class="kv"><span>Zone</span><span><?= e($participant['zone']) ?></span></div>
      <div class="kv"><span>Email</span><span><?= e($participant['email']) ?></span></div>
      <div class="kv"><span>Phone / WhatsApp</span><span><?= e($participant['phone']) ?></span></div>
      <div class="kv"><span>Next of kin</span><span><?= e($participant['nok_name'] ?: '—') ?></span></div>
      <div class="kv"><span>Next of kin phone</span><span><?= e($participant['nok_phone'] ?: '—') ?></span></div>
      <div class="kv"><span>Under 18</span><span><?= $participant['is_minor'] ? 'Yes' : 'No' ?></span></div>
      <?php if ($participant['is_minor']): ?>
        <div class="kv"><span>Guardian</span><span><?= e($participant['guardian_name']) ?></span></div>
        <div class="kv"><span>Guardian contact</span><span><?= e($participant['guardian_contact']) ?></span></div>
      <?php endif; ?>
      <div class="kv"><span>Registered</span><span><?= e(nice_datetime($participant['created_at'])) ?></span></div>
      <div class="kv"><span>Amount owed</span><span><?= money((int) $participant['amount_owed']) ?></span></div>
      <div class="kv"><span>Amount paid</span><span style="color:var(--green)"><?= money((int) $participant['amount_paid']) ?></span></div>
      <div class="kv"><span>Balance</span><span><?= money($balance) ?></span></div>
    </div>

    <div style="margin-top:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px">
      <p class="eyebrow" style="color:#92400e">Protected welfare information</p>
      <p style="font-size:12px;margin-top:5px"><strong>Allergies / existing ailment:</strong> <?= e($participant['allergies'] ?: 'None recorded') ?></p>
      <?php if ($participant['health_challenges']): ?><p style="font-size:12px;margin-top:4px"><strong>Health challenges:</strong> <?= e($participant['health_challenges']) ?></p><?php endif; ?>
      <p class="small" style="color:#92400e;margin-top:7px">Visible only to authorised staff. Never printed on ID cards, badges or public pages.</p>
    </div>
  </section>

  <aside style="display:flex;flex-direction:column;gap:14px">
    <section class="panel">
      <h2>Check-in</h2>
      <?php if ($isPresent): ?>
        <p class="notice notice-green" style="margin-top:0">✓ Checked in <?= e(nice_datetime($attendance['check_in_time'])) ?> by <?= e($attendance['checked_in_by'] ?: 'staff') ?>.</p>
        <?php if ($admin['role'] !== 'VIEWER'): ?>
        <form method="post" action="<?= e(url('admin/participants/' . $participant['id'] . '/action')) ?>" data-confirm="Reverse this check-in? This is logged in the audit trail." style="margin-top:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="undo_check_in">
          <input class="input" name="reason" placeholder="Reason for correction (required)" required style="font-size:12px;padding:9px 11px">
          <button class="btn btn-outline-green btn-sm btn-block" type="submit" style="margin-top:7px">Undo check-in</button>
        </form>
        <?php endif; ?>
      <?php elseif ($participant['payment_status'] !== 'FULLY_PAID'): ?>
        <p class="notice notice-red" style="margin-top:0">Payment is not complete — this delegate cannot be checked in.</p>
      <?php elseif (admin_can(['ATTENDANCE_ADMIN', 'REGISTRATION_ADMIN'])): ?>
        <form method="post" action="<?= e(url('admin/participants/' . $participant['id'] . '/action')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="check_in">
          <button class="btn btn-gold btn-block" type="submit">Check in delegate</button>
        </form>
      <?php endif; ?>
      <?php if ($attendance && $attendance['corrected']): ?><p class="small muted" style="margin-top:8px">Last correction: <?= e($attendance['correction_reason']) ?></p><?php endif; ?>
    </section>

    <section class="panel">
      <h2>House assignment</h2>
      <?php if ($admin['role'] === 'VIEWER'): ?>
        <p class="small muted">Current house: <strong><?= e($house['name'] ?? 'Not assigned') ?></strong></p>
      <?php else: ?>
        <form method="post" action="<?= e(url('admin/participants/' . $participant['id'] . '/action')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="assign_house">
          <select class="select" name="house_id" required style="font-size:12px;padding:9px 11px">
            <option value="">Choose house…</option>
            <?php foreach ($houses as $h): ?>
              <option value="<?= (int) $h['id'] ?>" <?= (int) ($participant['house_id'] ?? 0) === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?> · <?= e($h['gender'] === 'MALE' ? 'Male' : 'Female') ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-green btn-sm btn-block" type="submit" style="margin-top:7px">Save house</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($admin['role'] === 'SUPER_ADMIN' || $admin['role'] === 'REGISTRATION_ADMIN'): ?>
    <section class="panel" style="border-color:#fecaca">
      <h2 style="color:var(--red)">Archive record</h2>
      <p class="small muted">Soft delete hides this record from reports and lists. It is never physically removed.</p>
      <form method="post" action="<?= e(url('admin/participants/' . $participant['id'] . '/action')) ?>" data-confirm="Archive this participant? The record is hidden but retained for audit.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-sm btn-block" type="submit" style="background:#fee2e2;color:var(--red);border:1px solid #fecaca">Archive participant</button>
      </form>
    </section>
    <?php endif; ?>
  </aside>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Payment history</h2>
  <?php if (!$payments): ?>
    <p class="empty">No payment attempts yet.</p>
  <?php else: ?>
    <div class="table-wrap" style="border:0">
      <table class="table" style="min-width:820px">
        <thead><tr><th>Reference</th><th>Type</th><th>Amount</th><th>Plan</th><th>Status</th><th>Provider</th><th>Created</th><th>Paid</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td class="mono small"><?= e($p['reference']) ?><div class="sub"><?= e($p['provider_reference'] ?: '') ?></div></td>
              <td><?= e($p['payment_type'] === 'FULL' ? 'Full' : 'Instalment ' . $p['installment_number'] . '/' . INSTALLMENT_COUNT) ?></td>
              <td style="font-weight:800"><?= money((int) $p['amount']) ?></td>
              <td class="small"><?= e($p['plan_rate']) ?></td>
              <td><span class="<?= e(badge_class($p['status'])) ?>"><?= e($p['status']) ?></span></td>
              <td class="small"><?= e($p['provider']) ?></td>
              <td class="small muted"><?= e(nice_date($p['created_at'], 'j M, g:ia')) ?></td>
              <td class="small muted"><?= e($p['paid_at'] ? nice_date($p['paid_at'], 'j M, g:ia') : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
