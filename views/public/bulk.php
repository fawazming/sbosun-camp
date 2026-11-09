<?php
$baseFee = current_fee();
$charge = gateway_charge();
$fee = (int) ($fee ?? ($baseFee + $charge));
?>
<main class="section" style="padding-top:34px">
  <div class="container">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
      <span class="brand-mark" style="background:var(--green);color:var(--gold);width:44px;height:44px;font-size:12px">SB</span>
      <div>
        <p style="font-size:14px;font-weight:900;color:var(--green)">Bulk registration &amp; payment</p>
        <p class="small muted"><?= e((string) setting('name', 'SB HIC 2026')) ?> · Standard Bearers Osun</p>
      </div>
      <a class="btn btn-outline-green btn-sm" style="margin-left:auto" href="<?= e(url('register')) ?>">← Single registration</a>
    </div>

    <div class="register-shell">
      <aside class="register-aside">
        <div>
          <span class="aside-tag">One payment · many seats</span>
          <h3 class="serif">Bring your family, school or group.</h3>
          <p class="aside-copy">Buy multiple camp seats in one payment, then share one unique completion link with each delegate. Each link works exactly once.</p>

          <div class="aside-feature">
            <span class="af-icon">1</span>
            <div><h5>Choose your seats</h5><p>Mix categories if you need to. Up to 50 seats per order.</p></div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">2</span>
            <div><h5>Pay once</h5><p>The server calculates the total from the current camp price. No browser totals are trusted.</p></div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">3</span>
            <div><h5>Share the links</h5><p>After payment is confirmed, your buyer portal shows one private link per delegate.</p></div>
          </div>
          <div class="aside-feature">
            <span class="af-icon">4</span>
            <div><h5>Each delegate completes</h5><p>Every delegate fills their own form, gets a registration ID, portal and printable ID card.</p></div>
          </div>
        </div>
        <div class="calc-box">
          <div class="calc-label">🧮 BULK TOTAL</div>
          <div class="calc-card">
            <div class="calc-row"><span>Camp fee per seat</span><strong><?= money($baseFee) ?></strong></div>
            <div class="calc-row"><span>Gateway charge per seat</span><span><?= money($charge) ?></span></div>
            <div class="calc-row"><span>Price per seat</span><strong><?= money($fee) ?></strong></div>
            <div class="calc-row"><span>Seats selected</span><strong data-bulk-seats>0</strong></div>
            <div class="calc-row gold"><span>Total to pay</span><span data-bulk-total><?= money(0) ?></span></div>
          </div>
        </div>
      </aside>

      <div class="register-body">
        <p class="form-section-title" style="margin-top:0">Step 1 of 2</p>
        <h4>Create your bulk order</h4>
        <p class="rb-sub">Enter the buyer contact and how many seats you need. Delegate details are collected later through the one-time links.</p>

        <form method="post" action="<?= e(url('bulk')) ?>" style="margin-top:18px" data-bulk-form data-price="<?= (int) $fee ?>">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field span-6">
              <label for="email">Buyer Email *</label>
              <input class="input" type="email" id="email" name="email" placeholder="name@example.com" value="<?= old('email') ?>" required autocomplete="email">
              <span class="hint">Receipt, buyer portal and delegate links are tied to this email.</span>
            </div>
            <div class="field span-6">
              <label for="phone">Buyer Phone / WhatsApp *</label>
              <input class="input" type="tel" id="phone" name="phone" placeholder="e.g. 08123456789" value="<?= old('phone') ?>" required autocomplete="tel">
            </div>

            <div class="span-12">
              <div class="form-section-title">Seats</div>
              <div style="border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-top:8px">
                <table class="table" style="min-width:0">
                  <thead><tr><th>Category</th><th>Class</th><th style="text-align:right">Price</th><th style="text-align:right">Quantity</th></tr></thead>
                  <tbody>
                    <?php foreach (categories() as $cat): $class = class_for_category($cat); ?>
                      <tr>
                        <td style="font-weight:700"><?= e($cat) ?></td>
                        <td class="small muted"><?= e($class['name'] ?? '—') ?></td>
                        <td style="text-align:right;white-space:nowrap"><?= money($fee) ?></td>
                        <td style="text-align:right">
                          <input class="input" type="number" name="qty[<?= e($cat) ?>]" value="<?= (int) ($_SESSION['_old']['qty'][$cat] ?? 0) ?>" min="0" max="50" step="1" style="width:82px;padding:8px 10px;text-align:center" data-bulk-qty>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <p class="small muted" style="margin-top:6px">Set quantities to zero for categories you do not need. Maximum 50 seats per order.</p>
            </div>

            <div class="span-12">
              <div class="notice notice-amber">Bulk orders are paid in full. Each seat includes a <?= money($charge) ?> gateway charge. Delegates who want an instalment plan should register individually.</div>
            </div>

            <div class="span-12">
              <button class="btn btn-green btn-block" type="submit">Continue to secure payment →</button>
              <p class="small muted center" style="margin-top:9px">After payment, open your buyer portal to copy each delegate’s unique completion link.</p>
              <p class="small muted center" style="margin-top:5px">Already paid? <a href="<?= e(url('status')) ?>" style="color:var(--green);font-weight:800">Find registration</a> using your payment reference and buyer email.</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
