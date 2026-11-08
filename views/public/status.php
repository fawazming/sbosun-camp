<main class="section" style="padding-top:44px">
  <div class="container container-narrow">
    <div class="card" style="border-radius:24px;padding:30px">
      <p class="eyebrow">Registration lookup</p>
      <h1 class="display" style="font-size:32px;margin-top:4px">Find my registration.</h1>
      <p class="lede" style="margin-top:8px;font-size:14px">Paid but haven’t filled the delegate form? Use your payment reference and email. Already completed? Use your registration ID and surname.</p>

      <div class="grid grid-2" style="margin-top:20px;align-items:start">
        <form method="post" action="<?= e(url('status')) ?>" style="background:var(--mint);border:1px solid rgba(15,61,46,.08);border-radius:16px;padding:18px">
          <?= csrf_field() ?>
          <p class="eyebrow">Paid first?</p>
          <h3 style="font-size:17px;margin-top:3px">Use payment reference + email</h3>
          <p class="small muted" style="margin-top:5px">This opens your portal even before the delegate form is completed.</p>
          <div class="field" style="margin-top:12px">
            <label for="reference">Payment reference</label>
            <input class="input" id="reference" name="reference" placeholder="SBHIC-… or provider reference" required autocomplete="off">
          </div>
          <div class="field" style="margin-top:10px">
            <label for="payer_email">Email used to pay</label>
            <input class="input" type="email" id="payer_email" name="payer_email" placeholder="name@example.com" required autocomplete="email">
          </div>
          <button class="btn btn-green btn-block" type="submit" style="margin-top:14px">Open my portal</button>
        </form>

        <form method="post" action="<?= e(url('status')) ?>" style="background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px">
          <?= csrf_field() ?>
          <p class="eyebrow">Form completed?</p>
          <h3 style="font-size:17px;margin-top:3px">Use registration ID + surname</h3>
          <p class="small muted" style="margin-top:5px">Check your status without revealing contact or welfare details.</p>
          <div class="field" style="margin-top:12px">
            <label for="registration_id">Delegate / Registration ID</label>
            <input class="input" id="registration_id" name="registration_id" placeholder="SB-26-A1B2C3" autocomplete="off">
          </div>
          <div class="field" style="margin-top:10px">
            <label for="surname">Surname</label>
            <input class="input" id="surname" name="surname" placeholder="e.g. Alao" autocomplete="family-name">
          </div>
          <button class="btn btn-outline-green btn-block" type="submit" style="margin-top:14px">Check status</button>
        </form>
      </div>

      <p class="small muted" style="margin-top:16px">Bulk buyer? The payment-reference method opens your buyer portal with all delegate links. Individual delegates use the registration-ID method once their form is complete.</p>
    </div>
  </div>
</main>
