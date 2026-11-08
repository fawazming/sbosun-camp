<main class="section" style="padding-top:60px">
  <div class="container container-narrow">
    <div class="center-card">
      <div class="result-icon bad">500</div>
      <h1 style="font-size:26px">Something went wrong.</h1>
      <p class="small muted" style="margin-top:8px">The camp team has been notified. Please try again in a moment, or contact support if this keeps happening.</p>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:18px">
        <a class="btn btn-green btn-block" href="<?= e(url('home')) ?>">Back to camp home</a>
        <a class="btn btn-outline-green btn-block" href="mailto:<?= e((string) setting('support_email')) ?>">Contact support</a>
      </div>
    </div>
  </div>
</main>
