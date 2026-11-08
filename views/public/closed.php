<main class="section" style="padding-top:50px">
  <div class="container container-narrow">
    <div class="center-card">
      <div class="result-icon wait"><?= !empty($maintenance) ? '🛠️' : '⏸️' ?></div>
      <p class="eyebrow"><?= !empty($maintenance) ? 'Maintenance' : 'Registration paused' ?></p>
      <h1 style="font-size:27px;margin-top:4px"><?= !empty($maintenance) ? 'The camp portal is being updated.' : 'Registration is currently closed.' ?></h1>
      <p class="small muted" style="margin-top:8px">
        <?= !empty($maintenance) ? 'New registrations and payments are paused while we make an update. Already-registered delegates and staff can still sign in.' : 'New registrations are paused right now. If you already registered, you can still check your status and complete an outstanding payment.' ?>
      </p>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:18px">
        <a class="btn btn-green btn-block" href="<?= e(url('status')) ?>">Check my registration</a>
        <a class="btn btn-outline-green btn-block" href="<?= e(url('admin/login')) ?>">Staff sign in</a>
      </div>
      <p class="small muted" style="margin-top:14px">Support: <a style="color:var(--green);font-weight:800" href="mailto:<?= e((string) setting('support_email')) ?>"><?= e((string) setting('support_email')) ?></a> · <?= e((string) setting('support_phone')) ?></p>
    </div>
  </div>
</main>
