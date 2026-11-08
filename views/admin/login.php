<div class="admin-login-wrap">
  <div class="admin-login-card">
    <div class="brand-mark">SB</div>
    <h1>Admin Login</h1>
    <p class="sub"><?= e((string) setting('name', 'SB HIC 2026')) ?> — Camp Management</p>

    <form method="post" action="<?= e(url('admin/login')) ?>" style="margin-top:22px">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e((string) request('next', '')) ?>">
      <div class="field" style="margin-bottom:12px">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-green btn-block" type="submit" style="margin-top:18px">Sign In</button>
    </form>

    <p class="small muted center" style="margin-top:14px"><a href="<?= e(url('home')) ?>" style="color:var(--green);font-weight:800">← Back to camp site</a></p>
    <p class="small muted center" style="margin-top:10px;font-size:10px">Protected area. All sign-in attempts and admin actions are logged.</p>
  </div>
</div>
