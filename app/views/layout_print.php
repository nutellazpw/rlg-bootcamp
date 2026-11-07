<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Print') ?> · RLG Bootcamp</title>
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body>
<div class="toolbar no-print">
    <a href="<?= e(url('/bootcamps/' . (int) $b['id'])) ?>">← Back to bootcamp</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>
<div class="sheet">
    <header class="doc-head">
        <img src="<?= e(asset('img/logo-dark.svg')) ?>" alt="Relog Media" height="34">
        <div class="doc-meta">
            <div class="doc-type"><?= e($docType ?? '') ?></div>
            <div>Bootcamp #<?= (int) $b['id'] ?> · printed <?= e(date('d M Y H:i')) ?></div>
        </div>
    </header>
    <?= $content ?>
    <footer class="doc-foot">RLG Bootcamp · Relog Media</footer>
</div>
</body>
</html>
