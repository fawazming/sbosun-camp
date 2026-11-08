<?php
$mode = $mode ?? 'single';
$formAction = $formAction ?? url('complete/' . $participant['token']);
$category = $category ?? ($participant['category'] ?? '');
$prefill = $prefill ?? ['email' => $participant['email'] ?? '', 'phone' => $participant['phone'] ?? ''];
$v = static fn(string $key, string $default = ''): string => e($_SESSION['_old'][$key] ?? $default);
$balance = max(0, (int) $owed - (int) $paid);
?>
<main class="section" style="padding-top:34px">
  <div class="container">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
      <span class="brand-mark" style="background:var(--green);color:var(--gold);width:44px;height:44px;font-size:12px">SB</span>
      <div>
        <p style="font-size:14px;font-weight:900;color:var(--green)">Step 2 of 2 · Delegate details</p>
        <p class="small muted">Payment confirmed — finish your registration below.</p>
      </div>
      <a class="btn btn-outline-green btn-sm" style="margin-left:auto" href="<?= e($mode === 'bulk' ? url('bulk/' . $order['token']) : url('portal/' . $participant['token'])) ?>">← Back to portal</a>
    </div>

    <div class="register-shell">
      <aside class="register-aside">
        <div>
          <span class="aside-tag">✓ Payment confirmed</span>
          <h3 class="serif">Now, tell us who you are.</h3>
          <p class="aside-copy">Your seat is secured. Complete the form accurately — this is the information used for your class, house, check-in and ID card.</p>

          <div class="aside-feature">
            <span class="af-icon">🎓</span>
            <div>
              <h5>Category &amp; class</h5>
              <p><?= e($category) ?> · <?= e($class['name'] ?? 'Class assigned automatically') ?></p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">💳</span>
            <div>
              <h5>Payment</h5>
              <p><?= $mode === 'bulk' ? 'This seat is fully paid through the bulk order.' : 'Total ' . money((int) $owed) . ' · paid ' . money((int) $paid) . ($balance > 0 ? ' · balance ' . money($balance) : '') ?></p>
            </div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">♥</span>
            <div>
              <h5>Welfare protected</h5>
              <p>Next-of-kin and allergy details are visible only to authorised camp staff and never printed on your ID card.</p>
            </div>
          </div>
        </div>

        <div class="calc-box">
          <div class="calc-label">🧾 REGISTRATION SUMMARY</div>
          <div class="calc-card">
            <div class="calc-row"><span>Category</span><strong><?= e($category) ?></strong></div>
            <div class="calc-row"><span>Class</span><span><?= e($class['name'] ?? 'Auto-assigned') ?></span></div>
            <div class="calc-row"><span>Amount paid</span><span><?= money((int) $paid) ?></span></div>
            <?php if ($balance > 0): ?><div class="calc-row gold"><span>Balance</span><span><?= money($balance) ?></span></div><?php endif; ?>
            <div class="calc-row"><span>Camp dates</span><span><?= e(camp_dates()) ?></span></div>
          </div>
        </div>
      </aside>

      <div class="register-body">
        <p class="form-section-title" style="margin-top:0">Delegate Registration Form</p>
        <h4>Complete your details</h4>
        <p class="rb-sub">Fields marked * are required. You can return to your portal later to view your status and ID card.</p>

        <form method="post" action="<?= e($formAction) ?>" style="margin-top:20px">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field span-4">
              <label for="surname">Surname *</label>
              <input class="input" id="surname" name="surname" placeholder="e.g. Alao" value="<?= $v('surname', $participant['surname'] ?? '') ?>" required autocomplete="family-name">
            </div>
            <div class="field span-4">
              <label for="other_name">Other Name *</label>
              <input class="input" id="other_name" name="other_name" placeholder="e.g. Ibrahim Adebayo" value="<?= $v('other_name', $participant['other_name'] ?? '') ?>" required autocomplete="given-name">
            </div>
            <div class="field span-4">
              <label for="gender">Sex *</label>
              <select class="select" id="gender" name="gender" required>
                <option value="MALE" <?= ($_SESSION['_old']['gender'] ?? ($participant['gender'] ?? '')) === 'FEMALE' ? '' : 'selected' ?>>Brother (Male)</option>
                <option value="FEMALE" <?= ($_SESSION['_old']['gender'] ?? ($participant['gender'] ?? '')) === 'FEMALE' ? 'selected' : '' ?>>Sister (Female)</option>
              </select>
            </div>

            <div class="field span-4">
              <label for="age_group">Age Bracket *</label>
              <select class="select" id="age_group" name="age_group" required>
                <?php $selectedAge = $_SESSION['_old']['age_group'] ?? ($participant['age_group'] ?? '10 - 20'); ?>
                <?php foreach (age_groups() as $age): ?>
                  <option value="<?= e($age) ?>" <?= $selectedAge === $age ? 'selected' : '' ?>><?= e($age) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field span-8">
              <label for="email">Email Address *</label>
              <input class="input" type="email" id="email" name="email" value="<?= $v('email', $participant['email'] ?? $prefill['email']) ?>" required autocomplete="email">
              <span class="hint">Used for your receipt and ID card link.</span>
            </div>

            <div class="field span-6">
              <label for="phone">Phone / WhatsApp *</label>
              <input class="input" type="tel" id="phone" name="phone" value="<?= $v('phone', $participant['phone'] ?? $prefill['phone']) ?>" required autocomplete="tel">
            </div>
            <div class="field span-6">
              <label for="nok_phone">Next of Kin Phone Number *</label>
              <input class="input" type="tel" id="nok_phone" name="nok_phone" placeholder="e.g. 09053444178" value="<?= $v('nok_phone', $participant['nok_phone'] ?? '') ?>" required>
            </div>

            <div class="field span-6">
              <label for="zone">Zone *</label>
              <select class="select" id="zone" name="zone" required>
                <?php $selectedZone = $_SESSION['_old']['zone'] ?? ($participant['zone'] ?? 'Osogbo'); ?>
                <?php foreach (zones() as $zone): ?>
                  <option value="<?= e($zone) ?>" <?= $selectedZone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field span-6">
              <label for="nok_name">Next of Kin Name (optional)</label>
              <input class="input" id="nok_name" name="nok_name" placeholder="e.g. Mrs. Alao" value="<?= $v('nok_name', $participant['nok_name'] ?? '') ?>">
            </div>

            <div class="field span-12">
              <label for="allergies" style="color:#b45309">♥ Allergies / Existing Ailment *</label>
              <input class="input" id="allergies" name="allergies" placeholder="e.g. Asthma, allergies, ulcer — or type “None”" value="<?= $v('allergies', $participant['allergies'] ?? '') ?>" required>
              <span class="hint">Shown only to authorised welfare staff. Never printed on your ID card.</span>
            </div>
            <div class="field span-12">
              <label for="health_challenges">Other health notes (optional)</label>
              <input class="input" id="health_challenges" name="health_challenges" placeholder="Anything else the welfare team should know" value="<?= $v('health_challenges', $participant['health_challenges'] ?? '') ?>">
            </div>

            <div class="span-12">
              <label class="check" for="isMinor">
                <input type="checkbox" id="isMinor" name="is_minor" value="1" <?= ($_SESSION['_old']['is_minor'] ?? $participant['is_minor'] ?? '') === '1' ? 'checked' : '' ?>>
                <span>This participant is under 18</span>
              </label>
            </div>
            <div class="span-12" id="guardianFields" <?= ($_SESSION['_old']['is_minor'] ?? $participant['is_minor'] ?? '') == '1' ? '' : 'hidden' ?>>
              <div class="form-grid" style="background:rgba(232,185,35,.12);border:1px solid rgba(232,185,35,.4);border-radius:14px;padding:16px">
                <div class="field span-6">
                  <label for="guardian_name">Guardian Name *</label>
                  <input class="input" id="guardian_name" name="guardian_name" placeholder="e.g. Mrs. Alao" value="<?= $v('guardian_name', $participant['guardian_name'] ?? '') ?>">
                </div>
                <div class="field span-6">
                  <label for="guardian_contact">Guardian Phone/Email *</label>
                  <input class="input" id="guardian_contact" name="guardian_contact" placeholder="e.g. 09053444178" value="<?= $v('guardian_contact', $participant['guardian_contact'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="span-12">
              <button class="btn btn-green btn-block" type="submit" style="margin-top:4px">Save details &amp; open my portal →</button>
              <p class="small muted center" style="margin-top:9px">Your data is used for camp administration only. Welfare notes are restricted to authorised staff.</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
