<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/config/database.php';


$query = trim($_GET['q'] ?? '');


if (mb_strlen($query) < 2) {

    echo json_encode([
        'ok' => true,
        'universidades' => []
    ]);

    exit;
}


$stmt = $pdo->prepare("
    SELECT
        id,
        nombre,
        nombre_corto

    FROM universidades

    WHERE
        activa = 1
        AND (
            nombre LIKE :query_nombre
            OR nombre_corto LIKE :query_corto
        )

    ORDER BY nombre ASC

    LIMIT 15
");


$search = '%' . $query . '%';


$stmt->execute([
    'query_nombre' => $search,
    'query_corto' => $search
]);


echo json_encode([
    'ok' => true,
    'universidades' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);