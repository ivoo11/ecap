<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Condiciones para la emisión de certificados | ECAP</title>
<link rel="icon" href="/assets/img/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
</head>
<body>
<header class="site-header"><div class="container header-inner"><a href="/" class="brand" aria-label="ECAP - Inicio"><img src="/assets/img/logoaz.png" alt="ECAP"></a><nav aria-label="Navegación principal"><a href="/#actividades">Actividades</a></nav></div></header>
<main class="activity-detail certificate-policy"><section class="detail-description"><div class="container detail-content-grid">
<?php // El contenido compartido conserva un único texto institucional.
ob_start(); require __DIR__.'/../includes/condiciones-certificados.php';
echo str_replace(['<h2>','</h2>'],['<h1>','</h1>'],ob_get_clean()); ?>
<p class="certificate-policy-note">Estas condiciones se comunican para las próximas actividades. No modifican los criterios aplicados a actividades ya realizadas.</p>
<a href="/#actividades">Ver actividades</a>
</div></section></main>
<footer class="site-footer"><div class="container footer-inner"><img src="/assets/img/logobc.png" alt="ECAP" class="footer-logo"><p>© <?= date('Y') ?> ECAP</p></div></footer>
</body></html>
