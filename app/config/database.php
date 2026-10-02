<?php

declare(strict_types=1);

$dbHost = 'localhost';
$dbPort = '8888';
$dbName = 'ecap';
$dbUser = 'root';
$dbPass = 'root';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    http_response_code(500);

    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        exit('Error DB: ' . $e->getMessage());
    }

    exit('No se pudo conectar con la base de datos.');
}