<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';


/* =========================================================
   HELPERS
   ========================================================= */

function modalidadLabel(string $modalidad): string
{
    return match ($modalidad) {
        'online' => 'Online',
        'presencial' => 'Presencial',
        'hibrida' => 'Híbrida',
        default => ucfirst($modalidad),
    };
}

function fechaCompleta(string $fecha): string
{
    $timestamp = strtotime($fecha);

    $meses = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    return
        date('j', $timestamp) .
        ' de ' .
        $meses[(int) date('n', $timestamp)] .
        ' de ' .
        date('Y', $timestamp);
}

function horaActividad(string $fecha): string
{
    return date('H:i', strtotime($fecha));
}

function iniciales(string $nombre, string $apellido): string
{
    return mb_strtoupper(
        mb_substr(trim($nombre), 0, 1) .
        mb_substr(trim($apellido), 0, 1)
    );
}


/* =========================================================
   SLUG
   ========================================================= */

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(404);
    exit('Actividad no encontrada.');
}


/* =========================================================
   ACTIVIDAD + EDICIÓN
   ========================================================= */

$sql = "
    SELECT
        a.id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.programa,
        a.imagen_portada,

        ta.nombre AS tipo,

        e.id AS edicion_id,
        e.fecha_inicio,
        e.fecha_fin,
        e.modalidad,
        e.estado,
        e.cupo_maximo,

        (
            SELECT COUNT(*)
            FROM inscripciones i
            WHERE
                i.edicion_id = e.id
                AND i.estado = 'confirmada'
        ) AS inscriptos_confirmados 

    FROM actividades a

    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id

    INNER JOIN ediciones e
        ON e.actividad_id = a.id

    WHERE
        a.slug = :slug
        AND a.estado = 'publicada'
        AND e.estado IN (
            'proximamente',
            'inscripcion_abierta',
            'cupo_completo',
            'inscripcion_cerrada',
            'en_curso',
            'finalizada'
        )

        ORDER BY
            CASE
                WHEN e.estado = 'en_curso' THEN 1
                WHEN e.estado = 'inscripcion_abierta' THEN 2
                WHEN e.estado = 'proximamente' THEN 3
                WHEN e.estado = 'cupo_completo' THEN 4
                WHEN e.estado = 'inscripcion_cerrada' THEN 5
                WHEN e.estado = 'finalizada' THEN 6
                ELSE 7
            END ASC,

            CASE
                WHEN e.estado IN (
                    'en_curso',
                    'inscripcion_abierta',
                    'proximamente',
                    'cupo_completo',
                    'inscripcion_cerrada'
                )
                THEN e.fecha_inicio
            END ASC,

            CASE
                WHEN e.estado = 'finalizada'
                THEN e.fecha_inicio
            END DESC

        LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'slug' => $slug
]);

$actividad = $stmt->fetch();

if (!$actividad) {
    http_response_code(404);
    exit('Actividad no encontrada.');
}

/* =========================================================
   CUPO
   ========================================================= */

$cupoMaximo = $actividad['cupo_maximo'] !== null
    ? (int) $actividad['cupo_maximo']
    : null;

$inscriptosConfirmados =
    (int) $actividad['inscriptos_confirmados'];

$hayCupo =
    $cupoMaximo === null ||
    $inscriptosConfirmados < $cupoMaximo;


/* =========================================================
   DOCENTES
   ========================================================= */

$sqlDocentes = "
    SELECT
        d.id,
        d.titulo_profesional,
        d.bio,
        d.foto,

        p.nombre,
        p.apellido

    FROM edicion_docentes ed

    INNER JOIN docentes d
        ON d.id = ed.docente_id

    INNER JOIN personas p
        ON p.id = d.persona_id

    WHERE
        ed.edicion_id = :edicion_id
        AND d.activo = 1

    ORDER BY
        ed.orden ASC,
        p.apellido ASC,
        p.nombre ASC
";

$stmtDocentes = $pdo->prepare($sqlDocentes);

$stmtDocentes->execute([
    'edicion_id' => $actividad['edicion_id']
]);

$docentes = $stmtDocentes->fetchAll();


/* =========================================================
   DATOS DERIVADOS
   ========================================================= */

$fecha = fechaCompleta($actividad['fecha_inicio']);
$hora = horaActividad($actividad['fecha_inicio']);

$imagenPortada = null;

if (!empty($actividad['imagen_portada'])) {
    $imagenPortada =
        '../assets/img/actividades/' .
        rawurlencode($actividad['imagen_portada']);
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

    <title>
        <?= htmlspecialchars($actividad['titulo']) ?> · ECAP
    </title>

    <meta
        name="description"
        content="<?= htmlspecialchars(
            $actividad['descripcion']
            ?: 'Actividad académica de ECAP.'
        ) ?>"
    >

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/styles.css"
    >

</head>


<body>


<header class="site-header">

    <div class="container header-inner">

        <a
            href="../"
            class="brand"
            aria-label="ECAP - Inicio"
        >

            <img
                src="../assets/img/logoaz.png"
                alt="ECAP"
            >

        </a>


        <nav
            class="desktop-nav"
            aria-label="Navegación principal"
        >

            <a href="../#actividades">
                Actividades
            </a>

            <a
                href="#"
                class="nav-access"
            >
                Acceso
            </a>

        </nav>


        <button
            class="menu-button"
            type="button"
            aria-label="Abrir menú"
            aria-expanded="false"
        >
            <span></span>
            <span></span>
        </button>

    </div>

</header>


<main class="activity-detail">


    <!-- =====================================================
         CABECERA
         ===================================================== -->

<section class="activity-intro">

    <div class="container">

        <p class="activity-eyebrow">
            <?= htmlspecialchars($actividad['tipo']) ?>
            ·
            <?= htmlspecialchars(
                modalidadLabel($actividad['modalidad'])
            ) ?>
        </p>

        <h1 class="activity-title">
            <?= htmlspecialchars($actividad['titulo']) ?>
        </h1>

        <?php if (!empty($docentes)): ?>

            <div class="activity-teachers">

                <?php foreach ($docentes as $docente): ?>

                    <p>
                        <?php if (!empty($docente['titulo_profesional'])): ?>
                            <?= htmlspecialchars($docente['titulo_profesional']) ?>
                        <?php endif; ?>

                        <?= htmlspecialchars($docente['nombre']) ?>
                        <?= htmlspecialchars($docente['apellido']) ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <div class="activity-meta">
            <span><?= htmlspecialchars($fecha) ?></span>

            <span>
                <?= htmlspecialchars($hora) ?> hs
                ·
                <?= htmlspecialchars(
                    modalidadLabel($actividad['modalidad'])
                ) ?>
            </span>
        </div>


        <?php if (
            $actividad['estado'] === 'inscripcion_abierta' &&
            $hayCupo
        ): ?>

            <a
                href="../inscripcion/?edicion=<?= (int) $actividad['edicion_id'] ?>"
                class="primary-action"
            >
                Inscribirme
            </a>

        <?php elseif (
            !$hayCupo ||
            $actividad['estado'] === 'cupo_completo'
        ): ?>

            <span
                class="primary-action primary-action-disabled"
                aria-disabled="true"
            >
                Cupos agotados
            </span>

        <?php elseif ($actividad['estado'] === 'proximamente'): ?>

            <span
                class="primary-action primary-action-disabled"
                aria-disabled="true"
            >
                Inscripciones próximamente
            </span>

        <?php elseif ($actividad['estado'] === 'inscripcion_cerrada'): ?>

            <span
                class="primary-action primary-action-disabled"
                aria-disabled="true"
            >
                Inscripciones cerradas
            </span>

        <?php elseif ($actividad['estado'] === 'en_curso'): ?>

            <span
                class="primary-action primary-action-disabled"
                aria-disabled="true"
            >
                Actividad en curso
            </span>

        <?php elseif ($actividad['estado'] === 'finalizada'): ?>

            <span
                class="primary-action primary-action-disabled"
                aria-disabled="true"
            >
                Actividad finalizada
            </span>

        <?php endif; ?>

    </div>

</section>


<?php if (!empty($docentes)): ?>

    <section class="activity-bios">

        <div class="container">

            <h2 class="activity-bios-title">
                <?= count($docentes) === 1
                    ? 'Acerca del docente'
                    : 'Acerca de los docentes' ?>
            </h2>

            <div class="activity-bios-content">

                <?php foreach ($docentes as $docente): ?>

                    <?php
                        $fotoDocente = null;

                        if (!empty($docente['foto'])) {
                            $fotoDocente =
                                '../assets/img/docentes/' .
                                rawurlencode($docente['foto']);
                        }
                    ?>

                    <article class="activity-bio">

                        <?php if (count($docentes) > 1): ?>

                            <h3 class="activity-bio-name">
                                <?php if (!empty($docente['titulo_profesional'])): ?>
                                    <?= htmlspecialchars($docente['titulo_profesional']) ?>
                                <?php endif; ?>

                                <?= htmlspecialchars($docente['nombre']) ?>
                                <?= htmlspecialchars($docente['apellido']) ?>
                            </h3>

                        <?php endif; ?>

                        <?php if (!empty($docente['bio'])): ?>

                            <p>
                                <?= nl2br(
                                    htmlspecialchars($docente['bio'])
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if ($fotoDocente): ?>

                        <div class="activity-bio-photo">
                            <img
                                src="<?= htmlspecialchars($fotoDocente) ?>"
                                alt="<?= htmlspecialchars(
                                    $docente['nombre'] . ' ' . $docente['apellido']
                                ) ?>"
                            >
                        </div>

                    <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

<?php endif; ?>



    <!-- =====================================================
         DESCRIPCIÓN
         Sólo aparece si existe
         ===================================================== -->

    <?php if (!empty($actividad['descripcion'])): ?>

        <section class="detail-description">

            <div class="container detail-content-grid">

                <h2>
                    Sobre la actividad
                </h2>

                <div class="detail-body">
                    <?= nl2br(
                        htmlspecialchars($actividad['descripcion'])
                    ) ?>
                </div>

            </div>

        </section>

    <?php endif; ?>

</main>


<footer class="site-footer">

    <div class="container footer-inner">

        <img
            src="../assets/img/logobc.png"
            alt="ECAP"
            class="footer-logo"
        >

        <p>
            © <?= date('Y') ?> ECAP
        </p>

    </div>

</footer>


<script src="../assets/js/app.js"></script>

</body>

</html>