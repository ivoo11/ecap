<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../app/services/InscripcionService.php';


/*
|--------------------------------------------------------------------------
| Edición
|--------------------------------------------------------------------------
*/

$edicionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$edicionId) {
    http_response_code(404);
    exit('Edición no encontrada.');
}


/*
|--------------------------------------------------------------------------
| Cargar edición + actividad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.actividad_id,
        e.fecha_inicio,
        e.fecha_fin,
        e.modalidad,
        e.cupo_maximo,
        e.estado,
        a.titulo AS actividad_titulo,
        a.slug AS actividad_slug,
        ta.nombre AS tipo_actividad
    FROM ediciones e
    INNER JOIN actividades a
        ON a.id = e.actividad_id
    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id
    WHERE e.id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $edicionId,
]);

$edicion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edicion) {
    http_response_code(404);
    exit('Edición no encontrada.');
}


/*
|--------------------------------------------------------------------------
| Estado de interfaz
|--------------------------------------------------------------------------
*/

$mensajeExito = null;
$mensajeError = null;
$personaEncontrada = null;
$dniBuscado = '';


/*
|--------------------------------------------------------------------------
| Acciones POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Recargá la página.');
    }

    $accion = (string) ($_POST['accion'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Buscar persona
    |--------------------------------------------------------------------------
    */

    if ($accion === 'buscar_persona') {

        $dniBuscado = preg_replace(
            '/\D/',
            '',
            (string) ($_POST['dni'] ?? '')
        );

        if (
            strlen($dniBuscado) < 7 ||
            strlen($dniBuscado) > 8
        ) {

            $mensajeError = 'Ingresá un DNI válido.';

        } else {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    nombre,
                    apellido,
                    dni,
                    email,
                    estado
                FROM personas
                WHERE dni = :dni
                LIMIT 1
            ");

            $stmt->execute([
                'dni' => $dniBuscado,
            ]);

            $personaEncontrada = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$personaEncontrada) {

                $mensajeError =
                    'La persona no se encuentra registrada en ECAP. '
                    . 'Debe completar primero el registro general.';

            } elseif ($personaEncontrada['estado'] !== 'activo') {

                $mensajeError =
                    'La persona está registrada, pero su perfil no se encuentra activo.';

                $personaEncontrada = null;

            } else {

                /*
                |--------------------------------------------------------------------------
                | Verificar si ya está inscripta
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        estado
                    FROM inscripciones
                    WHERE
                        persona_id = :persona_id
                        AND edicion_id = :edicion_id
                    LIMIT 1
                ");

                $stmt->execute([
                    'persona_id' => (int) $personaEncontrada['id'],
                    'edicion_id' => $edicionId,
                ]);

                $inscripcionExistente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($inscripcionExistente) {

                    $mensajeError =
                        'Esta persona ya posee una inscripción para esta edición.';

                    $personaEncontrada = null;
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Agregar alumno
    |--------------------------------------------------------------------------
    */

    if ($accion === 'agregar_alumno') {

        $personaId = filter_input(
            INPUT_POST,
            'persona_id',
            FILTER_VALIDATE_INT
        );

        if (!$personaId) {

            $mensajeError = 'La persona seleccionada no es válida.';

        } else {

            try {

                $inscripcionService = new InscripcionService($pdo);

                $inscripcionService->inscribirPersonaDesdeAdmin(
                    (int) $personaId,
                    $edicionId
                );

                /*
                 * PRG:
                 * evitamos que actualizar el navegador vuelva a enviar
                 * la inscripción y/o el correo.
                 */

                header(
                    'Location: /admin/actividades/ediciones/editar.php'
                    . '?id=' . $edicionId
                    . '&alta=ok'
                );

                exit;

            } catch (Throwable $e) {

                $mensajeError = $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Confirmación posterior al redirect
|--------------------------------------------------------------------------
*/

if (($_GET['alta'] ?? '') === 'ok') {
    $mensajeExito =
        'Alumno agregado correctamente. La inscripción quedó confirmada.';
}


/*
|--------------------------------------------------------------------------
| Inscriptos
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        i.id AS inscripcion_id,
        i.estado AS inscripcion_estado,
        i.inscripto_en,
        p.id AS persona_id,
        p.nombre,
        p.apellido,
        p.dni,
        p.email
    FROM inscripciones i
    INNER JOIN personas p
        ON p.id = i.persona_id
    WHERE i.edicion_id = :edicion_id
    ORDER BY
        p.apellido ASC,
        p.nombre ASC,
        p.id ASC
");

$stmt->execute([
    'edicion_id' => $edicionId,
]);

$inscriptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmados = 0;

foreach ($inscriptos as $inscripto) {
    if ($inscripto['inscripcion_estado'] === 'confirmada') {
        $confirmados++;
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function etiquetaModalidadEdicion(string $modalidad): string
{
    return match ($modalidad) {
        'online' => 'Online',
        'presencial' => 'Presencial',
        'hibrida' => 'Híbrida',
        default => $modalidad,
    };
}


function etiquetaEstadoInscripcion(string $estado): string
{
    return match ($estado) {
        'confirmada' => 'Confirmada',
        'pendiente' => 'Pendiente',
        'cancelada' => 'Cancelada',
        default => $estado,
    };
}


function fechaEdicionAdmin(string $fecha): string
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

    return
        date('j', $timestamp)
        . ' '
        . $meses[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp)
        . ' · '
        . date('H:i', $timestamp);
}


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

$pageTitle = 'Alumnos';
$pageSection = 'actividades';

require __DIR__ . '/../../includes/header.php';

?>

<section class="admin-page admin-page--edition">


    <div class="activity-detail-back">

        <a
            href="/admin/actividades/editar.php?id=<?= (int) $edicion['actividad_id'] ?>&seccion=ediciones"
        >
            <span aria-hidden="true">←</span>
            Volver a la actividad
        </a>

    </div>


    <header class="edition-admin-hero">

        <span class="edition-admin-hero__type">
            <?= htmlspecialchars(
                $edicion['tipo_actividad'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>

        <h1>
            <?= htmlspecialchars(
                $edicion['actividad_titulo'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <div class="edition-admin-hero__meta">

            <span>
                <?= htmlspecialchars(
                    fechaEdicionAdmin($edicion['fecha_inicio']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

            <span aria-hidden="true">·</span>

            <span>
                <?= htmlspecialchars(
                    etiquetaModalidadEdicion($edicion['modalidad']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

        </div>

    </header>


    <nav
        class="edition-admin-nav"
        aria-label="Gestión de edición"
    >

        <span class="edition-admin-nav__item">
            Configuración
        </span>

        <span class="edition-admin-nav__item">
            Docentes
        </span>

        <span class="edition-admin-nav__item">
            Clases
        </span>

        <span class="edition-admin-nav__item is-active">
            Alumnos
        </span>

        <span class="edition-admin-nav__item">
            Materiales
        </span>

    </nav>


    <?php if ($mensajeExito): ?>

        <div class="edition-message edition-message--success">
            <?= htmlspecialchars(
                $mensajeExito,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($mensajeError): ?>

        <div class="edition-message edition-message--error">
            <?= htmlspecialchars(
                $mensajeError,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <section class="edition-students">


        <header class="edition-students__header">

            <div>

                <h2>Alumnos</h2>

                <p>
                    <?= $confirmados ?>
                    de
                    <?= (int) $edicion['cupo_maximo'] ?>
                    cupos confirmados
                </p>

            </div>

        </header>


        <div class="edition-add-student">

            <div class="edition-add-student__intro">

                <h3>Agregar alumno</h3>

                <p>
                    Buscá por DNI una persona previamente registrada
                    en ECAP.
                </p>

            </div>


            <form
                method="post"
                class="edition-student-search"
            >

                <?= Csrf::field() ?>

                <input
                    type="hidden"
                    name="accion"
                    value="buscar_persona"
                >

                <label for="dni">
                    DNI
                </label>

                <div class="edition-student-search__controls">

                    <input
                        type="text"
                        id="dni"
                        name="dni"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="10"
                        placeholder="Ingresá el DNI"
                        value="<?= htmlspecialchars(
                            $dniBuscado,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                    <button
                        type="submit"
                        class="activity-action-primary"
                    >
                        Buscar persona
                    </button>

                </div>

            </form>

        </div>

            <?php if ($personaEncontrada): ?>

                <div class="edition-person-result">

                    <div class="edition-person-result__identity">

                        <strong>
                            <?= htmlspecialchars(
                                $personaEncontrada['nombre']
                                . ' '
                                . $personaEncontrada['apellido'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <span>
                            DNI
                            <?= htmlspecialchars(
                                $personaEncontrada['dni'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <span>
                            <?= htmlspecialchars(
                                $personaEncontrada['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </div>


                    <form method="post">

                        <?= Csrf::field() ?>

                        <input
                            type="hidden"
                            name="accion"
                            value="agregar_alumno"
                        >

                        <input
                            type="hidden"
                            name="persona_id"
                            value="<?= (int) $personaEncontrada['id'] ?>"
                        >

                        <button
                            type="submit"
                            class="activity-action-primary"
                        >
                            Agregar a la actividad
                            <span aria-hidden="true">+</span>
                        </button>

                    </form>

                </div>

            <?php endif; ?>

        <div class="edition-students-list">

            <?php if (!$inscriptos): ?>

                <div class="edition-students-empty">

                    <strong>
                        Todavía no hay alumnos inscriptos.
                    </strong>

                    <p>
                        Las inscripciones confirmadas aparecerán acá.
                    </p>

                </div>

            <?php else: ?>

                <?php foreach ($inscriptos as $inscripto): ?>

                    <article class="edition-student-row">

                        <div class="edition-student-row__identity">

                            <strong>
                                <?= htmlspecialchars(
                                    $inscripto['apellido']
                                    . ', '
                                    . $inscripto['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <span>
                                DNI
                                <?= htmlspecialchars(
                                    $inscripto['dni'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>


                        <div class="edition-student-row__email">

                            <?= htmlspecialchars(
                                $inscripto['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div class="edition-student-row__status">

                            <?= htmlspecialchars(
                                etiquetaEstadoInscripcion(
                                    $inscripto['inscripcion_estado']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


    </section>

</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>