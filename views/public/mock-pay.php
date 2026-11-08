<?php
$participant = (int) $payment['participant_id'] > 0
    ? db_one('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']])
    : null;
$bulkOrder = !empty($payment['bulk_order_id'])
    ? db_one('SELECT * FROM bulk_orders WHERE id = ?', [$payment['bulk_order_id']])
    : null;
?>
<main class="section" style="padding-top:44px">
  <div class="container container-narrow">
    <div class="center-card" style="max-width:520px">
      <div class="result-icon wait">🧪</div>
      <p class="eyebrow">Sandbox checkout</p>
      <h1 style="font-size:26px;margin-top:4px">Test payment gateway</h1>
      <p class="small muted" style="margin-top:7px">This page only exists while <span class="mono">PAYMENT_MODE=mock</span>. Change it in <span class="mono">.env</span> to manual or hosted for real payments.</p>

      <div class="detail-list">
        <?php if ($bulkOrder): ?>
          <div class="dl-row"><span>Buyer</span><span><?= e($bulkOrder['buyer_email']) ?></span></div>
          <div class="dl-row"><span>Bulk order</span><span><?= (int) $bulkOrder['seats'] ?> seats</span></div>
        <?php else: ?>
          <div class="dl-row"><span>Delegate</span><span><?= e(delegate_name($participant ?? [])) ?></span></div>
          <div class="dl-row"><span>Registration</span><span><?= e($participant['registration_id'] ?? '—') ?></span></div>
        <?php endif; ?>
        <div class="dl-row"><span>Reference</span><span class="mono"><?= e($payment['reference']) ?></span></div>
        <div class="dl-row"><span>Amount</span><span style="font-size:18px;color:var(--green)"><?= money((int) $payment['amount']) ?></span></div>
      </div>

      <form method="post" action="<?= e(url('pay/mock/' . $payment['reference'])) ?>" style="display:flex;gap:8px;margin-top:18px">
        <?= csrf_field() ?>
        <button class="btn btn-green" style="flex:1" type="submit" name="action" value="pay">Pay <?= money((int) $payment['amount']) ?></button>
        <button class="btn btn-outline-green" style="flex:1" type="submit" name="action" value="decline">Decline</button>
      </form>
      <p class="small muted" style="margin-top:14px">A successful test marks the payment verified, recalculates the delegate balance and assigns a house when fully paid. Bulk payments unlock all seat links.</p>
    </div>
  </div>
</main>
