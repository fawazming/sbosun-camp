<div class="admin-page-head">
  <div>
    <p class="eyebrow">Camp updates</p>
    <h1>Newsletter</h1>
    <p class="sub"><?= number_format($active) ?> active subscriber<?= $active === 1 ? '' : 's' ?> · <?= number_format($total) ?> total.</p>
  </div>
  <div class="bulk-actions">
    <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/reports/export', ['type' => 'newsletter'])) ?>">Export CSV</a>
    <span class="<?= e($mail_ready ? 'badge badge-green' : 'badge badge-amber') ?>"><?= $mail_ready ? 'SMTP ready' : 'SMTP not configured' ?></span>
  </div>
</div>

<div class="admin-grid admin-grid-2">
  <section class="panel">
    <h2>Broadcast to subscribers</h2>
    <p class="small muted" style="margin-bottom:10px">Sends to active subscribers only (max 200 per batch), each with a personal unsubscribe link. Marketing is never sent to unsubscribed addresses.</p>
    <?php if (!$mail_ready): ?><div class="notice notice-amber">Mail is not configured. Broadcasts are logged but cannot deliver until SMTP is set in <span class="mono">.env</span>.</div><?php endif; ?>
    <form method="post" action="<?= e(url('admin/newsletter/action')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="broadcast">
      <div class="form-grid">
        <div class="field span-12"><label>Subject</label><input class="input" name="subject" placeholder="e.g. SB HIC 2026 — what to bring" required></div>
        <div class="field span-12"><label>Email title</label><input class="input" name="title" placeholder="Defaults to the subject"></div>
        <div class="field span-12"><label>Message</label><textarea class="textarea" name="message" placeholder="Write your camp update…" required></textarea></div>
      </div>
      <button class="btn btn-gold btn-sm" type="submit" style="margin-top:10px" data-confirm="Send this broadcast to active subscribers?">Send broadcast</button>
    </form>
  </section>

  <section class="panel">
    <h2>Subscribers</h2>
    <?php if (!$rows): ?><p class="empty">No subscribers yet.</p><?php else: ?>
      <div style="max-height:430px;overflow:auto">
        <?php foreach ($rows as $s): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:1px solid #f1f5f0;padding:7px 0">
            <div><p style="font-size:12px;font-weight:700"><?= e($s['email']) ?></p><p class="small muted"><?= e($s['name'] ?: '—') ?> · <?= e(nice_date($s['subscribed_at'], 'j M Y')) ?></p></div>
            <div style="display:flex;align-items:center;gap:6px">
              <span class="<?= e($s['status'] === 'subscribed' ? 'badge badge-green' : 'badge badge-slate') ?>"><?= e($s['status']) ?></span>
              <form method="post" action="<?= e(url('admin/newsletter/action')) ?>" data-confirm="Remove this subscriber permanently?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-sm" type="submit" style="padding:4px 8px;background:#fee2e2;color:var(--red);border:1px solid #fecaca">✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="small muted" style="margin-top:9px">Showing up to 500 subscribers. Export for the complete list.</p>
    <?php endif; ?>
  </section>
</div>
