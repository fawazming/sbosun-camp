<?php
$success = flash('success');
$error = flash('error');
$info = flash('info');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title ?? 'Admin') . ' — SB Osun') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?= $content ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
