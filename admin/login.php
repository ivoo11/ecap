<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/Auth.php';
require_once __DIR__ . '/../app/auth/Csrf.php';

Session::start();

/*
|--------------------------------------------------------------------------
| Estado de sesión
|--------------------------------------------------------------------------
*/

$sessionExpired = isset($_GET['expired']);

/*
|--------------------------------------------------------------------------
| Usuario ya autenticado
|--------------------------------------------------------------------------
|
| Si existe una sesión administrativa vigente, no mostramos nuevamente
| el formulario de acceso.
|
*/

if (Auth::check()) {
    if (Auth::validateAdminSession() && Auth::canAccessAdmin()) {
        header('Location: index.php');
        exit;
    }

    /*
    | Si existía una sesión pero dejó de ser válida,
    | validateAdminSession() ya se ocupa de destruirla.
    */
}

$error = null;
$dni = '';

/*
|--------------------------------------------------------------------------
| Procesar login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'La solicitud no es válida. Volvé a intentarlo.';
    } elseif ($dni === '' || $password === '') {
        $error = 'Ingresá tu DNI y contraseña.';
    } elseif (Auth::attempt($pdo, $dni, $password)) {
        Csrf::regenerate();

        if (!Auth::canAccessAdmin()) {
            Auth::logout();

            $error = 'No tenés permisos para acceder al administrador.';
        } else {
            header('Location: index.php');
            exit;
        }
    } else {
        /*
        |--------------------------------------------------------------------------
        | Error deliberadamente genérico
        |--------------------------------------------------------------------------
        |
        | No revelamos si existe el DNI, si existe una cuenta asociada,
        | si está bloqueada o si la contraseña es incorrecta.
        |
        */

        $error = 'DNI o contraseña incorrectos.';
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>Administración | ECAP</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f5f6f8;
            color: #101D30;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login {
            width: 100%;
            max-width: 420px;
        }

        .login__brand {
            margin-bottom: 32px;
        }

        .login__brand strong {
            display: block;
            font-size: 24px;
            letter-spacing: -0.03em;
        }

        .login__brand span {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
        }

        .login__card {
            padding: 32px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(16, 29, 48, 0.07);
        }

        .login__title {
            margin: 0 0 6px;
            font-size: 24px;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .login__intro {
            margin: 0 0 28px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .field input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #d7dbe1;
            border-radius: 10px;
            outline: none;
            background: #ffffff;
            color: #101D30;
            font: inherit;
            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .field input:focus {
            border-color: #101D30;
            box-shadow: 0 0 0 3px rgba(16, 29, 48, 0.08);
        }

        .notice,
        .error {
            margin: 0 0 20px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.45;
        }

        .notice {
            background: #eef2f6;
            color: #3f4b5c;
        }

        .error {
            background: #f7eeee;
            color: #8b2d2d;
        }

        .submit {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 10px;
            background: #101D30;
            color: #ffffff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .submit:hover {
            opacity: .94;
        }
    </style>
</head>

<body>

<main class="login">

    <div class="login__brand">
        <strong>ECAP</strong>

        <span>
            Escuela de Capacitación de la Abogacía Pública
        </span>
    </div>

    <section class="login__card">

        <h1 class="login__title">
            Administración
        </h1>

        <p class="login__intro">
            Ingresá con tus credenciales para continuar.
        </p>

        <?php if ($sessionExpired): ?>
            <div class="notice" role="status">
                Tu sesión finalizó por seguridad.
                Ingresá nuevamente para continuar.
            </div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="error" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action=""
            autocomplete="on"
        >

            <?= Csrf::field() ?>

            <div class="field">
                <label for="dni">
                    DNI
                </label>

                <input
                    type="text"
                    id="dni"
                    name="dni"
                    value="<?= htmlspecialchars(
                        $dni,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    inputmode="numeric"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button
                class="submit"
                type="submit"
            >
                Ingresar
            </button>

        </form>

    </section>

</main>

</body>

</html>