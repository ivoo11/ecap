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


require_once __DIR__ . '/../../app/services/AdminForm.php';
$erroresGeneral = [];
$tiposGeneral = $pdo->query('SELECT id,nombre,activo FROM tipos_actividad ORDER BY nombre')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminForm::csrf();
    $seccion = 'general';
    $valoresGeneral = [];
    foreach (['titulo','descripcion','programa','tipo_actividad_id','estado'] as $key) $valoresGeneral[$key] = AdminForm::text($key);
    if ($valoresGeneral['titulo'] === '' || mb_strlen($valoresGeneral['titulo']) > 255) $erroresGeneral[] = 'Ingresá un título de hasta 255 caracteres.';
    foreach (['descripcion','programa'] as $key) if (strlen($valoresGeneral[$key]) > 65535) $erroresGeneral[] = 'La descripción o el programa son demasiado extensos.';
    $permitidos = array_column(array_filter($tiposGeneral, static fn($tipo) => $tipo['activo'] || $tipo['id']===$actividad['tipo_actividad_id']), 'id');
    if (!ctype_digit($valoresGeneral['tipo_actividad_id']) || !in_array((int)$valoresGeneral['tipo_actividad_id'],$permitidos,true)) $erroresGeneral[] = 'Seleccioná un tipo de actividad válido.';
    if (!in_array($valoresGeneral['estado'], ['borrador','publicada','archivada'],true)) $erroresGeneral[] = 'Seleccioná un estado válido.';
    $imagenNueva = null;
    if (!$erroresGeneral) try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT imagen_portada FROM actividades WHERE id=? FOR UPDATE'); $stmt->execute([$actividadId]); $actual = $stmt->fetch();
        if (!$actual) throw new DomainException('La actividad ya no está disponible.');
        $imagenNueva = AdminForm::image('imagen_portada', 'actividades');
        $imagen = $imagenNueva ?? (AdminForm::text('quitar_imagen')==='1' ? null : $actual['imagen_portada']);
        $pdo->prepare('UPDATE actividades SET titulo=?,descripcion=?,programa=?,tipo_actividad_id=?,estado=?,imagen_portada=? WHERE id=?')->execute([$valoresGeneral['titulo'],$valoresGeneral['descripcion'] ?: null,$valoresGeneral['programa'] ?: null,(int)$valoresGeneral['tipo_actividad_id'],$valoresGeneral['estado'],$imagen,$actividadId]);
        $pdo->commit(); Csrf::regenerate(); header('Location: /admin/actividades/editar.php?id='.$actividadId.'&seccion=general&guardada=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        AdminForm::discardImage($imagenNueva, 'actividades');
        $erroresGeneral[] = $e instanceof DomainException ? $e->getMessage() : 'No se pudo guardar la actividad. Volvé a intentarlo.';
        if (!$e instanceof DomainException) error_log('Error guardando actividad ECAP: '.$e->getMessage());
    }
    $actividad = array_replace($actividad, $valoresGeneral);
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

<?php if ($erroresGeneral): ?><div class="admin-alert admin-alert--error" role="alert"><ul><?php foreach ($erroresGeneral as $error): ?><li><?= AdminForm::escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (isset($_GET['guardada']) && !$erroresGeneral): ?><div class="edition-message edition-message--success" role="status">Actividad guardada.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="activity-create-form"><section class="activity-editor-surface"><div class="activity-editor-body">
<?= Csrf::field() ?>
<h2>Información general</h2><p>Estos datos se comparten entre todas las ediciones. El enlace de la actividad se conserva aunque cambies su título.</p>
<div class="admin-field"><label for="tipo_actividad_id">Tipo de actividad</label><select class="admin-select" id="tipo_actividad_id" name="tipo_actividad_id" required><?php foreach ($tiposGeneral as $tipo): if (!$tipo['activo'] && $tipo['id']!==$actividad['tipo_actividad_id']) continue; ?><option value="<?= (int)$tipo['id'] ?>" <?= (int)$actividad['tipo_actividad_id']===(int)$tipo['id']?'selected':'' ?>><?= AdminForm::escape($tipo['nombre']) ?></option><?php endforeach; ?></select></div>
<div class="activity-editor-field"><label for="titulo">Título</label><input id="titulo" name="titulo" maxlength="255" value="<?= AdminForm::escape($actividad['titulo']) ?>" required></div>
<?php foreach (['descripcion'=>'Descripción','programa'=>'Programa'] as $key=>$label): ?><div class="activity-editor-field"><label for="<?= $key ?>"><?= $label ?></label><textarea id="<?= $key ?>" name="<?= $key ?>" rows="7"><?= AdminForm::escape($actividad[$key]) ?></textarea></div><?php endforeach; ?>
<?php if ($actividad['imagen_portada']): ?><p><a href="/assets/img/actividades/<?= rawurlencode($actividad['imagen_portada']) ?>" target="_blank" rel="noopener">Ver imagen actual</a></p><p><label><input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen de portada</label></p><?php endif; ?>
<div class="activity-editor-field"><label for="imagen_portada">Imagen de portada (JPG, PNG o WebP, hasta 2 MB)</label><input id="imagen_portada" name="imagen_portada" type="file" accept="image/jpeg,image/png,image/webp"><p>Si hay un error al guardar, seleccioná el archivo nuevamente.</p></div>
<div class="admin-field"><label for="estado">Estado de la actividad</label><select class="admin-select" id="estado" name="estado"><?php foreach (['borrador'=>'Borrador','publicada'=>'Publicada','archivada'=>'Archivada'] as $value=>$label): ?><option value="<?= $value ?>" <?= $actividad['estado']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
<p>Publicar permite mostrar sus ediciones visibles. Pasar a borrador o archivar oculta la actividad en la web. Revisá también el estado de cada edición.</p>
</div><footer class="activity-editor-actions"><a class="activity-action-secondary" href="/admin/actividades/editar.php?id=<?= (int)$actividadId ?>">Ir a ediciones</a><button type="submit" class="activity-action-primary">Guardar actividad</button></footer></section></form>


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
                                    <?= $edicion['cupo_maximo'] === null ? 'Sin límite de cupo' : (int)$edicion['cupo_maximo'] . ' cupos' ?>
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