<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ECAP Admin — Dashboard
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/bootstrap.php';

require_once __DIR__.'/../app/services/CorreoControl.php';
$correoControl=new CorreoControl($pdo);
$correosPendientes=$correoControl->pendientes();
$cronCorreo=$correoControl->cron();
$pageTitle = 'Inicio';
$pageSection = 'inicio';

require __DIR__ . '/includes/header.php';

require_once __DIR__.'/../app/services/CorreoPanel.php';
$resumenRecordatorios=(new CorreoPanel($pdo))->recordatoriosHoy();
require __DIR__.'/includes/inicio-contenido.php';
require __DIR__.'/includes/footer.php';
