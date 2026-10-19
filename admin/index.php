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

?>

<section class="admin-page">

    <header class="admin-page__header">

        <div>
            <h1>
                Hola, <?= htmlspecialchars(
                    $usuario['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <p>
                Gestioná las principales áreas de ECAP desde un único lugar.
            </p>
        </div>

    </header>

    <section class="admin-card correo-cron">
        <h2>Correos automáticos</h2>
        <p><?= count($correosPendientes) ? count($correosPendientes).' correos requieren atención.' : 'Sin correos pendientes de atención para actividades vigentes.' ?></p>
        <?php if ($cronCorreo['alerta']): ?><p><strong>Revisar recordatorios: no hay una ejecución completada hoy.</strong></p><?php endif; ?>
        <a class="activity-action-secondary" href="/admin/correos/">Ver control de correos</a>
    </section>
    <div class="admin-dashboard">

        <a
            class="admin-dashboard__card"
            href="/admin/actividades/"
        >
            <span class="admin-dashboard__label">
                Actividades
            </span>

            <strong>
                Gestionar actividades
            </strong>

            <p>
                Creá y administrá actividades, ediciones, clases y docentes asignados.
            </p>
        </a>

        <a
            class="admin-dashboard__card"
            href="/admin/alumnos/"
        >
            <span class="admin-dashboard__label">
                Alumnos
            </span>

            <strong>
                Gestionar alumnos
            </strong>

            <p>
                Consultá alumnos, inscripciones, cursadas, asistencia y certificados.
            </p>
        </a>

        <a
            class="admin-dashboard__card"
            href="/admin/docentes/"
        >
            <span class="admin-dashboard__label">
                Docentes
            </span>

            <strong>
                Gestionar docentes
            </strong>

            <p>
                Administrá perfiles docentes y su participación en las actividades.
            </p>
        </a>

    </div>

</section>

<?php

require __DIR__ . '/includes/footer.php';