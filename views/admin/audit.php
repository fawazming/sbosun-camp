<?php
$query = $_GET; unset($query['r'], $query['page']);
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Accountability</p>
    <h1>Audit log</h1>
    <p class="sub"><?= number_format($total) ?> recorded events. Logins, exports, approvals, check-ins and configuration changes.</p>
  </div>
</div>

<div class="table-wrap">
  <?php if (!$logs): ?>
    <p class="empty">No audit events yet.</p>
  <?php else: ?>
    <table class="table" style="min-width:880px">
      <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="small muted" style="white-space:nowrap"><?= e(nice_datetime($log['created_at'])) ?></td>
            <td class="small"><strong><?= e($log['actor_name'] ?: 'system') ?></strong><?php if ($log['actor_id']): ?><div class="sub">#<?= (int) $log['actor_id'] ?></div><?php endif; ?></td>
            <td><span class="badge badge-slate"><?= e($log['action']) ?></span></td>
            <td class="small"><?= e($log['target_type'] ?: '—') ?><?= $log['target_id'] ? ' · ' . e($log['target_id']) : '' ?></td>
            <td class="small muted" style="max-width:300px"><?php
              $details = [];
              if ($log['before_json']) $details[] = 'before ' . $log['before_json'];
              if ($log['after_json']) $details[] = 'after ' . $log['after_json'];
              echo e(mb_strimwidth(implode(' | ', $details), 0, 220, '…'));
            ?></td>
            <td class="small mono"><?= e($log['ip'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="<?= e(url('admin/audit', array_merge($query, ['page' => $page - 1]))) ?>">← Prev</a><?php endif; ?>
    <span class="current"><?= $page ?></span>
    <span style="border:0;background:none;color:var(--muted)">of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= e(url('admin/audit', array_merge($query, ['page' => $page + 1]))) ?>">Next →</a><?php endif; ?>
  </div>
<?php endif; ?>
