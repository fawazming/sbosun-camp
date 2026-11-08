<?php
$fullName = trim($participant['other_name'] . ' ' . $participant['surname']);
$paid = participant_paid((int) $participant['id']);
$owed = participant_owed($participant);
$valid = $paid >= $owed && $owed > 0;
$bars = '';
$seed = crc32($participant['registration_id']);
for ($i = 0; $i < 46; $i++) {
    $width = (($seed >> ($i % 30)) & 1) ? 2.5 : 1;
    $opacity = $i % 3 === 0 ? '.5' : '1';
    $bars .= '<i style="width:' . $width . 'px;opacity:' . $opacity . '"></i>';
}
?>
<main class="id-card-page">
  <div class="container container-narrow" style="text-align:center;margin-bottom:18px" data-no-print>
    <p class="eyebrow">SB HIC <?= e((string) setting('year', '2026')) ?> · Delegate ID card</p>
    <h1 style="font-size:27px;margin-top:4px"><?= e($fullName) ?></h1>
    <p class="small muted"><?= e($participant['registration_id']) ?></p>
    <?php if (!$valid): ?>
      <p class="notice notice-amber" style="max-width:460px;margin:12px auto">This ID card is provisional until full payment is confirmed. It currently shows <?= money($paid) ?> paid of <?= money($owed) ?>.</p>
    <?php endif; ?>
  </div>

  <div class="id-card" id="idCard">
    <div class="id-card-top">
      <div>
        <div class="id-org">Standard Bearers · Osun State</div>
        <div style="font-size:15px;font-weight:900;margin-top:2px">Holiday Islamic Course</div>
      </div>
      <div class="id-year"><?= e((string) setting('year', '2026')) ?></div>
    </div>
    <div class="id-card-body">
      <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start">
        <div>
          <p class="id-name"><?= e($fullName) ?></p>
          <p class="id-cat"><?= e($participant['category']) ?> · <?= e($class['name'] ?? 'Dawah Class') ?></p>
        </div>
        <div style="width:58px;height:58px;border-radius:50%;background:var(--gold);color:var(--dark);display:grid;place-items:center;font-size:19px;font-weight:900;flex:0 0 auto"><?= e(initials($fullName)) ?></div>
      </div>

      <div class="id-card-lines">
        <div class="id-line"><p class="il-label">Registration no.</p><p class="il-value mono"><?= e($participant['registration_id']) ?></p></div>
        <div class="id-line"><p class="il-label">House</p><p class="il-value"><?= e($house['name'] ?? 'Pending') ?></p></div>
        <div class="id-line"><p class="il-label">Zone / Branch</p><p class="il-value"><?= e($participant['zone']) ?></p></div>
        <div class="id-line"><p class="il-label">Gender</p><p class="il-value"><?= e($participant['gender'] === 'MALE' ? 'Brother' : 'Sister') ?></p></div>
        <div class="id-line"><p class="il-label">Camp dates</p><p class="il-value"><?= e(camp_dates()) ?></p></div>
        <div class="id-line"><p class="il-label">Venue</p><p class="il-value"><?= e((string) setting('venue_name')) ?></p></div>
      </div>

      <div style="margin-top:18px;display:flex;justify-content:space-between;align-items:center;gap:10px">
        <span class="status-stamp"><?= $valid ? 'Valid delegate' : 'Provisional' ?></span>
        <span style="font-size:9px;color:var(--sage);text-align:right;max-width:190px"><?= e((string) setting('motto', 'Upholding the standard…')) ?><br><?= e((string) setting('venue_address')) ?></span>
      </div>
    </div>
    <div class="id-card-foot">
      <div class="barcode" aria-hidden="true"><?= $bars ?></div>
      <div class="id-motto">Present this card at check-in.<br><?= e((string) setting('support_phone')) ?></div>
    </div>
  </div>

  <div class="id-actions" data-no-print>
    <button class="btn btn-green" type="button" onclick="window.print()">Print / Save PDF</button>
    <a class="btn btn-gold" href="<?= e(url('portal/' . $participant['token'])) ?>">Back to my portal</a>
    <a class="btn btn-outline-green" href="<?= e(url('event.ics')) ?>">Add camp to calendar</a>
  </div>
  <p class="small muted center" style="margin-top:12px" data-no-print>Your ID card intentionally never shows your phone, email, payment reference, next-of-kin or welfare notes.</p>
</main>
