<?php

declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

$entorno = getenv('ECAP_ENV');

$isLocal =
    $entorno === 'local'
    ||
    in_array(
        $_SERVER['SERVER_NAME'] ?? '',
        ['localhost', '127.0.0.1'],
        true
    );

$configFile = $isLocal
    ? __DIR__ . '/database.local.php'
    : __DIR__ . '/database.production.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuración de base de datos no disponible.');
}

$config = require $configFile;

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['name']
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        $options
    );

    $pdo->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {

    http_response_code(500);

    if ($isLocal) {
        exit('Error DB: ' . $e->getMessage());
    }

    error_log(
        'Error de conexión DB ECAP: ' .
        $e->getMessage()
    );

    exit('No se pudo conectar con la base de datos.');
}