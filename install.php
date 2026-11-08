<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
$already = db_ready();

if ($isCli) {
    $options = getopt('', ['email::', 'password::', 'name::']);
    $result = install_database(
        (string) ($options['email'] ?? ''),
        (string) ($options['password'] ?? ''),
        (string) ($options['name'] ?? 'Super Admin')
    );
    echo "SB Osun Camp Portal database installed.\n";
    if ($result['admin_created']) {
        echo "Super Admin created.\n";
        if ($result['temp_password']) {
            echo "Temporary password: {$result['temp_password']}\n";
        }
    } else {
        echo "Admin account already existed — left unchanged.\n";
    }
    echo "Installed. Open the portal in your browser (for XAMPP: http://localhost/sbosun-camp/).\n";
    exit(0);
}

if ($already) {
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Already installed</title>'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#F7F3E8;display:grid;place-items:center;min-height:100vh;margin:0;color:#14170F}.card{background:#fff;border-radius:24px;padding:32px;max-width:480px;box-shadow:0 24px 60px rgba(10,42,32,.12)}a{color:#0F3D2E;font-weight:700}</style></head><body>'
        . '<div class="card"><h1 style="color:#0F3D2E">Portal already installed</h1><p>The database schema exists. For safety this installer will not run again.</p>'
        . '<p><a href="' . e(url('home')) . '">Go to the camp portal</a> · <a href="' . e(url('admin/login')) . '">Admin sign in</a></p></div></body></html>';
    exit;
}

$error = null;
$success = null;
if (is_post()) {
    verify_csrf();
    $name = trim((string) post('name', 'Super Admin'));
    $email = strtolower(trim((string) post('email')));
    $password = (string) post('password');

    if (!valid_email($email)) {
        $error = 'Enter a valid administrator email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password of at least 8 characters.';
    } else {
        try {
            $result = install_database($email, $password, $name);
            $success = ['email' => $email];
        } catch (Throwable $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — SB Osun Camp Portal</title>
<style>
:root{--green:#0F3D2E;--dark:#0A2A20;--gold:#E8B923;--cream:#F7F3E8;--muted:#4B5347}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--cream);font-family:Arial,Helvetica,sans-serif;color:#14170F;padding:24px}
.card{width:100%;max-width:520px;background:#fff;border:1px solid rgba(15,61,46,.12);border-radius:28px;padding:34px;box-shadow:0 28px 70px rgba(10,42,32,.14)}
.logo{width:46px;height:46px;border-radius:50%;background:var(--gold);color:var(--dark);display:grid;place-items:center;font-weight:900;margin-bottom:16px}
h1{font-size:26px;line-height:1.15;margin:0 0 8px;color:var(--green)}p{line-height:1.6;color:var(--muted);font-size:14px}
label{display:block;font-size:12px;font-weight:800;margin:16px 0 6px;color:#14170F}
input{width:100%;padding:13px 14px;border:1px solid rgba(15,61,46,.18);border-radius:12px;font-size:14px;outline:none}
input:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(232,185,35,.28)}
button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:999px;background:var(--green);color:var(--cream);font-size:14px;font-weight:800;cursor:pointer}
button:hover{background:#0c2f1d}.alert{margin:16px 0;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:700}
.err{background:#fee2e2;color:#b91c1c}.ok{background:#dcfce7;color:#15803d}
a{color:var(--green);font-weight:700}.note{background:var(--cream);padding:14px 16px;border-radius:14px;font-size:12px;margin-top:18px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">SB</div>
  <h1>Set up your camp portal</h1>
  <p>One-time installer for <strong>SB HIC 2026</strong> — Standard Bearers Islamic Organization, Osun State.</p>

  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

  <?php if ($success): ?>
    <div class="alert ok">Installation complete. Your Super Admin account is ready.</div>
    <p>Sign in with <strong><?= e($success['email']) ?></strong> using the password you just chose.</p>
    <p><a href="<?= e(url('admin/login')) ?>">Go to admin sign in →</a><br><a href="<?= e(url('home')) ?>">Open the public portal →</a></p>
  <?php else: ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label for="name">Your name</label>
      <input id="name" name="name" value="Super Admin" required>
      <label for="email">Admin email</label>
      <input id="email" type="email" name="email" placeholder="admin@sbosun.org" required>
      <label for="password">Admin password (min. 8 characters)</label>
      <input id="password" type="password" name="password" minlength="8" required>
      <button type="submit">Install the portal</button>
    </form>
    <div class="note">The installer creates the database tables, classes, houses and your first Super Admin account. It is idempotent and will not run again once installed.</div>
  <?php endif; ?>
</div>
</body>
</html>
