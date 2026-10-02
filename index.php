<?php

declare(strict_types=1);

require_once __DIR__ . '/app/config/database.php';


/* =========================================================
   ACTIVIDAD DESTACADA
   ========================================================= */

$sqlDestacada = "
    SELECT
        a.id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.imagen_portada,
        ta.nombre AS tipo,
        e.id AS edicion_id,
        e.fecha_inicio,
        e.modalidad,
        e.estado,

        GROUP_CONCAT(
            DISTINCT CONCAT_WS(
                ' ',
                NULLIF(TRIM(d.titulo_profesional), ''),
                p.nombre,
                p.apellido
            )
            ORDER BY p.apellido, p.nombre
            SEPARATOR ' · '
        ) AS docentes

    FROM actividades a

    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id

    INNER JOIN ediciones e
        ON e.actividad_id = a.id

    LEFT JOIN edicion_docentes ed
        ON ed.edicion_id = e.id

    LEFT JOIN docentes d
        ON d.id = ed.docente_id
        AND d.activo = 1

    LEFT JOIN personas p
        ON p.id = d.persona_id

    WHERE
        a.estado = 'publicada'
        AND a.destacada = 1
        AND e.estado NOT IN ('cancelada', 'finalizada')

    GROUP BY
        a.id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.imagen_portada,
        ta.nombre,
        e.id,
        e.fecha_inicio,
        e.modalidad,
        e.estado,
        a.orden_destacada

    ORDER BY
        a.orden_destacada IS NULL,
        a.orden_destacada ASC,
        e.fecha_inicio ASC

    LIMIT 1
";

$stmtDestacada = $pdo->prepare($sqlDestacada);
$stmtDestacada->execute();

$actividadDestacada = $stmtDestacada->fetch();


/* =========================================================
   PRÓXIMAS ACTIVIDADES
   ========================================================= */

$sql = "
    SELECT
        a.id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.imagen_portada,
        ta.nombre AS tipo,
        e.id AS edicion_id,
        e.fecha_inicio,
        e.modalidad,
        e.estado,

        GROUP_CONCAT(
            DISTINCT CONCAT_WS(
                ' ',
                NULLIF(TRIM(d.titulo_profesional), ''),
                p.nombre,
                p.apellido
            )
            ORDER BY p.apellido, p.nombre
            SEPARATOR ' · '
        ) AS docentes

    FROM actividades a

    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id

    INNER JOIN ediciones e
        ON e.actividad_id = a.id

    LEFT JOIN edicion_docentes ed
        ON ed.edicion_id = e.id

    LEFT JOIN docentes d
        ON d.id = ed.docente_id
        AND d.activo = 1

    LEFT JOIN personas p
        ON p.id = d.persona_id

    WHERE
        a.estado = 'publicada'
        AND e.estado NOT IN ('cancelada', 'finalizada')

    GROUP BY
        a.id,
        a.titulo,
        a.slug,
        a.descripcion,
        a.imagen_portada,
        ta.nombre,
        e.id,
        e.fecha_inicio,
        e.modalidad,
        e.estado

    ORDER BY e.fecha_inicio ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$actividades = $stmt->fetchAll();


/* =========================================================
   HELPERS
   ========================================================= */

function fechaActividad(string $fecha): array
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

    return [
        'dia' => date('j', $timestamp),
        'mes' => $meses[(int) date('n', $timestamp)],
        'hora' => date('H:i', $timestamp),
    ];
}

function modalidadLabel(string $modalidad): string
{
    return match ($modalidad) {
        'online' => 'Online',
        'presencial' => 'Presencial',
        'hibrida' => 'Híbrida',
        default => ucfirst($modalidad),
    };
}

function imagenActividad(?string $imagen): ?string
{
    if (!$imagen) {
        return null;
    }

    return 'assets/img/actividades/' . rawurlencode($imagen);
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

    <title>ECAP | Escuela de Capacitación de la Abogacía Pública</title>

    <meta
        name="description"
        content="ECAP. Actividades académicas, cursos, talleres y formación profesional."
    >

    <link
        rel="icon"
        type="image/png"
        href="assets/img/favicon.png"
    >

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap"
    rel="stylesheet"
>

    <link
        rel="stylesheet"
        href="assets/css/styles.css"
    >
</head>

<body>

<header class="site-header">
    <div class="container header-inner">

        <a
            href="./"
            class="brand"
            aria-label="ECAP - Inicio"
        >
            <img
                src="assets/img/logoaz.png"
                alt="ECAP"
            >
        </a>

        <nav class="desktop-nav" aria-label="Navegación principal">
            <a href="#actividades">Actividades</a>
            <a href="#" class="nav-access">Acceso</a>
        </nav>

        <button
            class="menu-button"
            type="button"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="mobile-nav"
        >
            <span></span>
            <span></span>
        </button>

    </div>

    <nav
        class="mobile-nav"
        id="mobile-nav"
        aria-label="Navegación móvil"
    >
        <div class="container mobile-nav-inner">

            <a href="#actividades">
                Actividades
            </a>

            <a href="#" class="nav-access">
                Acceso
            </a>

        </div>
    </nav>
</header>


<main>

    <!-- HERO -->

    <section class="hero">
        <div class="container hero-inner">

            <div class="hero-copy">

                <h1>
                    Estamos construyendo la capacitación del futuro.
                </h1>

                <p>
                    Formación y actualización para los desafíos
                    de la Abogacía Pública.
                </p>

            </div>

        </div>
    </section>


    <!-- DESTACADA: SOLO APARECE SI EXISTE -->

    <?php if ($actividadDestacada): ?>

        <?php
            $fechaDestacada = fechaActividad(
                $actividadDestacada['fecha_inicio']
            );

            $imagenDestacada = imagenActividad(
                $actividadDestacada['imagen_portada']
            );
        ?>

        <section class="featured-section">

            <div class="container">

                <a
                    href="actividades/?slug=<?= urlencode($actividadDestacada['slug']) ?>"
                    class="featured-activity"
                >

                    <div class="featured-media">

                        <?php if ($imagenDestacada): ?>

                            <img
                                src="<?= htmlspecialchars($imagenDestacada) ?>"
                                alt=""
                            >

                        <?php else: ?>

                            <div class="featured-placeholder"></div>

                        <?php endif; ?>

                    </div>


                    <div class="featured-content">

                        <p class="featured-label">
                            Actividad destacada
                        </p>

                        <h2>
                            <?= htmlspecialchars($actividadDestacada['titulo']) ?>
                        </h2>

                        <?php if (!empty($actividadDestacada['descripcion'])): ?>

                            <p class="featured-description">
                                <?= htmlspecialchars($actividadDestacada['descripcion']) ?>
                            </p>

                        <?php endif; ?>

                        <p class="featured-meta">
                            <?= htmlspecialchars($fechaDestacada['dia']) ?>
                            de
                            <?= htmlspecialchars($fechaDestacada['mes']) ?>
                            ·
                            <?= htmlspecialchars($fechaDestacada['hora']) ?>
                            hs
                            ·
                            <?= htmlspecialchars(
                                modalidadLabel($actividadDestacada['modalidad'])
                            ) ?>
                        </p>

                        <span class="text-link">
                            Conocer la actividad
                        </span>

                    </div>

                </a>

            </div>

        </section>

    <?php endif; ?>


    <!-- PRÓXIMAS ACTIVIDADES -->

    <section class="activities-section">

        <div class="container">

            <div
                class="section-heading"
                id="actividades"
            >
                <h2>Próximas actividades</h2>
            </div>


            <?php if (!empty($actividades)): ?>

                <div class="activities-grid">

                    <?php foreach ($actividades as $actividad): ?>

                        <?php
                            $fecha = fechaActividad(
                                $actividad['fecha_inicio']
                            );

                            $imagen = imagenActividad(
                                $actividad['imagen_portada']
                            );
                        ?>

                        <article class="activity-item">

                            <a
                                href="actividades/?slug=<?= urlencode($actividad['slug']) ?>"
                                class="activity-link"
                            >

                            <div class="activity-image">

                                <?php if ($imagen): ?>

                                    <img
                                        src="<?= htmlspecialchars($imagen) ?>"
                                        alt=""
                                    >

                                <?php else: ?>

                                    <div class="activity-image-fallback"></div>

                                <?php endif; ?>


                                <div class="activity-date-overlay">

                                    <span class="fallback-day">
                                        <?= htmlspecialchars($fecha['dia']) ?>
                                    </span>

                                    <span class="fallback-month">
                                        <?= htmlspecialchars(
                                            strtoupper(substr($fecha['mes'], 0, 3))
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                                <div class="activity-copy">

                                    <h3>
                                        <?= htmlspecialchars($actividad['titulo']) ?>
                                    </h3>

                                    <?php if (!empty($actividad['docentes'])): ?>

                                    <p class="activity-teacher">
                                        <?= htmlspecialchars($actividad['docentes']) ?>
                                    </p>

                                    <?php endif; ?>

                                    <p>
                                        <?= htmlspecialchars($actividad['tipo']) ?>
                                        ·
                                        <?= htmlspecialchars(
                                            modalidadLabel($actividad['modalidad'])
                                        ) ?>
                                    </p>

                                    <p>
                                        <?= htmlspecialchars($fecha['dia']) ?>
                                        de
                                        <?= htmlspecialchars($fecha['mes']) ?>
                                        ·
                                        <?= htmlspecialchars($fecha['hora']) ?>
                                        hs
                                    </p>

                                </div>

                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="empty-state">
                    Próximamente se anunciarán nuevas actividades.
                </p>

            <?php endif; ?>

        </div>

    </section>


    <!-- INSTITUCIONAL -->

    <section class="about-section">

        <div class="container about-grid">

            <div class="about-title">
                <h2>
                    Formar para transformar.
                </h2>
            </div>

            <div class="about-copy">

                <p>
                    ECAP es un espacio de formación y actualización
                    orientado a los desafíos contemporáneos de la
                    Abogacía Pública.
                </p>

                <p>
                    Una propuesta académica que reúne conocimiento,
                    experiencia y herramientas para el desarrollo
                    profesional.
                </p>

            </div>

        </div>

    </section>

</main>


<footer class="site-footer">

    <div class="container footer-inner">

        <img
            src="assets/img/logobc.png"
            alt="ECAP"
            class="footer-logo"
        >

        <p>
            © <?= date('Y') ?> ECAP
        </p>

    </div>

</footer>

<script src="assets/js/app.js"></script>

</body>
</html>