<?php
$fullName = trim($participant['other_name'] . ' ' . $participant['surname']);
$balance = max(0, $owed - $paid);
?>
<main class="section" style="padding-top:34px">
  <div class="container container-narrow">
    <a class="admin-back" href="<?= e(url('status')) ?>">← Search again</a>
    <div class="card" style="margin-top:12px;border-radius:24px;padding:26px">
      <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px">
        <div>
          <p class="eyebrow">Registration found</p>
          <h1 style="font-size:28px;margin-top:3px"><?= e($fullName) ?></h1>
          <p class="small muted"><?= e($participant['registration_id']) ?> · <?= e($participant['zone']) ?> · <?= e($participant['category']) ?></p>
        </div>
        <span class="<?= e(badge_class($participant['payment_status'])) ?>"><?= e(payment_status_label($participant['payment_status'])) ?></span>
      </div>

      <div class="info-grid" style="margin-top:18px">
        <div class="info-tile"><p class="it-label">Class</p><p class="it-value"><?= e($class['name'] ?? '—') ?></p></div>
        <div class="info-tile"><p class="it-label">House</p><p class="it-value"><?= e($house['name'] ?? 'Pending') ?></p></div>
        <div class="info-tile"><p class="it-label">Attendance</p><p class="it-value"><?= e(($attendance['status'] ?? 'NOT_ARRIVED') === 'PRESENT' ? 'Checked in' : 'Not yet arrived') ?></p></div>
        <div class="info-tile"><p class="it-label">Balance</p><p class="it-value"><?= money($balance) ?></p></div>
      </div>

      <div class="detail-list">
        <div class="dl-row"><span>Total owed</span><span><?= money($owed) ?></span></div>
        <div class="dl-row"><span>Paid so far</span><span><?= money($paid) ?></span></div>
        <div class="dl-row"><span>Camp dates</span><span><?= e(camp_dates()) ?></span></div>
        <div class="dl-row"><span>Venue</span><span><?= e((string) setting('venue_name')) ?>, <?= e((string) setting('venue_address')) ?></span></div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px">
        <a class="btn btn-green" href="<?= e(url('portal/' . $participant['token'])) ?>">Open full portal &amp; pay</a>
        <a class="btn btn-outline-green" href="<?= e(url('id-card/' . $participant['token'])) ?>">View ID card</a>
        <a class="btn btn-outline-green" href="<?= e(url('event.ics')) ?>">Add to calendar</a>
      </div>
      <p class="notice notice-amber" style="margin-top:16px">For your privacy this page never shows your phone, email, next-of-kin or welfare notes. Those are available only in the personal portal link and the protected admin area.</p>
    </div>
  </div>
</main>
