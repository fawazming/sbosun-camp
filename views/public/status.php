<main class="section" style="padding-top:44px">
  <div class="container container-narrow">
    <div class="card" style="border-radius:24px;padding:30px">
      <p class="eyebrow">Registration lookup</p>
      <h1 class="display" style="font-size:32px;margin-top:4px">Find my registration.</h1>
      <p class="lede" style="margin-top:8px;font-size:14px">Enter your Delegate ID and surname exactly as submitted. This is rate-limited and does not expose contact details.</p>
      <form method="post" action="<?= e(url('status')) ?>" style="margin-top:20px">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field span-6">
            <label for="registration_id">Delegate / Registration ID</label>
            <input class="input" id="registration_id" name="registration_id" placeholder="SB-26-A1B2C3" required autocomplete="off">
          </div>
          <div class="field span-6">
            <label for="surname">Surname</label>
            <input class="input" id="surname" name="surname" placeholder="e.g. Alao" required autocomplete="family-name">
          </div>
          <div class="span-12">
            <button class="btn btn-green btn-block" type="submit">Check my registration</button>
          </div>
        </div>
      </form>
      <p class="small muted" style="margin-top:14px">Do you have the personal link from your registration confirmation? <a href="<?= e(url('home')) ?>" style="color:var(--green);font-weight:800">Return home</a> — the link opens your full portal.</p>
    </div>
  </div>
</main>
