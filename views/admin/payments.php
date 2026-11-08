<?php
$status = (string) request('status', '');
$query = $_GET; unset($query['r']);
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Money</p>
    <h1>Payments</h1>
    <p class="sub">Server-verified transactions, manual approvals and refunds.</p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-green btn-sm" href="<?= e(url('admin/payments/export')) ?>">Export CSV</a>
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/participants')) ?>">Participants</a>
  </div>
</div>

<div class="admin-grid admin-grid-2" style="margin-bottom:14px">
  <section class="panel">
    <p class="eyebrow">Total collected (verified)</p>
    <p style="font-size:32px;font-weight:900;color:var(--green);margin-top:3px"><?= money($collected) ?></p>
    <p class="small muted">Only payments verified by the gateway, webhook or a finance admin are counted.</p>
  </section>
  <section class="panel">
    <p class="eyebrow">Transaction mix</p>
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-top:8px;gap:8px">
      <?php foreach (['PENDING' => 'text-amber', 'VERIFIED' => 'text-green', 'FAILED' => 'text-red', 'REFUNDED' => ''] as $s => $accent): ?>
        <div style="background:var(--cream);border-radius:10px;padding:9px;text-align:center">
          <p class="<?= e($accent) ?>" style="font-size:18px;font-weight:900"><?= (int) ($counts[$s] ?? 0) ?></p>
          <p class="small muted"><?= e(ucfirst(strtolower($s))) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<div class="filter-tabs">
  <?php foreach (['' => 'All', 'PENDING' => 'Pending', 'VERIFIED' => 'Verified', 'FAILED' => 'Failed', 'REFUNDED' => 'Refunded'] as $key => $label): ?>
    <a class="filter-tab <?= $status === $key ? 'active' : '' ?>" href="<?= e(url('admin/payments', ['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get" action="<?= e(url('admin/payments')) ?>">
  <input type="hidden" name="r" value="admin/payments">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input class="input search" type="search" name="q" value="<?= e((string) request('q', '')) ?>" placeholder="Search reference, Delegate ID, name…">
  <button class="btn btn-green btn-sm" type="submit">Search</button>
  <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/payments', ['status' => $status])) ?>">Clear</a>
</form>

<div class="table-wrap">
  <?php if (!$rows): ?>
    <p class="empty">No transactions match this filter.</p>
  <?php else: ?>
    <table class="table" style="min-width:980px">
      <thead><tr><th>Reference</th><th>Participant</th><th>Type</th><th>Amount</th><th>Status</th><th>Provider</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td class="mono small"><?= e($p['reference']) ?><div class="sub"><?= e($p['provider_reference'] ?: '') ?></div></td>
            <td>
              <?php if (!empty($p['bulk_order_id'])): ?>
                <div class="name">Bulk order</div><div class="sub"><?= e($p['buyer_email'] ?: 'Bulk buyer') ?> · <?= (int) ($p['bulk_seats'] ?? 0) ?> seats</div>
              <?php else: ?>
                <div class="name"><?= e(delegate_name($p)) ?></div><div class="sub"><?= e($p['registration_id'] ?: '—') ?> · <?= e($p['zone'] ?: 'Profile pending') ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($p['payment_type'] === 'FULL' ? 'Full' : ($p['payment_type'] === 'BULK' ? 'Bulk · ' . (int) ($p['bulk_seats'] ?? 0) . ' seats' : 'Instalment ' . $p['installment_number'] . '/' . INSTALLMENT_COUNT)) ?><div class="sub"><?= e($p['plan_rate']) ?></div></td>
            <td style="font-weight:800;white-space:nowrap"><?= money((int) $p['amount']) ?></td>
            <td><span class="<?= e(badge_class($p['status'])) ?>"><?= e($p['status']) ?></span></td>
            <td class="small"><?= e($p['provider']) ?></td>
            <td class="small muted"><?= e(nice_date($p['paid_at'] ?: $p['created_at'], 'j M, g:ia')) ?></td>
            <td>
              <?php if ($p['status'] === 'PENDING' && admin_can(['FINANCE_ADMIN'])): ?>
                <form method="post" action="<?= e(url('admin/payments/' . $p['id'] . '/action')) ?>" data-confirm="Approve <?= e(money((int) $p['amount'])) ?> for <?= e($p['registration_id']) ?>? Only do this after confirming the money in your bank account.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-green btn-sm" type="submit">Approve</button>
                </form>
                <?php if (($p['provider'] ?? '') === 'payvessel'): ?>
                  <form method="post" action="<?= e(url('admin/payments/' . $p['id'] . '/action')) ?>" style="margin-top:4px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reverify">
                    <button class="btn btn-outline-green btn-sm" type="submit">Re-verify</button>
                  </form>
                <?php endif; ?>
              <?php elseif ($p['status'] === 'VERIFIED' && admin_can(['FINANCE_ADMIN'])): ?>
                <details>
                  <summary class="small" style="color:var(--red);font-weight:800;cursor:pointer">Refund</summary>
                  <form method="post" action="<?= e(url('admin/payments/' . $p['id'] . '/action')) ?>" style="margin-top:5px;min-width:170px" data-confirm="Record this refund? It is logged to the audit trail.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="refund">
                    <input class="input" name="reason" placeholder="Refund reason (required)" required style="font-size:10.5px;padding:6px 8px">
                    <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:var(--red);border:1px solid #fecaca;margin-top:4px">Confirm refund</button>
                  </form>
                </details>
              <?php else: ?>
                <span class="small muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<p class="small muted" style="margin-top:9px">Showing up to 300 most recent transactions. Use the search and status filters to narrow the list; export contains the full dataset.</p>
