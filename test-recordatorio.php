<?php

declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/services/MailService.php';

$mailService = new MailService();

try {

    $mailService->enviarRecordatorioClase(
        'ivanmontano87@gmail.com',
        'Ivo',
        'El procedimiento probatorio y la prueba científica',
        '2026-10-08 14:00:00',
        'online',
        'https://zoom.us/j/123456789',
        '123 456 789',
        'ECAP2026'
    );

    echo 'OK - Recordatorio enviado.';

} catch (Throwable $e) {

    echo 'ERROR - ';
    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );
}