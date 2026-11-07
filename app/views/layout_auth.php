<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?>RLG Bootcamp</title>
<meta name="theme-color" content="#0b0b0b">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-brand">
        <img src="<?= e(asset('img/logo.svg')) ?>" alt="Relog Media" width="200" height="45">
        <span class="brand-tag">Bootcamp</span>
    </div>
    <div class="auth-card">
        <?php foreach (take_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= $f['message'] ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </div>
    <p class="auth-foot">Access is by invitation only. Contact your Relog Media bootcamp manager.</p>
</div>
</body>
</html>
