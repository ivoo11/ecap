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
        AND e.estado IN (
            'proximamente',
            'inscripcion_abierta',
            'cupo_completo',
            'inscripcion_cerrada',
            'en_curso'
        )

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
        e.cupo_maximo,

        (
            SELECT COUNT(*)
            FROM inscripciones i
            WHERE
                i.edicion_id = e.id
                AND i.estado = 'confirmada'
        ) AS inscriptos_confirmados,

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
        AND e.estado IN (
            'proximamente',
            'inscripcion_abierta',
            'cupo_completo',
            'inscripcion_cerrada',
            'en_curso'
    )

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
   ACTIVIDADES ANTERIORES
   ========================================================= */

$sqlAnteriores = "
    SELECT
        a.id,
        a.titulo,
        a.slug,
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
        AND e.estado = 'finalizada'

    GROUP BY
        a.id,
        a.titulo,
        a.slug,
        a.imagen_portada,
        ta.nombre,
        e.id,
        e.fecha_inicio,
        e.modalidad,
        e.estado

    ORDER BY e.fecha_inicio DESC
";

$stmtAnteriores = $pdo->prepare($sqlAnteriores);
$stmtAnteriores->execute();

$actividadesAnteriores = $stmtAnteriores->fetchAll();

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
        href="assets/css/styles.css?v=<?= filemtime(__DIR__ . '/assets/css/styles.css') ?>"
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

    <!-- HERO CAROUSEL -->

    <section class="home-hero" aria-label="Destacados ECAP">

        <div class="home-hero-track">

            <!-- SLIDE 1 · SUMATE -->

            <article class="home-hero-slide is-active">

                <div class="home-hero-bg home-hero-bg-join"></div>

                <div class="home-hero-overlay"></div>

                <div class="container home-hero-content">

                    <div class="home-hero-copy">

                        <p class="home-hero-eyebrow">
                            Comunidad ECAP
                        </p>

                        <h1>
                            Sumate a ECAP.
                        </h1>

                        <p class="home-hero-description">
                            Registrate para recibir novedades, invitaciones
                            a actividades y conocer nuestras próximas
                            propuestas de capacitación.
                        </p>

                        <a href="#" class="home-hero-action">
                            Registrarme
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>

                </div>

            </article>


            <!-- SLIDE 2 · IDENTIDAD -->

            <article class="home-hero-slide">

                <div class="home-hero-bg home-hero-bg-future"></div>

                <div class="home-hero-overlay"></div>

                <div class="container home-hero-content">

                    <div class="home-hero-copy">

                        <p class="home-hero-eyebrow">
                            ECAP
                        </p>

                        <h2>
                            Estamos construyendo la capacitación del futuro.
                        </h2>

                        <p class="home-hero-description">
                            Formación y actualización para los desafíos
                            de la Abogacía Pública.
                        </p>

                    </div>

                </div>

            </article>


            <!-- SLIDE 3 · ACTIVIDADES -->

            <article class="home-hero-slide">

                <div class="home-hero-bg home-hero-bg-activities"></div>

                <div class="home-hero-overlay"></div>

                <div class="container home-hero-content">

                    <div class="home-hero-copy">

                        <p class="home-hero-eyebrow">
                            Actividades
                        </p>

                        <h2>
                            Un espacio de encuentro, formación<br>y crecimiento.
                        </h2>

                        <p class="home-hero-description">
                            Seminarios, encuentros y propuestas académicas
                            para la Abogacía Pública.
                        </p>

                        <a href="#actividades" class="home-hero-action">
                            Ver actividades
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>

                </div>

            </article>

        </div>

        <!-- NAVEGACIÓN DESKTOP -->

        <button
            type="button"
            class="home-hero-arrow home-hero-arrow-prev"
            aria-label="Anterior"
        >
            <span aria-hidden="true">‹</span>
        </button>

        <button
            type="button"
            class="home-hero-arrow home-hero-arrow-next"
            aria-label="Siguiente"
        >
            <span aria-hidden="true">›</span>
        </button>

        <div class="home-hero-controls">

            <button
                type="button"
                class="home-hero-dot is-active"
                aria-label="Ver Sumate a ECAP"
                aria-current="true"
            ></button>

            <button
                type="button"
                class="home-hero-dot"
                aria-label="Ver presentación de ECAP"
                aria-current="false"
            ></button>

            <button
                type="button"
                class="home-hero-dot"
                aria-label="Ver actividades"
                aria-current="false"
            ></button>

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


                            /* ESTADO PÚBLICO DE LA EDICIÓN */

                            $estadoEdicion = $actividad['estado'];

                            $cupoMaximo = $actividad['cupo_maximo'] !== null
                                ? (int) $actividad['cupo_maximo']
                                : null;

                            $inscriptosConfirmados =
                                (int) $actividad['inscriptos_confirmados'];

                            $hayCupo =
                                $cupoMaximo === null ||
                                $inscriptosConfirmados < $cupoMaximo;


                            if (
                                !$hayCupo ||
                                $estadoEdicion === 'cupo_completo'
                            ) {

                                $estadoPublico = 'CUPOS AGOTADOS';
                                $estadoClase = 'is-full';

                            } elseif ($estadoEdicion === 'proximamente') {

                                $estadoPublico = 'INSCRIPCIONES PRÓXIMAMENTE';
                                $estadoClase = 'is-soon';

                            } elseif ($estadoEdicion === 'inscripcion_abierta') {

                                $estadoPublico = 'INSCRIPCIONES ABIERTAS';
                                $estadoClase = 'is-open';

                            } elseif ($estadoEdicion === 'inscripcion_cerrada') {

                                $estadoPublico = 'INSCRIPCIONES CERRADAS';
                                $estadoClase = 'is-closed';

                            } elseif ($estadoEdicion === 'en_curso') {

                                $estadoPublico = 'ACTIVIDAD EN CURSO';
                                $estadoClase = 'is-running';

                            } else {

                                $estadoPublico = '';
                                $estadoClase = '';

                            }

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

                                    <?php if ($estadoPublico !== ''): ?>

                                        <p class="activity-status <?= htmlspecialchars($estadoClase) ?>">
                                            <?= htmlspecialchars($estadoPublico) ?>
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

        <?php if (!empty($actividadesAnteriores)): ?>

            <!-- ACTIVIDADES ANTERIORES -->

            <section class="past-activities-section">

                <div class="container">

                    <div class="section-heading past-activities-heading">
                        <h2>Actividades anteriores</h2>
                    </div>

                    <div class="past-activities-grid">

                        <?php foreach ($actividadesAnteriores as $actividadAnterior): ?>

                            <?php
                                $fechaAnterior = fechaActividad(
                                    $actividadAnterior['fecha_inicio']
                                );

                                $imagenAnterior = imagenActividad(
                                    $actividadAnterior['imagen_portada']
                                );
                            ?>

                            <article class="activity-item activity-item-past">

                                <div class="activity-image">

                                    <?php if ($imagenAnterior): ?>

                                        <img
                                            src="<?= htmlspecialchars($imagenAnterior) ?>"
                                            alt=""
                                        >

                                    <?php else: ?>

                                        <div class="activity-image-fallback"></div>

                                    <?php endif; ?>


                                    <div class="activity-date-overlay">

                                        <span class="fallback-day">
                                            <?= htmlspecialchars($fechaAnterior['dia']) ?>
                                        </span>

                                        <span class="fallback-month">
                                            <?= htmlspecialchars(
                                                strtoupper(
                                                    substr(
                                                        $fechaAnterior['mes'],
                                                        0,
                                                        3
                                                    )
                                                )
                                            ) ?>
                                        </span>

                                    </div>

                                </div>


                                <div class="activity-copy">

                                    <h3>
                                        <?= htmlspecialchars(
                                            $actividadAnterior['titulo']
                                        ) ?>
                                    </h3>

                                    <?php if (!empty($actividadAnterior['docentes'])): ?>

                                        <p class="activity-teacher">
                                            <?= htmlspecialchars(
                                                $actividadAnterior['docentes']
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                    <p>
                                        <?= htmlspecialchars(
                                            $actividadAnterior['tipo']
                                        ) ?>
                                        ·
                                        <?= htmlspecialchars(
                                            modalidadLabel(
                                                $actividadAnterior['modalidad']
                                            )
                                        ) ?>
                                    </p>

                                    <p>
                                        <?= htmlspecialchars(
                                            $fechaAnterior['dia']
                                        ) ?>
                                        de
                                        <?= htmlspecialchars(
                                            $fechaAnterior['mes']
                                        ) ?>
                                        ·
                                        <?= htmlspecialchars(
                                            $fechaAnterior['hora']
                                        ) ?>
                                        hs
                                    </p>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </div>

            </section>

        <?php endif; ?>

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