<?php
$state = $result['state'] ?? 'error';
$fullName = $participant ? trim($participant['other_name'] . ' ' . $participant['surname']) : '';
$iconClass = $state === 'verified' ? 'ok' : ($state === 'pending' ? 'wait' : 'bad');
$icon = $state === 'verified' ? '✓' : ($state === 'pending' ? '⏳' : '✕');
?>
<main class="section" style="padding-top:36px">
  <div class="container container-narrow">
    <div class="center-card">
      <div class="result-icon <?= e($iconClass) ?>"><?= $icon ?></div>

      <?php if ($state === 'verified'): ?>
        <p class="eyebrow">Payment confirmed</p>
        <h1 style="font-size:28px;margin-top:4px">You’re all set<?= $fullName ? ', ' . e(explode(' ', $fullName)[0]) : '' ?>.</h1>
        <p class="small muted" style="margin-top:7px">Your payment has been independently verified on our server.</p>
        <div class="detail-list">
          <div class="dl-row"><span>Registration ID</span><span><?= e($participant['registration_id'] ?? '—') ?></span></div>
          <div class="dl-row"><span>Amount paid</span><span><?= money((int) ($payment['amount'] ?? 0)) ?></span></div>
          <div class="dl-row"><span>Payment status</span><span><?= e(payment_status_label($participant['payment_status'] ?? 'NOT_PAID')) ?></span></div>
          <div class="dl-row"><span>House</span><span><?= e($house['name'] ?? 'Assigned shortly') ?></span></div>
        </div>
        <?php if (($participant['payment_status'] ?? '') === 'PARTIALLY_PAID'): ?>
          <p class="notice notice-amber">This instalment is confirmed. Return to your portal any time to pay the next one before the early-bird deadline.</p>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:18px">
          <a class="btn btn-green btn-block" href="<?= e(url('portal/' . $participant['token'])) ?>">View my registration</a>
          <a class="btn btn-outline-green btn-block" href="<?= e(url('id-card/' . $participant['token'])) ?>">Open my ID card</a>
        </div>

      <?php elseif ($state === 'pending'): ?>
        <p class="eyebrow">Still confirming</p>
        <h1 style="font-size:26px;margin-top:4px">We’re waiting for confirmation.</h1>
        <p class="small muted" style="margin-top:7px"><?= e($result['message'] ?? 'This payment is still pending.') ?> If you have already transferred, an admin will confirm it shortly.</p>
        <?php if ($payment): ?>
          <div class="detail-list">
            <div class="dl-row"><span>Reference</span><span class="mono"><?= e($payment['reference']) ?></span></div>
            <div class="dl-row"><span>Amount</span><span><?= money((int) $payment['amount']) ?></span></div>
            <div class="dl-row"><span>Status</span><span><?= e($payment['status']) ?></span></div>
          </div>
        <?php endif; ?>
        <?php if ($participant): ?>
          <div style="display:flex;flex-direction:column;gap:8px;margin-top:18px">
            <a class="btn btn-green btn-block" href="<?= e(url('portal/' . $participant['token'])) ?>">Check my registration</a>
            <a class="btn btn-outline-green btn-block" href="<?= e(url('status')) ?>">Look up another registration</a>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <p class="eyebrow" style="color:var(--red)">Payment not completed</p>
        <h1 style="font-size:26px;margin-top:4px">This payment didn’t go through.</h1>
        <p class="small muted" style="margin-top:7px"><?= e($result['message'] ?? 'No charge was completed. You can try again from your portal.') ?></p>
        <?php if ($reference): ?>
          <p class="notice notice-red">Reference: <span class="mono"><?= e($reference) ?></span></p>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:18px">
          <?php if ($participant): ?>
            <a class="btn btn-gold btn-block" href="<?= e(url('portal/' . $participant['token'])) ?>">Try payment again</a>
          <?php endif; ?>
          <a class="btn btn-outline-green btn-block" href="<?= e(url('home')) ?>">Back to camp home</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
