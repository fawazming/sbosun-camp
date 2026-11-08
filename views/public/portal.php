<?php
$displayName = trim(($participant['other_name'] ?? '') . ' ' . ($participant['surname'] ?? ''));
if ($displayName === '') {
    $displayName = $participant['email'];
}
$startedInstallments = has_installment_plan((int) $participant['id']);
$pending = null;
foreach ($payments as $p) {
    if ($p['status'] === 'PENDING') { $pending = $p; break; }
}
$showPayment = $activePayment ?: $pending;
$whatsapp = whatsapp_number();
$waMessage = rawurlencode(
    "Assalamu Alaykum. I have paid for " . setting('name', 'SB HIC 2026') . ".\n"
    . "Name: " . $displayName . "\n"
    . "Delegate ID: " . $participant['registration_id'] . "\n"
    . "Reference: " . ($showPayment['reference'] ?? '') . "\nMy receipt is attached."
);
?>
<main>
  <section class="portal-head">
    <div class="container">
      <p class="eyebrow" style="color:var(--gold)"><?= e((string) setting('name', 'SB HIC 2026')) ?> · Delegate portal</p>
      <h1 style="margin-top:4px"><?= e($displayName) ?></h1>
      <p class="portal-meta">
        <?php if ($profileCompleted): ?>
          Registration ID: <strong style="color:#fff"><?= e($participant['registration_id']) ?></strong> · <?= e($participant['zone'] ?: 'Zone pending') ?> · <?= e($participant['category']) ?>
        <?php else: ?>
          Registration ID: <strong style="color:#fff"><?= e($participant['registration_id']) ?></strong> · <?= e($participant['category']) ?> · delegate details pending
        <?php endif; ?>
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
        <span class="<?= e(badge_class($participant['payment_status'])) ?>"><?= e(payment_status_label($participant['payment_status'])) ?></span>
        <?php if ($profileCompleted): ?>
          <span class="<?= e(badge_class($participant['attendance_status'])) ?>"><?= e($participant['attendance_status'] === 'PRESENT' ? 'Checked in' : 'Not yet arrived') ?></span>
        <?php else: ?>
          <span class="badge badge-amber">Details incomplete</span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="container" style="padding-top:22px;padding-bottom:60px">
    <?php if (!$profileCompleted): ?>
      <?php if ($hasPayment): ?>
        <section style="background:linear-gradient(135deg,var(--green),var(--deep));color:var(--cream);border-radius:22px;padding:24px;margin-bottom:18px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px">
          <div>
            <p class="eyebrow" style="color:var(--gold)">Payment confirmed</p>
            <h2 style="font-size:23px;margin-top:3px;color:#fff">Complete your delegate details.</h2>
            <p class="small" style="color:var(--sage);margin-top:5px">Your seat is secured. Fill the form once to unlock your ID card, class and house.</p>
          </div>
          <a class="btn btn-gold" href="<?= e(url('complete/' . $participant['token'])) ?>">Complete registration →</a>
        </section>
      <?php else: ?>
        <section style="background:#fffbeb;border:1px solid #fde68a;border-radius:18px;padding:16px 18px;margin-bottom:18px">
          <p style="font-size:13px;font-weight:800;color:#92400e">Step 1: complete your payment. Your delegate form unlocks automatically once payment is confirmed.</p>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px">
      <?php if ($profileCompleted): ?>
        <a class="btn btn-outline-green btn-sm" href="<?= e(url('id-card/' . $participant['token'])) ?>">View ID card</a>
      <?php endif; ?>
      <a class="btn btn-outline-green btn-sm" href="<?= e(url('status')) ?>">Find registration</a>
      <a class="btn btn-outline-green btn-sm" href="<?= e(url('home')) ?>">Back home</a>
    </div>

    <div class="info-grid" style="margin-bottom:18px">
      <div class="info-tile"><p class="it-label">Category</p><p class="it-value"><?= e($participant['category']) ?></p></div>
      <div class="info-tile"><p class="it-label">Class</p><p class="it-value"><?= e($class['name'] ?? 'Pending') ?></p></div>
      <div class="info-tile"><p class="it-label">House</p><p class="it-value"><?= e($house['name'] ?? 'Assigned after full payment') ?></p></div>
      <div class="info-tile"><p class="it-label">Camp dates</p><p class="it-value"><?= e(camp_dates()) ?></p></div>
    </div>

    <div class="portal-grid">
      <section class="panel">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
          <div>
            <h2 style="font-size:18px">Payment</h2>
            <p class="panel-sub">Fees cover accommodation, feeding, welfare and materials for all five days.</p>
          </div>
          <span class="<?= e(badge_class($participant['payment_status'])) ?>"><?= e(payment_status_label($participant['payment_status'])) ?></span>
        </div>

        <div class="balance-row"><span class="muted">Total owed</span><span class="mono" style="font-weight:800"><?= money($owed) ?></span></div>
        <div class="balance-row"><span class="muted">Paid so far</span><span class="mono" style="font-weight:800;color:var(--green)"><?= money($paid) ?></span></div>
        <div class="balance-row total"><span>Balance</span><span class="mono"><?= money($balance) ?></span></div>
        <?php if ($startedInstallments): ?>
          <p class="small muted" style="margin-top:6px">Instalment plan started · <?= verified_installment_count((int) $participant['id']) ?> of <?= INSTALLMENT_COUNT ?> confirmed. Amounts adjust automatically if the general camp fee changes.</p>
        <?php endif; ?>

        <?php if ($showPayment && $showPayment['status'] === 'PENDING'): ?>
          <div style="margin-top:16px">
            <?php if ($mode === 'manual'): ?>
              <div class="transfer-box">
                <p class="tb-label"><?= e($showPayment['payment_type'] === 'INSTALLMENT' ? 'Instalment ' . $showPayment['installment_number'] . '/' . INSTALLMENT_COUNT : 'Payment') ?> — transfer exactly</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <div class="transfer-row"><span>Bank</span><strong><?= e($showPayment['bank_name'] ?: config('payment.manual_bank', 'Bank')) ?></strong></div>
                <div class="transfer-row"><span>Account name</span><strong><?= e($showPayment['account_name'] ?: config('payment.manual_account_name', '')) ?></strong></div>
                <div class="transfer-row"><span>Account number</span><button type="button" class="copy-btn" data-copy="<?= e($showPayment['virtual_account_number'] ?? '') ?>"><?= e($showPayment['virtual_account_number'] ?? '—') ?> ⧉</button></div>
                <div class="transfer-row"><span>Reference</span><strong class="mono"><?= e($showPayment['reference']) ?></strong></div>
                <ol class="instructions">
                  <li>Transfer the exact amount to the account above.</li>
                  <li>Put your Delegate ID <strong><?= e($participant['registration_id']) ?></strong> in the transfer description.</li>
                  <li>Take a screenshot of the receipt and send it to us on WhatsApp.</li>
                </ol>
                <?php if ($whatsapp): ?>
                  <a class="btn btn-gold btn-block" style="margin-top:12px;background:#25D366;color:#fff" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $waMessage ?>">Send receipt on WhatsApp</a>
                <?php endif; ?>
                <p class="notice notice-amber">An admin confirms your payment once the money is seen in our account. Your form unlocks automatically after approval.</p>
              </div>
            <?php elseif ($mode === 'mock'): ?>
              <div class="transfer-box">
                <p class="tb-label">Sandbox payment</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <p class="small muted" style="margin-top:4px">This portal is in sandbox mode. Use the test checkout to simulate a successful or cancelled payment.</p>
                <a class="btn btn-green btn-block" style="margin-top:12px" href="<?= e(url('pay/mock/' . $showPayment['reference'])) ?>">Open sandbox checkout</a>
              </div>
            <?php else: ?>
              <div class="transfer-box">
                <p class="tb-label">Hosted checkout</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <p class="small muted" style="margin-top:4px">Continue to the secure payment page to complete this transaction.</p>
                <a class="btn btn-green btn-block" style="margin-top:12px" href="<?= e($showPayment['checkout_url'] ?: '#') ?>">Continue to payment</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($balance > 0): ?>
          <div style="margin-top:18px">
            <?php if ($quote !== null): ?>
              <div class="notice notice-amber" style="margin-bottom:10px">
                Next instalment <?= (int) $quote['slot'] ?>/<?= INSTALLMENT_COUNT ?>: <strong><?= money((int) $quote['amount']) ?></strong>
                (<?= (int) $quote['slots_left'] ?> payment<?= (int) $quote['slots_left'] === 1 ? '' : 's' ?> left, recalculated from your balance).
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="post" action="<?= e(url('pay/start')) ?>" style="flex:1;min-width:190px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="token" value="<?= e($participant['token']) ?>">
                  <input type="hidden" name="payment_type" value="INSTALLMENT">
                  <input type="hidden" name="installment_number" value="<?= (int) $quote['slot'] ?>">
                  <button class="btn btn-gold btn-block" type="submit"><?= $startedInstallments ? 'Pay next instalment' : 'Start instalments' ?> · <?= money((int) $quote['amount']) ?></button>
                </form>
                <form method="post" action="<?= e(url('pay/start')) ?>" style="flex:1;min-width:190px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="token" value="<?= e($participant['token']) ?>">
                  <input type="hidden" name="payment_type" value="FULL">
                  <button class="btn btn-outline-green btn-block" type="submit">Pay full balance · <?= money($balance) ?></button>
                </form>
              </div>
            <?php else: ?>
              <form method="post" action="<?= e(url('pay/start')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($participant['token']) ?>">
                <input type="hidden" name="payment_type" value="FULL">
                <button class="btn btn-green btn-block" type="submit">Pay full balance · <?= money($balance) ?></button>
              </form>
            <?php endif; ?>
            <p class="small muted" style="margin-top:7px">The server calculates the amount from the current pricing phase. Browser-submitted prices are never trusted.</p>
          </div>
        <?php elseif ($profileCompleted): ?>
          <div class="notice notice-green" style="margin-top:16px">✓ You are fully paid. Your house and ID card are ready.</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <a class="btn btn-green" href="<?= e(url('id-card/' . $participant['token'])) ?>">Open my ID card</a>
            <?php if ($house): ?><span class="badge badge-gold" style="align-self:center"><?= e($house['name']) ?></span><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="notice notice-amber" style="margin-top:16px">Payment received. Complete your delegate details to finish registration.</div>
          <a class="btn btn-green btn-block" style="margin-top:10px" href="<?= e(url('complete/' . $participant['token'])) ?>">Complete registration →</a>
        <?php endif; ?>

        <?php if ($payments): ?>
          <div style="margin-top:22px">
            <p class="eyebrow" style="margin-bottom:6px">Payment history</p>
            <div class="pay-history">
              <?php foreach ($payments as $p): ?>
                <div class="pay-row">
                  <div>
                    <p class="pr-main"><?= $p['payment_type'] === 'FULL' ? 'Full payment' : ($p['payment_type'] === 'BULK' ? 'Bulk payment' : 'Instalment ' . e((string) $p['installment_number']) . '/' . INSTALLMENT_COUNT) ?></p>
                    <p class="pr-sub"><?= e($p['reference']) ?> · <?= e($p['provider']) ?></p>
                  </div>
                  <div style="text-align:right">
                    <p class="mono" style="font-weight:800"><?= money((int) $p['amount']) ?></p>
                    <span class="<?= e(badge_class($p['status'])) ?>"><?= e($p['status']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <aside style="display:flex;flex-direction:column;gap:14px">
        <section class="card-dark">
          <p class="eyebrow" style="color:var(--gold)">Camp information</p>
          <h3 style="font-size:18px;margin-top:4px;color:#fff">Your place at <?= e((string) setting('venue_name')) ?></h3>
          <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;font-size:12.5px;color:var(--sage)">
            <div><strong style="color:var(--gold)">Class:</strong> <?= e($class['name'] ?? 'Pending') ?></div>
            <div><strong style="color:var(--gold)">House:</strong> <?= e($house['name'] ?? 'Assigned after payment is confirmed') ?></div>
            <div><strong style="color:var(--gold)">Attendance:</strong> <?= e($participant['attendance_status'] === 'PRESENT' ? 'Checked in' : 'Not yet arrived') ?></div>
            <div><strong style="color:var(--gold)">Venue:</strong> <?= e((string) setting('venue_name')) ?>, <?= e((string) setting('venue_address')) ?></div>
            <div><strong style="color:var(--gold)">Dates:</strong> <?= e(camp_dates()) ?></div>
          </div>
          <p class="small" style="color:var(--sage);margin-top:14px">Bring your registration ID at arrival for check-in. Payment must be completed before you can be checked in.</p>
        </section>

        <section class="card">
          <p class="eyebrow">Need help?</p>
          <p class="small muted" style="margin-top:6px">Contact the camp team if anything looks wrong with your registration.</p>
          <div style="margin-top:10px;font-size:12.5px">
            <p><strong>Email:</strong> <a style="color:var(--green)" href="mailto:<?= e((string) setting('support_email')) ?>"><?= e((string) setting('support_email')) ?></a></p>
            <p><strong>Phone:</strong> <?= e((string) setting('support_phone')) ?></p>
          </div>
        </section>

        <section class="card" style="border-color:#fde68a;background:#fffbeb">
          <p class="eyebrow" style="color:#92400e">Welfare note</p>
          <p class="small" style="color:#92400e;margin-top:6px">Your next-of-kin phone and allergy/existing ailment details are visible only to authorised welfare staff. They are never printed on your ID card.</p>
        </section>
      </aside>
    </div>
  </div>
</main>
