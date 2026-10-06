<?php

declare(strict_types=1);

require_once __DIR__ . '/services/ReachService.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $reach = new ReachService();
    $profile = $reach->getProfile();

    echo "ReachService OK\n";
    echo "Perfil: " . ($profile['brand_name'] ?? '—') . "\n";
    echo "Dominio: " . ($profile['domain'] ?? '—') . "\n";
    echo "UUID correcto: "
        . (($profile['uuid'] ?? '') === 'da149b0a-e7ad-42e7-b324-07e443d13855' ? 'SI' : 'NO')
        . "\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}