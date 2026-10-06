<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/Auth.php';
require_once __DIR__ . '/../app/auth/Csrf.php';

Session::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

if (!Csrf::validate($_POST['_csrf'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

Auth::logout();

header('Location: login.php');
exit;