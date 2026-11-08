<div class="admin-page-head">
  <div>
    <p class="eyebrow">Accommodation</p>
    <h1>Houses</h1>
    <p class="sub">Gender-aware house capacity, automatic placement and manual overrides.</p>
  </div>
  <div class="bulk-actions">
    <details>
      <summary class="btn btn-outline-green btn-sm" style="list-style:none;cursor:pointer">+ New house</summary>
      <form method="post" action="<?= e(url('admin/houses/action')) ?>" class="panel" style="position:absolute;z-index:20;margin-top:8px;width:280px;box-shadow:var(--shadow-lg)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field" style="margin-bottom:8px"><label>House name</label><input class="input" name="name" placeholder="e.g. Umar House" required></div>
        <div class="field" style="margin-bottom:8px"><label>Gender</label><select class="select" name="gender"><option value="MALE">Male</option><option value="FEMALE">Female</option></select></div>
        <div class="field" style="margin-bottom:8px"><label>Capacity</label><input class="input" type="number" name="capacity" value="40" min="1"></div>
        <button class="btn btn-gold btn-sm btn-block" type="submit">Create house</button>
      </form>
    </details>
    <form method="post" action="<?= e(url('admin/houses/action')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="auto_assign">
      <button class="btn btn-green btn-sm" type="submit" <?= $unassigned ? '' : 'disabled' ?>>Auto-assign (<?= count($unassigned) ?> pending)</button>
    </form>
  </div>
</div>

<div class="admin-grid admin-grid-3">
  <?php foreach ($houses as $h): $occ = (int) $h['occupants']; $cap = (int) $h['capacity']; $pct = $cap > 0 ? round(($occ / $cap) * 100) : 0; ?>
    <section class="panel">
      <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
        <div><h3 style="margin:0"><?= e($h['name']) ?></h3><p class="small muted"><?= e($h['gender'] === 'MALE' ? 'Male house' : 'Female house') ?></p></div>
        <span class="<?= e($h['status'] === 'ACTIVE' ? 'badge badge-green' : 'badge badge-slate') ?>"><?= e($h['status']) ?></span>
      </div>
      <div class="progress" style="margin:12px 0 7px"><span style="width:<?= $pct ?>%"></span></div>
      <p class="small" style="font-weight:700"><?= $occ ?> / <?= $cap ?> occupants · <?= max(0, $cap - $occ) ?> available</p>
      <p class="small muted" style="margin-top:3px"><?= $pct ?>% full</p>
      <form method="post" action="<?= e(url('admin/houses/action')) ?>" style="margin-top:9px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="house_id" value="<?= (int) $h['id'] ?>">
        <button class="btn btn-outline-green btn-sm" type="submit"><?= $h['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
      </form>
    </section>
  <?php endforeach; ?>
  <?php if (!$houses): ?><div class="panel"><p class="empty">No houses yet. Create the first one.</p></div><?php endif; ?>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Paid but unassigned (<?= count($unassigned) ?>)</h2>
  <?php if (!$unassigned): ?>
    <p class="empty">Every fully-paid delegate has a house. 🎉</p>
  <?php else: ?>
    <?php foreach ($unassigned as $p): $options = array_values(array_filter($houses, static fn(array $h): bool => $h['gender'] === $p['gender'] && $h['status'] === 'ACTIVE' && ((int) $h['capacity'] > (int) $h['occupants']))); ?>
      <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #f1f5f0;padding:9px 0">
        <div>
          <p style="font-size:12.5px;font-weight:800"><?= e(delegate_name($p)) ?></p>
          <p class="small muted"><?= e($p['registration_id']) ?> · <?= e($p['gender'] === 'MALE' ? 'Male' : 'Female') ?></p>
        </div>
        <form method="post" action="<?= e(url('admin/houses/action')) ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="assign">
          <input type="hidden" name="participant_id" value="<?= (int) $p['id'] ?>">
          <select class="select" name="house_id" required style="font-size:11px;padding:7px 9px;width:auto">
            <option value="">Select house…</option>
            <?php foreach ($options as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?> (<?= max(0, (int) $h['capacity'] - (int) $h['occupants']) ?> free)</option><?php endforeach; ?>
          </select>
          <button class="btn btn-green btn-sm" type="submit">Assign</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
