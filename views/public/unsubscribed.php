<main class="section" style="padding-top:50px">
  <div class="container container-narrow">
    <div class="center-card">
      <div class="result-icon ok">✓</div>
      <p class="eyebrow">Email preferences</p>
      <h1 style="font-size:26px;margin-top:4px">You’ve been unsubscribed.</h1>
      <p class="small muted" style="margin-top:8px">
        <?= $subscriber ? e($subscriber['email']) . ' will no longer receive camp marketing emails.' : 'This unsubscribe link is not recognised, but you will not receive further marketing emails from this link.' ?>
      </p>
      <p class="small muted" style="margin-top:8px">Transactional messages about an existing registration (such as a confirmed payment) may still be sent.</p>
      <a class="btn btn-green" style="margin-top:18px" href="<?= e(url('home')) ?>">Back to camp home</a>
    </div>
  </div>
</main>
