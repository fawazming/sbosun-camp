<?php
$fee = current_fee();
$early = phase() === 'EARLY_BIRD';
$charge = gateway_charge();
$installmentCount = installment_count();
$fullTotal = $fee + $charge;
$installment = installment_cash_amount($fee, 1, $installmentCount);
$selectedCategory = old('category', 'Undergraduate');
?>
<main class="section" style="padding-top:34px">
  <div class="container">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
      <span class="brand-mark" style="background:var(--green);color:var(--gold);width:44px;height:44px;font-size:12px">SB</span>
      <div>
        <p style="font-size:14px;font-weight:900;color:var(--green)"><?= e((string) setting('name', 'SB HIC 2026')) ?> Registration</p>
        <p class="small muted">Standard Bearers Islamic Organization · Osun State</p>
      </div>
      <a class="btn btn-outline-green btn-sm" style="margin-left:auto" href="<?= e(url('home')) ?>">← Back home</a>
    </div>

    <div class="register-shell">
      <aside class="register-aside">
        <div>
          <span class="aside-tag">Pay first · details after</span>
          <h3 class="serif">Secure your place.</h3>
          <p class="aside-copy">Pay for your camp seat first. As soon as the payment is confirmed, your delegate form unlocks so you can provide your details and get your ID card.</p>

          <div class="aside-feature">
            <span class="af-icon">1</span>
            <div>
              <h5>Pay securely</h5>
              <p>Pay in full or start a <?= $installmentCount ?>-part instalment plan before the early-bird deadline.</p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">2</span>
            <div>
              <h5>Fill your delegate form</h5>
              <p>Your personal portal unlocks after payment. Add your details, next of kin and welfare notes.</p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">3</span>
            <div>
              <h5>Get your ID card</h5>
              <p>Use your portal or Find Registration any time to view your status, house and printable ID card.</p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">🛡️</span>
            <div>
              <h5>Server-verified</h5>
              <p>The server calculates every price and confirms every payment. We never ask for your card or banking password.</p>
            </div>
          </div>
        </div>

        <div class="calc-box">
          <div class="calc-label">🧮 PAYMENT SUMMARY</div>
          <div class="calc-card">
            <div class="calc-row"><span>Camp fee (<?= e(phase_label()) ?>)</span><strong><?= money($fee) ?></strong></div>
            <div class="calc-row"><span>Gateway charge</span><span><?= money($charge) ?></span></div>
            <div class="calc-row"><span>Pay in full</span><strong><?= money($fullTotal) ?></strong></div>
            <div class="calc-row gold" data-installment-note><span>Instalment 1 of <?= $installmentCount ?></span><span><?= money($installment) ?></span></div>
            <div class="calc-row" data-installment-note><span>Each later instalment</span><span>balance split + <?= money($charge) ?> charge</span></div>
            <div class="calc-row" data-full-note style="color:#6ee7b7">✓ Full single payment selected</div>
          </div>
        </div>
      </aside>

      <div class="register-body">
        <p class="form-section-title" style="margin-top:0">Step 1 of 2</p>
        <h4>Pay for your camp seat</h4>
        <p class="rb-sub">Enter only what we need to create your payment. The full delegate form comes after payment is confirmed — fields marked * are required.</p>

        <form method="post" action="<?= e(url('register')) ?>" style="margin-top:20px">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field span-12">
              <label for="email">Email Address *</label>
              <input class="input" type="email" id="email" name="email" placeholder="name@example.com" value="<?= old('email') ?>" required autocomplete="email">
              <span class="hint">Your receipt and portal link are sent here. Use the same email if you need to find your registration later.</span>
            </div>
            <div class="field span-12">
              <label for="phone">Phone / WhatsApp *</label>
              <input class="input" type="tel" id="phone" name="phone" placeholder="e.g. 08123456789" value="<?= old('phone') ?>" required autocomplete="tel">
            </div>
            <div class="field span-12">
              <label for="category">Category *</label>
              <select class="select" id="category" name="category" required>
                <?php foreach (categories() as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?> · <?= e($cat === 'JSS 1-3' ? 'Lower Class' : ($cat === 'SS1- SSS 3' ? 'Upper Class' : 'Dawah Class')) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="hint">Your class is assigned automatically from this category.</span>
            </div>

            <div class="span-12">
              <div class="form-section-title">Payment Option</div>
              <div class="pay-choice" style="margin-top:8px">
                <label>
                  <input type="radio" name="preferred_plan" value="FULL" data-plan checked>
                  <strong>Pay in Full</strong>
                  <span><?= money($fullTotal) ?> once</span>
                </label>
                <label <?= $early ? '' : 'style="opacity:.45;pointer-events:none"' ?>>
                  <input type="radio" name="preferred_plan" value="INSTALLMENT" data-plan <?= $early ? '' : 'disabled' ?>>
                  <strong>Split Payments</strong>
                  <span><?= $early ? $installmentCount . ' instalments from ' . money($installment) : 'Early bird only' ?></span>
                </label>
              </div>
              <p class="small muted" style="margin-top:7px">Every payment includes a <?= money($charge) ?> gateway charge. Instalments adapt to the current general fee and are only available before <?= e(nice_date((string) setting('early_bird_deadline'), 'j F Y')) ?>.</p>
            </div>

            <div class="span-12">
              <button class="btn btn-green btn-block" type="submit" style="margin-top:4px">Continue to secure payment →</button>
              <p class="small muted center" style="margin-top:9px">Buying for a group? <a href="<?= e(url('bulk')) ?>" style="color:var(--green);font-weight:800">Bulk registration &amp; payment</a>.</p>
              <p class="small muted center" style="margin-top:5px">Already paid? <a href="<?= e(url('status')) ?>" style="color:var(--green);font-weight:800">Find registration</a> to open your portal and complete your form.</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
