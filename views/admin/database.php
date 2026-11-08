<?php
$query = $_GET; unset($query['r'], $query['page'], $query['table']);
?>
<div class="admin-page-head">
  <div>
    <p class="eyebrow">Super Admin · read-only</p>
    <h1>Database viewer</h1>
    <p class="sub">Browse every table in the live <?= e((string) config('db.driver')) ?> database. Write statements are blocked.</p>
  </div>
  <div class="bulk-actions">
    <span class="badge badge-slate"><?= count($tables) ?> tables</span>
    <span class="badge badge-amber">Read-only</span>
  </div>
</div>

<div class="admin-grid admin-grid-3" style="align-items:start">
  <section class="panel" style="grid-column:span 1">
    <h2>Tables</h2>
    <div style="display:flex;flex-direction:column;gap:3px;margin-top:8px">
      <?php foreach ($tables as $table): ?>
        <a href="<?= e(url('admin/database', ['table' => $table])) ?>" style="display:flex;justify-content:space-between;gap:8px;padding:7px 9px;border-radius:8px;text-decoration:none;font-size:11.5px;<?= $selected === $table ? 'background:var(--green);color:var(--cream);font-weight:800' : 'color:var(--green)' ?>">
          <span class="mono"><?= e($table) ?></span>
          <span style="opacity:.75"><?= number_format(db_table_row_count($table)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" style="grid-column:span 2">
    <?php if ($selected !== ''): ?>
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center">
        <div><h2 style="margin:0" class="mono"><?= e($selected) ?></h2><p class="small muted"><?= number_format($total) ?> row<?= $total === 1 ? '' : 's' ?> · showing 40 per page</p></div>
        <a class="btn btn-outline-green btn-sm" href="<?= e(url('admin/database', ['table' => $selected])) ?>">Refresh</a>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:5px;margin:12px 0">
        <?php foreach ($columnsMeta as $col): $cname = $col['name'] ?? $col['Field'] ?? ''; $ctype = $col['type'] ?? $col['Type'] ?? ''; $pk = !empty($col['pk']) || (($col['Key'] ?? '') === 'PRI'); ?>
          <span class="badge <?= $pk ? 'badge-gold' : 'badge-slate' ?>" style="font-family:ui-monospace,monospace;font-size:9.5px"><?= e($cname) ?><?= $ctype ? ' · ' . e(mb_strimwidth($ctype, 0, 24, '…')) : '' ?><?= $pk ? ' PK' : '' ?></span>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap" style="border:1px solid var(--line)">
        <table class="table" style="min-width:<?= max(680, count($rows ? array_keys($rows[0]) : ($columnsMeta ? array_keys($columnsMeta[0]) : [])) * 150) ?>px">
          <thead><tr>
            <?php if ($rows): foreach (array_keys($rows[0]) as $column): ?><th><?= e($column) ?></th><?php endforeach; endif; ?>
          </tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td class="empty">This table is empty.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($row as $value): ?>
                  <td style="font-size:10.5px;max-width:320px;word-break:break-word"><?= e(mb_strimwidth((string) ($value ?? ''), 0, 140, '…')) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?><a href="<?= e(url('admin/database', array_merge($query, ['table' => $selected, 'page' => $page - 1]))) ?>">← Prev</a><?php endif; ?>
          <span class="current"><?= $page ?></span>
          <span style="border:0;background:none;color:var(--muted)">of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e(url('admin/database', array_merge($query, ['table' => $selected, 'page' => $page + 1]))) ?>">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="empty">Choose a table on the left.</p>
    <?php endif; ?>
  </section>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Read-only SQL</h2>
  <p class="small muted" style="margin-bottom:10px">SELECT / WITH … SELECT only. Results are capped at 200 rows. Password hashes are masked in the table browser; this console may still return them, so handle the output carefully.</p>
  <form method="post" action="<?= e(url('admin/database/query')) ?>">
    <?= csrf_field() ?>
    <textarea class="textarea mono" name="sql" placeholder="SELECT registration_id, email, payment_status FROM participants ORDER BY id DESC" style="min-height:110px;font-size:12px"><?= $queryResult ? e($queryResult['sql']) : '' ?></textarea>
    <button class="btn btn-green btn-sm" type="submit" style="margin-top:9px">Run query</button>
  </form>

  <?php if ($queryResult): ?>
    <?php if ($queryResult['error']): ?>
      <div class="notice notice-red" style="margin-top:12px"><?= e($queryResult['error']) ?></div>
    <?php elseif (!$queryResult['rows']): ?>
      <p class="notice notice-amber" style="margin-top:12px">Query ran successfully and returned no rows.</p>
    <?php else: ?>
      <div class="table-wrap" style="margin-top:12px">
        <table class="table" style="min-width:640px">
          <thead><tr><?php foreach ($queryResult['columns'] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($queryResult['rows'] as $row): ?>
              <tr><?php foreach ($queryResult['columns'] as $column): ?><td style="font-size:10.5px;max-width:320px;word-break:break-word"><?= e(mb_strimwidth((string) ($row[$column] ?? ''), 0, 160, '…')) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="small muted" style="margin-top:6px"><?= count($queryResult['rows']) ?> row(s) returned.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
