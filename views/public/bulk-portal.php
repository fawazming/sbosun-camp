<?php
$showPayment = $activePayment ?: $pending;
$totalSeats = (int) $order['seats'];
$pct = $totalSeats > 0 ? round(($used / $totalSeats) * 100) : 0;
$paid = in_array($order['status'], ['PAID', 'COMPLETED'], true);
$waMessage = rawurlencode(
    "Assalamu Alaykum. I have paid for a bulk SB HIC registration.\n"
    . "Buyer: " . $order['buyer_email'] . "\n"
    . "Reference: " . ($order['reference'] ?? '') . "\n"
    . "Seats: " . $totalSeats . " (₦" . number_format((int) $order['amount']) . ")\nMy receipt is attached."
);
?>
<main>
  <section class="portal-head">
    <div class="container">
      <p class="eyebrow" style="color:var(--gold)">Bulk registration · buyer portal</p>
      <h1 style="margin-top:4px"><?= e($order['buyer_email']) ?></h1>
      <p class="portal-meta"><?= $totalSeats ?> seat<?= $totalSeats === 1 ? '' : 's' ?> · <?= money((int) $order['amount']) ?> · <?= e(nice_datetime($order['created_at'])) ?></p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
        <span class="<?= e($paid ? 'badge badge-green' : 'badge badge-amber') ?>"><?= e(bulk_order_status_label($order['status'])) ?></span>
        <span class="badge badge-slate"><?= $used ?> of <?= $totalSeats ?> delegate forms completed</span>
      </div>
    </div>
  </section>

  <div class="container" style="padding-top:22px;padding-bottom:60px">
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px">
      <a class="btn btn-outline-green btn-sm" href="<?= e(url('status')) ?>">Find registration</a>
      <a class="btn btn-outline-green btn-sm" href="<?= e(url('bulk')) ?>">New bulk order</a>
      <a class="btn btn-outline-green btn-sm" href="<?= e(url('home')) ?>">Back home</a>
    </div>

    <div class="progress" style="height:10px;margin-bottom:18px"><span style="width:<?= (int) $pct ?>%"></span></div>

    <div class="portal-grid">
      <section class="panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
          <div>
            <h2 style="font-size:18px">Payment</h2>
            <p class="panel-sub">One payment covers all <?= $totalSeats ?> seat<?= $totalSeats === 1 ? '' : 's' ?> in this bulk order.</p>
          </div>
          <span class="<?= e($paid ? 'badge badge-green' : 'badge badge-amber') ?>"><?= e($paid ? 'Paid' : 'Pending') ?></span>
        </div>

        <div class="balance-row"><span class="muted">Order total</span><span class="mono" style="font-weight:800"><?= money((int) $order['amount']) ?></span></div>
        <div class="balance-row"><span class="muted">Seats</span><span class="mono" style="font-weight:800"><?= $totalSeats ?></span></div>
        <div class="balance-row total"><span>Status</span><span><?= e(bulk_order_status_label($order['status'])) ?></span></div>

        <?php if (!$paid && $showPayment && $showPayment['status'] === 'PENDING'): ?>
          <div style="margin-top:16px">
            <?php if ($mode === 'manual'): ?>
              <div class="transfer-box">
                <p class="tb-label">Bulk payment — transfer exactly</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <div class="transfer-row"><span>Bank</span><strong><?= e($showPayment['bank_name'] ?: config('payment.manual_bank', 'Bank')) ?></strong></div>
                <div class="transfer-row"><span>Account name</span><strong><?= e($showPayment['account_name'] ?: config('payment.manual_account_name', '')) ?></strong></div>
                <div class="transfer-row"><span>Account number</span><button type="button" class="copy-btn" data-copy="<?= e($showPayment['virtual_account_number'] ?? '') ?>"><?= e($showPayment['virtual_account_number'] ?? '—') ?> ⧉</button></div>
                <div class="transfer-row"><span>Reference</span><strong class="mono"><?= e($showPayment['reference']) ?></strong></div>
                <ol class="instructions">
                  <li>Transfer the exact amount.</li>
                  <li>Use <strong><?= e($order['buyer_email']) ?></strong> as the transfer narration if your bank allows it.</li>
                  <li>Send the receipt on WhatsApp so a finance admin can approve it.</li>
                </ol>
                <?php if ($whatsapp): ?>
                  <a class="btn btn-gold btn-block" style="margin-top:12px;background:#25D366;color:#fff" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $waMessage ?>">Send receipt on WhatsApp</a>
                <?php endif; ?>
                <p class="notice notice-amber">Seat links unlock as soon as an admin confirms the payment.</p>
              </div>
            <?php elseif ($mode === 'mock'): ?>
              <div class="transfer-box">
                <p class="tb-label">Sandbox bulk payment</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <p class="small muted" style="margin-top:4px">No real money moves. Successful test payments unlock all seat links.</p>
                <a class="btn btn-green btn-block" style="margin-top:12px" href="<?= e(url('pay/mock/' . $showPayment['reference'])) ?>">Open sandbox checkout</a>
              </div>
            <?php else: ?>
              <div class="transfer-box">
                <p class="tb-label">Hosted checkout</p>
                <p class="tb-amount"><?= money((int) $showPayment['amount']) ?></p>
                <a class="btn btn-green btn-block" style="margin-top:12px" href="<?= e($showPayment['checkout_url'] ?: '#') ?>">Continue to payment</a>
              </div>
            <?php endif; ?>
          </div>
        <?php elseif (!$paid): ?>
          <div class="notice notice-amber" style="margin-top:16px">No active payment found for this order. Start a new checkout below.</div>
        <?php endif; ?>

        <?php if (!$paid): ?>
          <form method="post" action="<?= e(url('bulk/retry/' . $order['token'])) ?>" style="margin-top:12px">
            <?= csrf_field() ?>
            <button class="btn btn-outline-green btn-sm" type="submit">Refresh / retry payment</button>
          </form>
        <?php endif; ?>

        <?php if ($payments): ?>
          <div style="margin-top:22px">
            <p class="eyebrow" style="margin-bottom:6px">Payment history</p>
            <div class="pay-history">
              <?php foreach ($payments as $p): ?>
                <div class="pay-row">
                  <div><p class="pr-main">Bulk payment</p><p class="pr-sub"><?= e($p['reference']) ?> · <?= e($p['provider']) ?></p></div>
                  <div style="text-align:right"><p class="mono" style="font-weight:800"><?= money((int) $p['amount']) ?></p><span class="<?= e(badge_class($p['status'])) ?>"><?= e($p['status']) ?></span></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <aside style="display:flex;flex-direction:column;gap:14px">
        <section class="card-dark">
          <p class="eyebrow" style="color:var(--gold)">How to distribute</p>
          <h3 style="font-size:18px;margin-top:4px;color:#fff">One link per delegate.</h3>
          <p class="small" style="color:var(--sage);margin-top:8px">Copy each unused link below and send it to the right delegate. Once a link is completed it is locked and cannot be reused.</p>
          <p class="small" style="color:var(--sage);margin-top:8px">Each delegate receives their own registration ID, portal and printable ID card.</p>
        </section>

        <?php if ($paid): ?>
          <section class="card">
            <p class="eyebrow">Order summary</p>
            <div class="kv"><span>Reference</span><span class="mono small"><?= e($order['reference'] ?: '—') ?></span></div>
            <div class="kv"><span>Buyer email</span><span class="small"><?= e($order['buyer_email']) ?></span></div>
            <div class="kv"><span>Buyer phone</span><span class="small"><?= e($order['buyer_phone']) ?></span></div>
            <div class="kv"><span>Paid</span><span class="small"><?= e($order['paid_at'] ? nice_datetime($order['paid_at']) : '—') ?></span></div>
          </section>
        <?php endif; ?>

        <section class="card" style="border-color:#fde68a;background:#fffbeb">
          <p class="eyebrow" style="color:#92400e">Privacy</p>
          <p class="small" style="color:#92400e;margin-top:6px">Only share a link with the delegate who will attend. The link gives access to that delegate’s personal portal and ID card.</p>
        </section>
      </aside>
    </div>

    <section class="panel" style="margin-top:18px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
        <h2 style="margin:0">Delegate links (<?= $used ?>/<?= $totalSeats ?> completed)</h2>
        <span class="badge badge-slate"><?= e(bulk_order_status_label($order['status'])) ?></span>
      </div>

      <?php if (!$paid): ?>
        <p class="empty">Your unique delegate links will appear here after the payment is confirmed.</p>
      <?php else: ?>
        <div class="table-wrap" style="border:0">
          <table class="table" style="min-width:760px">
            <thead><tr><th>Seat</th><th>Category</th><th>Status</th><th>Delegate</th><th>Link / portal</th></tr></thead>
            <tbody>
              <?php foreach ($tickets as $i => $ticket): ?>
                <tr>
                  <td style="font-weight:800"><?= $i + 1 ?></td>
                  <td><?= e($ticket['category']) ?></td>
                  <td>
                    <?php if ((int) $ticket['used'] === 1): ?>
                      <span class="badge badge-green">Completed</span>
                    <?php else: ?>
                      <span class="badge badge-amber">Awaiting delegate</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ((int) $ticket['used'] === 1): ?>
                      <div class="name"><?= e(trim(($ticket['other_name'] ?? '') . ' ' . ($ticket['surname'] ?? ''))) ?></div>
                      <div class="sub mono"><?= e($ticket['registration_id'] ?? '') ?></div>
                    <?php else: ?>
                      <span class="small muted">Not filled yet</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ((int) $ticket['used'] === 1): ?>
                      <span class="small muted">Completed — each link works once.</span>
                    <?php else: ?>
                      <a class="btn btn-gold btn-sm" href="<?= e(url('complete-ticket/' . $ticket['token'])) ?>">Open completion form</a>
                      <button type="button" class="btn btn-outline-green btn-sm" data-copy="<?= e(absolute_url('complete-ticket/' . $ticket['token'])) ?>">Copy link</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
