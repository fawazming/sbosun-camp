<div class="admin-page-head">
  <div>
    <p class="eyebrow">Group registration</p>
    <h1>Bulk orders</h1>
    <p class="sub">One payment, multiple seats. Each seat gets a unique one-time completion link.</p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-green btn-sm" href="<?= e(url('bulk')) ?>" target="_blank" rel="noopener">Open bulk registration ↗</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/payments')) ?>">Payments</a>
  </div>
</div>

<div class="stat-grid" style="margin-bottom:16px">
  <div class="stat-card"><p class="sc-label">Bulk orders</p><p class="sc-value"><?= number_format($stats['orders']) ?></p><p class="sc-sub">All time</p></div>
  <div class="stat-card"><p class="sc-label">Paid orders</p><p class="sc-value text-green"><?= number_format($stats['paid']) ?></p><p class="sc-sub">Primary payment verified</p></div>
  <div class="stat-card"><p class="sc-label">Seats sold</p><p class="sc-value"><?= number_format($stats['seats']) ?></p><p class="sc-sub">Across all orders</p></div>
  <div class="stat-card"><p class="sc-label">Forms completed</p><p class="sc-value text-amber"><?= number_format($stats['done']) ?></p><p class="sc-sub">One-time links used</p></div>
</div>

<div class="table-wrap">
  <?php if (!$orders): ?>
    <p class="empty">No bulk orders yet. Share the <a href="<?= e(url('bulk')) ?>" style="color:var(--green);font-weight:800">bulk registration page</a> to get started.</p>
  <?php else: ?>
    <table class="table" style="min-width:980px">
      <thead><tr><th>Reference</th><th>Buyer</th><th>Seats</th><th>Amount</th><th>Status</th><th>Progress</th><th>Created</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
          $done = (int) $order['seats_done'];
          $seats = max(1, (int) $order['seats_total']);
          $pct = round(($done / $seats) * 100);
          $status = (string) $order['status'];
          $badge = in_array($status, ['PAID', 'COMPLETED'], true) ? 'badge badge-green' : ($status === 'PENDING' ? 'badge badge-amber' : 'badge badge-red');
          ?>
          <tr>
            <td class="mono small"><?= e($order['reference'] ?: '—') ?><div class="sub">#<?= (int) $order['id'] ?></div></td>
            <td><div class="name"><?= e($order['buyer_email']) ?></div><div class="sub"><?= e($order['buyer_phone']) ?></div></td>
            <td><?= (int) $order['seats'] ?><div class="sub"><?= $done ?> completed</div></td>
            <td style="font-weight:800"><?= money((int) $order['amount']) ?></td>
            <td><span class="<?= e($badge) ?>"><?= e(bulk_order_status_label($status)) ?></span></td>
            <td style="min-width:130px">
              <div class="progress" style="height:6px"><span style="width:<?= (int) $pct ?>%"></span></div>
              <div class="sub"><?= $done ?>/<?= (int) $order['seats'] ?> links used</div>
            </td>
            <td class="small muted"><?= e(nice_datetime($order['created_at'])) ?></td>
            <td>
              <a class="btn btn-outline-green btn-sm" href="<?= e(url('bulk/' . $order['token'])) ?>" target="_blank" rel="noopener">Buyer portal ↗</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<p class="small muted" style="margin-top:9px">Showing the most recent 300 orders. Bulk payments appear in the Payments screen and can be approved there when using manual mode.</p>
