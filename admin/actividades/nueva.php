<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Nueva actividad';
$pageSection = 'actividades';

$errores = [];

$valores = [
    'tipo_actividad_id' => '',
    'titulo' => '',
    'descripcion' => '',
    'programa' => '',
];

$stmt = $pdo->query("
    SELECT id, nombre
    FROM tipos_actividad
    WHERE activo = 1
    ORDER BY nombre ASC
");

$tiposActividad = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Generar slug
|--------------------------------------------------------------------------
*/

function generarSlugActividad(string $texto): string
{
    $texto = trim($texto);

    if (function_exists('transliterator_transliterate')) {
        $texto = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; Lower()',
            $texto
        );
    } else {
        $texto = strtolower($texto);

        $texto = strtr($texto, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    $texto = trim($texto, '-');

    return $texto !== '' ? $texto : 'actividad';
}


/*
|--------------------------------------------------------------------------
| Procesar formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::validate($_POST['_csrf'] ?? '')) {
        $errores[] = 'La sesión del formulario venció. Volvé a intentarlo.';
    }

    $valores['tipo_actividad_id'] =
        trim((string) ($_POST['tipo_actividad_id'] ?? ''));

    $valores['titulo'] =
        trim((string) ($_POST['titulo'] ?? ''));

    $valores['descripcion'] =
        trim((string) ($_POST['descripcion'] ?? ''));

    $valores['programa'] =
        trim((string) ($_POST['programa'] ?? ''));


    if ($valores['tipo_actividad_id'] === '') {
        $errores[] = 'Seleccioná un tipo de actividad.';
    }

    if ($valores['titulo'] === '') {
        $errores[] = 'Ingresá el título de la actividad.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validar tipo de actividad
    |--------------------------------------------------------------------------
    */

    if ($valores['tipo_actividad_id'] !== '') {

        $stmtTipo = $pdo->prepare("
            SELECT id
            FROM tipos_actividad
            WHERE id = ?
              AND activo = 1
            LIMIT 1
        ");

        $stmtTipo->execute([
            (int) $valores['tipo_actividad_id']
        ]);

        if (!$stmtTipo->fetchColumn()) {
            $errores[] = 'El tipo de actividad seleccionado no es válido.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Crear actividad
    |--------------------------------------------------------------------------
    */

    if (!$errores) {

        $slugBase = generarSlugActividad($valores['titulo']);
        $slug = $slugBase;
        $contador = 2;

        $stmtSlug = $pdo->prepare("
            SELECT COUNT(*)
            FROM actividades
            WHERE slug = ?
        ");

        while (true) {

            $stmtSlug->execute([$slug]);

            if ((int) $stmtSlug->fetchColumn() === 0) {
                break;
            }

            $slug = $slugBase . '-' . $contador;
            $contador++;
        }


        $stmtInsert = $pdo->prepare("
            INSERT INTO actividades (
                tipo_actividad_id,
                titulo,
                slug,
                descripcion,
                programa,
                estado
            )
            VALUES (
                :tipo_actividad_id,
                :titulo,
                :slug,
                :descripcion,
                :programa,
                'borrador'
            )
        ");

        $stmtInsert->execute([
            'tipo_actividad_id' => (int) $valores['tipo_actividad_id'],
            'titulo' => $valores['titulo'],
            'slug' => $slug,
            'descripcion' => $valores['descripcion'] !== ''
                ? $valores['descripcion']
                : null,
            'programa' => $valores['programa'] !== ''
                ? $valores['programa']
                : null,
        ]);

        $actividadId = (int) $pdo->lastInsertId();

        Csrf::regenerate();

        header(
            'Location: /admin/actividades/editar.php?id='
            . $actividadId
            . '&creada=1'
        );

        exit;
    }
}


require __DIR__ . '/../includes/header.php';

?>

<section class="admin-page admin-page--editor">

    <div class="activity-create-nav">
        <a href="/admin/actividades/" class="activity-create-back">
            <span aria-hidden="true">←</span>
            Actividades
        </a>
    </div>


<header class="activity-create-hero">

    <h1>Crear actividad</h1>

    <nav class="activity-steps" aria-label="Progreso de creación">

        <div class="activity-step is-active">
            <span class="activity-step__number">01</span>
            <span class="activity-step__label">General</span>
        </div>

        <div class="activity-step">
            <span class="activity-step__number">02</span>
            <span class="activity-step__label">Edición</span>
        </div>

        <div class="activity-step">
            <span class="activity-step__number">03</span>
            <span class="activity-step__label">Docentes</span>
        </div>

        <div class="activity-step">
            <span class="activity-step__number">04</span>
            <span class="activity-step__label">Clases</span>
        </div>

        <div class="activity-step">
            <span class="activity-step__number">05</span>
            <span class="activity-step__label">Inscripción</span>
        </div>

        <div class="activity-step">
            <span class="activity-step__number">06</span>
            <span class="activity-step__label">Materiales</span>
        </div>

    </nav>

</header>


    <?php if ($errores): ?>

        <div class="admin-alert admin-alert--error">

            <strong>Hay información que necesitamos revisar.</strong>

            <ul>
                <?php foreach ($errores as $error): ?>
                    <li>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>


    <form method="post" class="activity-create-form">

        <?= Csrf::field() ?>


        <section class="activity-editor-surface">

            <header class="activity-editor-heading">

                <div>
                    <h2>Información general</h2>

                    <p>
                        Empecemos por definir qué vas a crear
                        y cómo se va a presentar.
                    </p>
                </div>

                <span>01</span>

            </header>


            <div class="activity-editor-body">


                <!-- Tipo de actividad -->

                <fieldset class="activity-type-field">

                    <legend>
                        ¿Qué tipo de actividad vas a crear?
                    </legend>

                    <div class="activity-type-options">

                        <?php foreach ($tiposActividad as $tipo): ?>

                            <?php
                            $tipoId = (string) $tipo['id'];
                            $seleccionado =
                                $tipoId === $valores['tipo_actividad_id'];
                            ?>

                            <label class="activity-type-option">

                                <input
                                    type="radio"
                                    name="tipo_actividad_id"
                                    value="<?= (int) $tipo['id'] ?>"
                                    <?= $seleccionado ? 'checked' : '' ?>
                                    required
                                >

                                <span>
                                    <?= htmlspecialchars(
                                        $tipo['nombre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </fieldset>


                <!-- Título -->

                <div class="activity-editor-field">

                    <label for="titulo">
                        Título
                    </label>

                    <input
                        type="text"
                        id="titulo"
                        name="titulo"
                        maxlength="255"
                        value="<?= htmlspecialchars(
                            $valores['titulo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Ej. Diplomatura en Derecho Administrativo"
                        required
                    >

                </div>


                <!-- Descripción -->

                <div class="activity-editor-field">

                    <div class="activity-editor-field__heading">

                        <label for="descripcion">
                            Descripción
                        </label>

                        <span>
                            Presentación pública
                        </span>

                    </div>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="6"
                        placeholder="Contá brevemente de qué trata la actividad y cuál es su propuesta."
                    ><?= htmlspecialchars(
                        $valores['descripcion'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>


                <!-- Programa -->

                <div class="activity-editor-field">

                    <div class="activity-editor-field__heading">

                        <label for="programa">
                            Programa
                        </label>

                        <span>
                            Opcional por ahora
                        </span>

                    </div>

                    <textarea
                        id="programa"
                        name="programa"
                        rows="7"
                        placeholder="Ingresá los contenidos, ejes temáticos o programa académico."
                    ><?= htmlspecialchars(
                        $valores['programa'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>

            </div>


            <footer class="activity-editor-actions">

                <a
                    href="/admin/actividades/"
                    class="activity-action-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="activity-action-primary"
                >
                    Crear y continuar
                    <span aria-hidden="true">→</span>
                </button>

            </footer>

        </section>

    </form>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>