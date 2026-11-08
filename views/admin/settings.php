<?php
$deadlineValue = date('Y-m-d\TH:i', strtotime((string) setting('early_bird_deadline')));
$startValue = date('Y-m-d', strtotime((string) setting('camp_start')));
$endValue = date('Y-m-d', strtotime((string) setting('camp_end')));
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Configuration</p>
    <h1>Settings</h1>
    <p class="sub">Business values are stored in the database. Secrets (gateway keys, SMTP) stay in <span class="mono">.env</span>.</p>
  </div>
  <div class="bulk-actions">
    <span class="<?= e($gateway_ready ? 'badge badge-green' : 'badge badge-amber') ?>">Gateway: <?= $gateway_ready ? 'configured' : 'not configured' ?></span>
    <span class="<?= e($mail_ready ? 'badge badge-green' : 'badge badge-amber') ?>">Mail: <?= $mail_ready ? 'configured' : 'not configured' ?></span>
    <span class="badge badge-slate">Env: <?= e((string) config('app.env')) ?></span>
  </div>
</div>

<form method="post" action="<?= e(url('admin/settings/save')) ?>">
  <?= csrf_field() ?>
  <div class="admin-grid admin-grid-2">
    <section class="panel">
      <h2>Programme</h2>
      <div class="form-grid">
        <div class="field span-8"><label>Camp name</label><input class="input" name="name" value="<?= e((string) setting('name')) ?>" required></div>
        <div class="field span-4"><label>Edition year</label><input class="input" type="number" name="year" value="<?= e((string) setting('year')) ?>" min="2020" max="2100" required></div>
        <div class="field span-6"><label>Camp start</label><input class="input" type="date" name="camp_start" value="<?= e($startValue) ?>" required></div>
        <div class="field span-6"><label>Camp end</label><input class="input" type="date" name="camp_end" value="<?= e($endValue) ?>" required></div>
        <div class="field span-6"><label>Venue name</label><input class="input" name="venue_name" value="<?= e((string) setting('venue_name')) ?>" required></div>
        <div class="field span-6"><label>Venue address</label><input class="input" name="venue_address" value="<?= e((string) setting('venue_address')) ?>" required></div>
        <div class="field span-12"><label>Support email</label><input class="input" type="email" name="support_email" value="<?= e((string) setting('support_email')) ?>" required></div>
        <div class="field span-6"><label>Support phone</label><input class="input" name="support_phone" value="<?= e((string) setting('support_phone')) ?>" required></div>
        <div class="field span-6"><label>Default house capacity</label><input class="input" type="number" name="default_house_capacity" value="<?= e((string) setting('default_house_capacity', 40)) ?>" min="1" required></div>
      </div>
    </section>

    <section class="panel">
      <h2>Fees &amp; pricing phase</h2>
      <div class="form-grid">
        <div class="field span-6"><label>Early-bird fee (₦)</label><input class="input" type="number" name="early_bird_fee" value="<?= e((string) setting('early_bird_fee')) ?>" min="1" required></div>
        <div class="field span-6"><label>Flat rate fee (₦)</label><input class="input" type="number" name="flat_fee" value="<?= e((string) setting('flat_fee')) ?>" min="1" required></div>
        <div class="field span-12"><label>Early-bird deadline</label><input class="input" type="datetime-local" name="early_bird_deadline" value="<?= e($deadlineValue) ?>" required></div>
      </div>
      <div class="notice notice-amber" style="margin-top:12px">
        Current phase: <strong><?= e(phase_label()) ?></strong> · charge now: <strong><?= money(current_fee()) ?></strong>. The server always recalculates the amount; changing these values never affects an already verified payment.
      </div>

      <h2 style="margin-top:18px">Registration state</h2>
      <label class="check" style="margin-bottom:8px"><input type="checkbox" name="registration_open" value="1" <?= (string) setting('registration_open', '1') === '1' ? 'checked' : '' ?>> Registration is open</label>
      <label class="check"><input type="checkbox" name="maintenance_mode" value="1" <?= (string) setting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>> Maintenance mode (pauses new registration/payment)</label>
      <p class="small muted" style="margin-top:7px">Maintenance mode blocks public registration and payments but leaves admin and existing delegate portals reachable.</p>
    </section>

    <section class="panel">
      <h2>Payment mode</h2>
      <div class="field">
        <label>How delegates pay</label>
        <select class="select" name="payment_mode">
          <option value="mock" <?= payment_mode() === 'mock' ? 'selected' : '' ?>>Mock / sandbox (testing only)</option>
          <option value="manual" <?= payment_mode() === 'manual' ? 'selected' : '' ?>>Manual bank transfer + WhatsApp receipt</option>
          <option value="hosted" <?= payment_mode() === 'hosted' ? 'selected' : '' ?>>Hosted checkout (PayVessel / PayFlow)</option>
        </select>
      </div>
      <p class="small muted" style="margin-top:6px">Hosted mode uses the API keys in <span class="mono">.env</span> and verifies every payment server-side.</p>

      <h2 style="margin-top:16px">Manual transfer account</h2>
      <div class="form-grid">
        <div class="field span-12"><label>Bank name</label><input class="input" name="payment_bank_name" value="<?= e((string) setting('payment_bank_name', config('payment.manual_bank', ''))) ?>"></div>
        <div class="field span-12"><label>Account name</label><input class="input" name="payment_account_name" value="<?= e((string) setting('payment_account_name', config('payment.manual_account_name', ''))) ?>"></div>
        <div class="field span-7"><label>Account number</label><input class="input" name="payment_account_number" value="<?= e((string) setting('payment_account_number', config('payment.manual_account_no', ''))) ?>"></div>
        <div class="field span-5"><label>WhatsApp number</label><input class="input" name="payment_whatsapp" value="<?= e((string) setting('payment_whatsapp', config('payment.whatsapp', ''))) ?>"></div>
      </div>
    </section>

    <section class="panel">
      <h2>Classes &amp; capacity</h2>
      <p class="small muted" style="margin-bottom:10px">Category keys are permanent database values. Labels can change per edition; never rename a key after launch.</p>
      <?php foreach ($classes as $c): ?>
        <div class="kv"><span><?= e($c['name']) ?></span><span class="small"><?= e(implode(', ', json_decode((string) $c['eligible_categories'], true) ?: [])) ?></span></div>
      <?php endforeach; ?>

      <h2 style="margin-top:16px">Integration status</h2>
      <div class="kv"><span>Hosted gateway keys</span><span><span class="<?= e($gateway_ready ? 'badge badge-green' : 'badge badge-amber') ?>"><?= $gateway_ready ? 'Configured' : 'Missing' ?></span></span></div>
      <div class="kv"><span>SMTP mail</span><span><span class="<?= e($mail_ready ? 'badge badge-green' : 'badge badge-amber') ?>"><?= $mail_ready ? 'Configured' : 'Missing' ?></span></span></div>
      <div class="kv"><span>Webhook secret</span><span><span class="<?= e(config('payment.webhook_secret') ? 'badge badge-green' : 'badge badge-amber') ?>"><?= config('payment.webhook_secret') ? 'Configured' : 'Missing' ?></span></span></div>
      <p class="small muted" style="margin-top:9px">Secrets are never rendered in HTML. Edit <span class="mono">.env</span> on the server, then reload this page.</p>
    </section>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
    <button class="btn btn-gold" type="submit">Save settings</button>
    <a class="btn btn-outline-green" href="<?= e(url('admin/dashboard')) ?>">Back to dashboard</a>
  </div>
</form>
