<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/config/database.php';

try {

    $stmt = $pdo->query("
        SELECT
            id,
            nombre
        FROM ambitos_profesionales
        WHERE activo = 1
        ORDER BY id ASC
    ");

    echo json_encode([
        'ok' => true,
        'ambitos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'ambitos' => []
    ]);
}