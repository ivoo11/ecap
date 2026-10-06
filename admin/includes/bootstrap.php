<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ECAP Admin Bootstrap
|--------------------------------------------------------------------------
|
| Punto de entrada común para todas las pantallas privadas del Admin.
|
| Centraliza:
| - conexión a base de datos;
| - autenticación;
| - protección CSRF;
| - vigencia de sesión administrativa;
| - autorización de acceso;
| - usuario autenticado.
|
*/

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/../../app/auth/Csrf.php';

Session::start();

/*
|--------------------------------------------------------------------------
| Validar sesión administrativa
|--------------------------------------------------------------------------
*/

if (!Auth::validateAdminSession()) {
    header('Location: /admin/login.php?expired=1');
    exit;
}

/*
|--------------------------------------------------------------------------
| Autorizar acceso
|--------------------------------------------------------------------------
*/

if (!Auth::canAccessAdmin()) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

/*
|--------------------------------------------------------------------------
| Usuario actual
|--------------------------------------------------------------------------
*/

$usuario = Auth::user();

if ($usuario === null) {
    header('Location: /admin/login.php');
    exit;
}