<div class="admin-page-head">
  <div>
    <p class="eyebrow">Exports</p>
    <h1>Reports</h1>
    <p class="sub">Download live data as CSV, ready for Excel or Google Sheets.</p>
  </div>
</div>

<div class="admin-grid admin-grid-2">
  <?php
  $reports = [
      ['registrations', 'Registration report', 'Participant, gender, age group, category, class, zone, payment and attendance.'],
      ['payments', 'Payment report', 'Participant, amount, payment type, instalment number and status.'],
      ['attendance', 'Attendance report', 'Participant, class, house, check-in status, time and staff member.'],
      ['houses', 'House report', 'House, gender, capacity, occupants and available spaces.'],
      ['newsletter', 'Newsletter report', 'Subscriber email, name, status, source and dates.'],
  ];
  foreach ($reports as [$type, $title, $desc]): ?>
    <section class="panel">
      <h2><?= e($title) ?></h2>
      <p class="small muted" style="margin:4px 0 14px"><?= e($desc) ?></p>
      <a class="btn btn-green btn-sm" href="<?= e(url('admin/reports/export', ['type' => $type])) ?>">Download CSV</a>
    </section>
  <?php endforeach; ?>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Data safety</h2>
  <p class="small muted">Exports include protected welfare fields for authorised staff. Store downloaded CSV files securely and delete them after use. Every export is written to the audit log with the actor, time and IP address.</p>
</section>
