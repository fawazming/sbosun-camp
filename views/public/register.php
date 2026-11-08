<?php
$fee = current_fee();
$early = phase() === 'EARLY_BIRD';
$installment = (int) round($fee / INSTALLMENT_COUNT);
$zoneList = zones();
$catList = categories();
$ageList = age_groups();
$selectedCategory = old('category', 'Undergraduate');
$selectedZone = old('zone', 'Osogbo');
$selectedAge = old('age_group', '10 - 20');
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
          <span class="aside-tag">Registration Details</span>
          <h3 class="serif">Uphold the Standard</h3>
          <p class="aside-copy">Your registration fee fully covers hostel accommodation, daily meals, welfare, study guides and camp sports packages for all five days of camp.</p>

          <div class="aside-feature">
            <span class="af-icon">📅</span>
            <div>
              <h5>Timeline Limit</h5>
              <p><?= $early ? 'Early-bird rate of <strong style="color:#fff">' . money((int) setting('early_bird_fee', 8500)) . '</strong> closes ' . e(nice_date((string) setting('early_bird_deadline'), 'j F Y')) . '. Flat rate is ' . money((int) setting('flat_fee', 12000)) . '.' : 'Early bird has closed. The flat camp rate is <strong style="color:#fff">' . money((int) setting('flat_fee', 12000)) . '</strong>.' ?></p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">👛</span>
            <div>
              <h5>Instalment Flexibility</h5>
              <p>Split your payment into <?= INSTALLMENT_COUNT ?> parts (<?= money($installment) ?> each). Must be completed by <?= e(nice_date((string) setting('early_bird_deadline'), 'j F')) ?>.</p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">🛡️</span>
            <div>
              <h5>Safe &amp; verified</h5>
              <p>Payment is confirmed by our team or the payment provider. We never ask for your card or banking password.</p>
            </div>
          </div>
        </div>

        <div class="calc-box">
          <div class="calc-label">🧮 INSTALMENT CALCULATOR</div>
          <div class="calc-card">
            <div class="calc-row"><span>Total Camp Fee</span><strong><?= money($fee) ?></strong></div>
            <div class="calc-row gold" data-installment-note><span>1st Instalment (1/<?= INSTALLMENT_COUNT ?>)</span><span><?= money($installment) ?></span></div>
            <div class="calc-row" data-installment-note><span>Instalments 2–<?= INSTALLMENT_COUNT ?></span><span><?= money($installment) ?> each</span></div>
            <div class="calc-row" data-full-note style="color:#6ee7b7">✓ Full single payment selected</div>
          </div>
        </div>
      </aside>

      <div class="register-body">
        <div class="form-section-title" style="margin-top:0">Delegate Registration Form</div>
        <h4 style="margin-top:4px">Delegate Registration Form</h4>
        <p class="rb-sub">Provide accurate details below for camp verification. Fields marked * are required.</p>

        <form method="post" action="<?= e(url('register')) ?>" style="margin-top:20px">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field span-4">
              <label for="surname">Surname *</label>
              <input class="input" id="surname" name="surname" placeholder="e.g. Alao" value="<?= old('surname') ?>" required autocomplete="family-name">
            </div>
            <div class="field span-4">
              <label for="other_name">Other Name *</label>
              <input class="input" id="other_name" name="other_name" placeholder="e.g. Ibrahim Adebayo" value="<?= old('other_name') ?>" required autocomplete="given-name">
            </div>
            <div class="field span-4">
              <label for="gender">Sex *</label>
              <select class="select" id="gender" name="gender" required>
                <option value="MALE" <?= old('gender') === 'FEMALE' ? '' : 'selected' ?>>Brother (Male)</option>
                <option value="FEMALE" <?= old('gender') === 'FEMALE' ? 'selected' : '' ?>>Sister (Female)</option>
              </select>
            </div>

            <div class="field span-4">
              <label for="age_group">Age Bracket *</label>
              <select class="select" id="age_group" name="age_group" required>
                <?php foreach ($ageList as $age): ?>
                  <option value="<?= e($age) ?>" <?= $selectedAge === $age ? 'selected' : '' ?>><?= e($age) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field span-8">
              <label for="email">Email Address *</label>
              <input class="input" type="email" id="email" name="email" placeholder="name@example.com" value="<?= old('email') ?>" required autocomplete="email">
            </div>

            <div class="field span-6">
              <label for="phone">Phone / WhatsApp *</label>
              <input class="input" type="tel" id="phone" name="phone" placeholder="e.g. 08123456789" value="<?= old('phone') ?>" required autocomplete="tel">
            </div>
            <div class="field span-6">
              <label for="nok_phone">Next of Kin Phone Number *</label>
              <input class="input" type="tel" id="nok_phone" name="nok_phone" placeholder="e.g. 09053444178" value="<?= old('nok_phone') ?>" required>
              <span class="hint">Someone we can reach in an emergency during camp.</span>
            </div>

            <div class="field span-6">
              <label for="category">Category *</label>
              <select class="select" id="category" name="category" required>
                <?php foreach ($catList as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field span-6">
              <label for="zone">Zone *</label>
              <select class="select" id="zone" name="zone" required>
                <?php foreach ($zoneList as $zone): ?>
                  <option value="<?= e($zone) ?>" <?= $selectedZone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field span-12">
              <label for="allergies" style="color:#b45309">♥ Allergies / Existing Ailment *</label>
              <input class="input" id="allergies" name="allergies" placeholder="e.g. Asthma, allergies, ulcer — or type “None”" value="<?= old('allergies') ?>" required>
              <span class="hint">This is shown only to authorised welfare staff. It is never printed on your ID card.</span>
            </div>

            <div class="field span-12">
              <label for="nok_name">Next of Kin Name (optional)</label>
              <input class="input" id="nok_name" name="nok_name" placeholder="e.g. Mrs. Alao" value="<?= old('nok_name') ?>">
            </div>

            <div class="span-12">
              <label class="check" for="isMinor">
                <input type="checkbox" id="isMinor" name="is_minor" value="1" <?= old('is_minor') === '1' ? 'checked' : '' ?>>
                <span>This participant is under 18</span>
              </label>
            </div>

            <div class="span-12" id="guardianFields" hidden>
              <div class="form-grid" style="background:rgba(232,185,35,.12);border:1px solid rgba(232,185,35,.4);border-radius:14px;padding:16px">
                <div class="field span-6">
                  <label for="guardian_name">Guardian Name *</label>
                  <input class="input" id="guardian_name" name="guardian_name" placeholder="e.g. Mrs. Alao" value="<?= old('guardian_name') ?>">
                </div>
                <div class="field span-6">
                  <label for="guardian_contact">Guardian Phone/Email *</label>
                  <input class="input" id="guardian_contact" name="guardian_contact" placeholder="e.g. 09053444178" value="<?= old('guardian_contact') ?>">
                </div>
              </div>
            </div>

            <div class="span-12">
              <div class="form-section-title">Payment Option</div>
              <div class="pay-choice" style="margin-top:8px">
                <label>
                  <input type="radio" name="preferred_plan" value="FULL" data-plan checked>
                  <strong>Pay in Full</strong>
                  <span><?= money($fee) ?> once</span>
                </label>
                <label>
                  <input type="radio" name="preferred_plan" value="INSTALLMENT" data-plan>
                  <strong>Split Payments</strong>
                  <span><?= INSTALLMENT_COUNT ?> instalments</span>
                </label>
              </div>
              <p class="small muted" style="margin-top:7px">You can still choose or change your payment plan on the next page. Payment is completed securely after registration.</p>
            </div>

            <div class="span-12">
              <button class="btn btn-green btn-block" type="submit" style="margin-top:4px">Submit Registration →</button>
              <p class="small muted center" style="margin-top:9px">Already registered? <a href="<?= e(url('status')) ?>" style="color:var(--green);font-weight:800">Find your registration</a>.</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
