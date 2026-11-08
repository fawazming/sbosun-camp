<?php
$success = flash('success');
$error = flash('error');
$info = flash('info');
$currentYear = date('Y');
$programmeName = setting('name', 'SB HIC 2026');
$registrationOpen = (string) setting('registration_open', '1') === '1' && (string) setting('maintenance_mode', '0') !== '1';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $programmeName) ?></title>
<meta name="description" content="Register for <?= e($programmeName) ?> — a five-day Holiday Islamic Course by Standard Bearers Islamic Organization, Osun State. <?= e(camp_dates()) ?> at <?= e((string) setting('venue_name')) ?>, <?= e((string) setting('venue_address')) ?>.">
<meta name="theme-color" content="#0F3D2E">
<meta property="og:title" content="<?= e($programmeName) ?> — SB Osun">
<meta property="og:description" content="Five days of learning, brotherhood and sisterhood. <?= e(camp_dates()) ?>.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='30' fill='%230F3D2E'/%3E%3Ctext x='32' y='40' font-family='Arial' font-size='22' font-weight='900' fill='%23E8B923' text-anchor='middle'%3ESB%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<noscript><style>[data-reveal]{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>
<header class="site-header">
  <div class="container">
    <div class="nav-inner">
      <a class="brand" href="<?= e(url('home')) ?>">
        <span class="brand-mark">SB</span>
        <span class="brand-name">Standard Bearers<small class="brand-small">Islamic Organization · Osun State</small></span>
      </a>
      <nav class="site-nav" aria-label="Main navigation">
        <a href="<?= e(url('home')) ?>#classes">Classes</a>
        <a href="<?= e(url('home')) ?>#highlights">Camp life</a>
        <a href="<?= e(url('home')) ?>#gallery">Gallery</a>
        <a href="<?= e(url('home')) ?>#lecturers">Faculty</a>
        <a href="<?= e(url('bulk')) ?>">Bulk tickets</a>
        <a href="<?= e(url('status')) ?>">Find registration</a>
      </nav>
      <div class="nav-actions">
        <a class="btn btn-gold btn-sm" href="<?= e($registrationOpen ? url('register') : url('status')) ?>"><?= $registrationOpen ? 'Register for Camp' : 'Registration status' ?></a>
        <button class="mobile-toggle" type="button" data-mobile-toggle="mobileNav" aria-label="Open menu">☰</button>
      </div>
    </div>
    <nav class="mobile-nav" id="mobileNav" aria-label="Mobile navigation">
      <a href="<?= e(url('home')) ?>#classes">Classes</a>
      <a href="<?= e(url('home')) ?>#highlights">Camp life</a>
      <a href="<?= e(url('home')) ?>#gallery">Gallery</a>
      <a href="<?= e(url('home')) ?>#lecturers">Faculty</a>
      <a href="<?= e(url('bulk')) ?>">Bulk tickets</a>
      <a href="<?= e(url('status')) ?>">Find registration</a>
      <a href="<?= e(url('admin/login')) ?>">Admin sign in</a>
    </nav>
  </div>
</header>

<?php if ($success || $error || $info): ?>
<div class="flash-wrap">
  <div class="container">
    <?php if ($success): ?><div class="flash flash-success">✓ <span><?= e($success) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="flash flash-error">! <span><?= e($error) ?></span></div><?php endif; ?>
    <?php if ($info): ?><div class="flash flash-info">i <span><?= e($info) ?></span></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?= $content ?>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <p class="footer-title">Standard Bearers <span><?= e(setting('name', 'SB HIC 2026')) ?></span></p>
        <p class="footer-copy">Upholding the standard — a Holiday Islamic Course for students and young adults across Osun State and beyond.</p>
        <form class="newsletter-form" style="margin-top:18px;max-width:420px" method="post" action="<?= e(url('newsletter')) ?>" data-newsletter-form>
          <?= csrf_field() ?>
          <input class="input" type="email" name="email" placeholder="Email for camp updates" aria-label="Email address" required>
          <button class="btn btn-gold btn-sm" type="submit">Subscribe</button>
        </form>
        <p class="small" data-newsletter-message style="margin-top:6px;color:var(--sage)"></p>
      </div>
      <div>
        <h4 class="footer-heading">Enquiries</h4>
        <ul class="enquiries">
          <?php foreach (config('enquiries') as [$state, $number]): ?>
            <li><strong><?= e($state) ?>:</strong> <?= e($number) ?></li>
          <?php endforeach; ?>
        </ul>
        <div style="margin-top:14px;font-size:12px;color:var(--sage)">
          <div><strong style="color:var(--cream)">Dates:</strong> <?= e(camp_dates()) ?></div>
          <div><strong style="color:var(--cream)">Venue:</strong> <?= e((string) setting('venue_name')) ?>, <?= e((string) setting('venue_address')) ?></div>
          <div><strong style="color:var(--cream)">Support:</strong> <a style="color:var(--gold)" href="mailto:<?= e((string) setting('support_email')) ?>"><?= e((string) setting('support_email')) ?></a> · <?= e((string) setting('support_phone')) ?></div>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= e((string) $currentYear) ?> Standard Bearers Islamic Organization, Osun State</span>
      <span>X @SbOsun_State · Instagram @sbosun_state · Facebook StandardBearersIO</span>
      <span><a href="<?= e(url('admin/login')) ?>" style="color:var(--sage);text-decoration:underline">Staff sign in</a></span>
    </div>
  </div>
</footer>

<div class="player-float">
  <audio id="memoryAudio" src="<?= e(asset('memory_lane.mp3')) ?>" loop preload="none"></audio>
  <button class="memory-button" id="memoryBtn" type="button" aria-label="Play Memory Lane audio">
    <span aria-hidden="true">▶</span>
    <span data-memory-label>Play Memory Lane</span>
    <span class="wave" aria-hidden="true"><i></i><i></i><i></i></span>
  </button>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
