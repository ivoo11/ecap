<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Actividad
|--------------------------------------------------------------------------
*/

$actividadId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$actividadId) {
    http_response_code(404);
    exit('Actividad no encontrada.');
}


$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.tipo_actividad_id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.programa,
        a.imagen_portada,
        a.destacada,
        a.estado,
        ta.nombre AS tipo_actividad
    FROM actividades a
    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id
    WHERE a.id = ?
    LIMIT 1
");

$stmt->execute([$actividadId]);

$actividad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actividad) {
    http_response_code(404);
    exit('Actividad no encontrada.');
}


/*
|--------------------------------------------------------------------------
| Sección
|--------------------------------------------------------------------------
*/

$seccion = $_GET['seccion'] ?? 'ediciones';

if (!in_array($seccion, ['general', 'ediciones'], true)) {
    $seccion = 'ediciones';
}


/*
|--------------------------------------------------------------------------
| Ediciones de la actividad
|--------------------------------------------------------------------------
*/

$stmtEdiciones = $pdo->prepare("
    SELECT
        id,
        fecha_inicio,
        fecha_fin,
        modalidad,
        cupo_maximo,
        inscripcion_desde,
        inscripcion_hasta,
        estado
    FROM ediciones
    WHERE actividad_id = ?
    ORDER BY fecha_inicio DESC, id DESC
");

$stmtEdiciones->execute([$actividadId]);

$ediciones = $stmtEdiciones->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function etiquetaEstadoEdicion(string $estado): string
{
    return match ($estado) {
        'borrador' => 'Borrador',
        'proximamente' => 'Próximamente',
        'inscripcion_abierta' => 'Inscripción abierta',
        'cupo_completo' => 'Cupo completo',
        'inscripcion_cerrada' => 'Inscripción cerrada',
        'en_curso' => 'En curso',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
        default => $estado,
    };
}

function etiquetaModalidad(string $modalidad): string
{
    return match ($modalidad) {
        'online' => 'Online',
        'presencial' => 'Presencial',
        'hibrida' => 'Híbrida',
        default => $modalidad,
    };
}

function fechaEdicion(string $fecha): string
{
    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    $meses = [
        1 => 'ENE',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'ABR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AGO',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DIC',
    ];

    $dia = date('j', $timestamp);
    $mes = $meses[(int) date('n', $timestamp)];
    $anio = date('Y', $timestamp);

    return "{$dia} {$mes} {$anio}";
}


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

$pageTitle = $actividad['titulo'];
$pageSection = 'actividades';

require __DIR__ . '/../includes/header.php';

?>

<section class="admin-page admin-page--activity-detail">

    <div class="activity-detail-back">
        <a href="/admin/actividades/">
            <span aria-hidden="true">←</span>
            Actividades
        </a>
    </div>


    <header class="activity-detail-hero">

        <span class="activity-detail-type">
            <?= htmlspecialchars(
                $actividad['tipo_actividad'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>

        <h1>
            <?= htmlspecialchars(
                $actividad['titulo'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

    </header>


    <nav
        class="activity-detail-nav"
        aria-label="Gestión de actividad"
    >

        <a
            href="/admin/actividades/editar.php?id=<?= (int) $actividad['id'] ?>&seccion=general"
            class="activity-detail-nav__item <?= $seccion === 'general' ? 'is-active' : '' ?>"
            <?= $seccion === 'general' ? 'aria-current="page"' : '' ?>
        >
            General
        </a>

        <a
            href="/admin/actividades/editar.php?id=<?= (int) $actividad['id'] ?>&seccion=ediciones"
            class="activity-detail-nav__item <?= $seccion === 'ediciones' ? 'is-active' : '' ?>"
            <?= $seccion === 'ediciones' ? 'aria-current="page"' : '' ?>
        >
            Ediciones
            <span><?= count($ediciones) ?></span>
        </a>

    </nav>


    <?php if ($seccion === 'general'): ?>

        <section class="activity-detail-section">

            <header class="activity-detail-section__header">

                <div>
                    <h2>Información general</h2>

                    <p>
                        Información académica que comparten todas
                        las ediciones de esta actividad.
                    </p>
                </div>

            </header>


            <div class="activity-general-data">

                <div class="activity-general-row">

                    <span class="activity-general-row__label">
                        Tipo
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $actividad['tipo_actividad'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </div>


                <div class="activity-general-row">

                    <span class="activity-general-row__label">
                        Título
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $actividad['titulo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </div>


                <div class="activity-general-row">

                    <span class="activity-general-row__label">
                        Descripción
                    </span>

                    <div class="activity-general-row__content">

                        <?php if (!empty($actividad['descripcion'])): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $actividad['descripcion'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        <?php else: ?>

                            <span class="activity-general-empty">
                                Sin descripción.
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="activity-general-row">

                    <span class="activity-general-row__label">
                        Programa
                    </span>

                    <div class="activity-general-row__content">

                        <?php if (!empty($actividad['programa'])): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $actividad['programa'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        <?php else: ?>

                            <span class="activity-general-empty">
                                Sin programa.
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>


    <?php else: ?>

        <section class="activity-detail-section">

            <header class="activity-detail-section__header">

                <div>
                    <h2>Ediciones</h2>

                    <p>
                        Cada edición representa una cursada independiente
                        de esta actividad.
                    </p>
                </div>

                <a
                    href="/admin/actividades/ediciones/nueva.php?actividad=<?= (int) $actividad['id'] ?>"
                    class="activity-action-primary"
                >
                    Nueva edición
                    <span aria-hidden="true">+</span>
                </a>

            </header>


            <?php if (!$ediciones): ?>

                <div class="activity-editions-empty">

                    <span class="activity-editions-empty__number">
                        01
                    </span>

                    <div>

                        <h3>Creá la primera edición</h3>

                        <p>
                            Definí cuándo se va a dictar esta actividad,
                            su modalidad y el cupo disponible.
                        </p>

                        <a
                            href="/admin/actividades/ediciones/nueva.php?actividad=<?= (int) $actividad['id'] ?>"
                            class="activity-editions-empty__action"
                        >
                            Crear primera edición
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>

                </div>


            <?php else: ?>

                <div class="activity-editions-list">

                    <?php foreach ($ediciones as $edicion): ?>

                        <article class="activity-edition-row">

                            <div class="activity-edition-row__date">

                                <span>
                                    <?= htmlspecialchars(
                                        fechaEdicion($edicion['fecha_inicio']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>


                            <div class="activity-edition-row__main">

                                <strong>
                                    <?= htmlspecialchars(
                                        etiquetaModalidad($edicion['modalidad']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= (int) $edicion['cupo_maximo'] ?>
                                    cupos
                                </span>

                            </div>


                            <div class="activity-edition-row__status">

                                <span>
                                    <?= htmlspecialchars(
                                        etiquetaEstadoEdicion($edicion['estado']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>


                            <div class="activity-edition-row__action">

                                <a
                                    href="/admin/actividades/ediciones/editar.php?id=<?= (int) $edicion['id'] ?>"
                                >
                                    Gestionar
                                    <span aria-hidden="true">→</span>
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>