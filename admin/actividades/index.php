<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_once __DIR__ . '/../../app/services/AdminForm.php';
require __DIR__ . '/../../app/services/EstadoEdiciones.php';
$estadosInscripcion = ['proximamente'=>'Próximamente', 'inscripcion_abierta'=>'Abiertas', 'inscripcion_cerrada'=>'Cerradas'];
$errorEstado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminForm::csrf();
    $id = filter_var(AdminForm::text('edicion_id'), FILTER_VALIDATE_INT);
    $estado = AdminForm::text('estado');
    try {
        if (!$id || !isset($estadosInscripcion[$estado])) throw new DomainException('Elegí un estado de inscripción válido.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT estado,fecha_inicio FROM ediciones WHERE id=? FOR UPDATE');
        $stmt->execute([$id]); $actual = $stmt->fetch();
        if (!$actual || !in_array($actual['estado'], ['proximamente','inscripcion_abierta','inscripcion_cerrada','cupo_completo'],true)
            || new DateTimeImmutable($actual['fecha_inicio']) <= new DateTimeImmutable()) throw new DomainException('Esta edición ya no admite cambios rápidos de inscripción. Recargá el listado.');
        if ($actual['estado'] !== AdminForm::text('estado_anterior')) throw new DomainException('El estado cambió desde que abriste la página. Revisalo antes de guardar.');
        $pdo->prepare('UPDATE ediciones SET estado=? WHERE id=?')->execute([$estado,$id]);
        $pdo->commit();
        header('Location: /admin/actividades/?estado_guardado=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorEstado = $e instanceof DomainException ? $e->getMessage() : 'No se pudo cambiar el estado. Volvé a intentarlo.';
        if (!$e instanceof DomainException) error_log('Estado rápido ECAP: '.$e->getMessage());
    }
}

$pageTitle = 'Actividades';
$pageSection = 'actividades';

/*
|--------------------------------------------------------------------------
| Actividades
|--------------------------------------------------------------------------
|
| Mostramos cada actividad junto con su edición más reciente.
| La gestión interna de ediciones, clases y docentes se hará desde
| la ficha de cada actividad.
|
*/

$sql = "
    SELECT
        a.id,
        a.titulo,
        a.slug,
        a.estado,
        a.imagen_portada,

        ta.nombre AS tipo_actividad,

        e.id AS edicion_id,
        e.fecha_inicio,
        e.fecha_fin,
        e.modalidad,
        e.cupo_maximo,
        e.estado AS edicion_estado

    FROM actividades a

    INNER JOIN tipos_actividad ta
        ON ta.id = a.tipo_actividad_id

    LEFT JOIN ediciones e
        ON e.id = (
            SELECT e2.id
            FROM ediciones e2
            WHERE e2.actividad_id = a.id
            ORDER BY e2.fecha_inicio DESC, e2.id DESC
            LIMIT 1
        )

    ORDER BY
        CASE
            WHEN e.fecha_inicio IS NULL THEN 1
            ELSE 0
        END,
        e.fecha_inicio DESC,
        a.id DESC
";

$stmt = $pdo->query($sql);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helpers visuales
|--------------------------------------------------------------------------
*/

function adminEstadoEdicion(string $estado): string
{
    return match ($estado) {
        'borrador'             => 'Borrador',
        'proximamente'         => 'Próximamente',
        'inscripcion_abierta'  => 'Inscripción abierta',
        'cupo_completo'        => 'Cupo completo',
        'inscripcion_cerrada'  => 'Inscripción cerrada',
        'en_curso'             => 'En curso',
        'finalizada'           => 'Finalizada',
        'cancelada'            => 'Cancelada',
        default                => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function adminModalidad(string $modalidad): string
{
    return match ($modalidad) {
        'online'     => 'Online',
        'presencial' => 'Presencial',
        'hibrida'    => 'Híbrida',
        default      => ucfirst($modalidad),
    };
}

require __DIR__ . '/../includes/header.php';

?>

<section class="admin-page">

    <header class="admin-page__header">

        <div>
            <h1>Actividades</h1>

            <p>
                Creá y administrá seminarios, cursos, talleres,
                diplomaturas y sus respectivas ediciones.
            </p>
        </div>

        <a
            href="/admin/actividades/nueva.php"
            class="admin-button"
        >
            Nueva actividad
        </a>

    </header>


    <?php if ($errorEstado): ?><div class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($errorEstado) ?></div><?php endif; ?>
    <?php if (isset($_GET['estado_guardado']) && !$errorEstado): ?><div class="edition-message edition-message--success" role="status">Estado de inscripción guardado. No se enviaron correos.</div><?php endif; ?>
    <p class="admin-text-muted">El selector cambia las inscripciones de la edición indicada. En curso y Finalizada se actualizan por fecha; los cupos agotados se calculan automáticamente.</p>
    <?php if (!$actividades): ?>

        <div class="admin-card">

            <h2>No hay actividades todavía</h2>

            <p>
                Creá la primera actividad académica de ECAP
                para comenzar a configurar su edición, docentes y clases.
            </p>

            <a
                href="/admin/actividades/nueva.php"
                class="admin-button"
            >
                Crear actividad
            </a>

        </div>

    <?php else: ?>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Actividad</th>
                        <th>Tipo</th>
                        <th>Edición</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($actividades as $actividad): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $actividad['titulo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $actividad['tipo_actividad'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <?php if ($actividad['fecha_inicio']): ?>

                                <?= htmlspecialchars(
                                    date(
                                        'd/m/Y',
                                        strtotime($actividad['fecha_inicio'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                <span class="admin-text-muted">
                                    Sin edición
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if ($actividad['modalidad']): ?>

                                <?= htmlspecialchars(
                                    adminModalidad($actividad['modalidad']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                <span class="admin-text-muted">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php $rapido = in_array($actividad['edicion_estado'], ['proximamente','inscripcion_abierta','inscripcion_cerrada','cupo_completo'],true) && strtotime($actividad['fecha_inicio'] ?? '') > time(); ?>
                            <?php if ($rapido): ?>
                            <form method="post" class="admin-quick-state">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="edicion_id" value="<?= (int)$actividad['edicion_id'] ?>">
                                <input type="hidden" name="estado_anterior" value="<?= AdminForm::escape($actividad['edicion_estado']) ?>">
                                <label for="estado-<?= (int)$actividad['edicion_id'] ?>">Inscripciones</label>
                                <select class="admin-select" name="estado" id="estado-<?= (int)$actividad['edicion_id'] ?>" aria-label="<?= AdminForm::escape('Inscripciones: '.$actividad['titulo']) ?>">
                                <?php foreach ($estadosInscripcion as $value=>$label): ?><option value="<?= $value ?>" <?= ($actividad['edicion_estado']==='cupo_completo'?'inscripcion_abierta':$actividad['edicion_estado'])===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
                                </select>
                                <button type="submit" class="admin-quick-state__save" aria-label="<?= AdminForm::escape('Guardar inscripciones: '.$actividad['titulo']) ?>" title="Guardar estado">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2Z"/><path d="M7 3v6h9V3M7 21v-8h10v8M13 5v2"/></svg>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="admin-status"><?= AdminForm::escape($actividad['edicion_estado'] ? adminEstadoEdicion($actividad['edicion_estado']) : ($actividad['estado']==='publicada'?'Publicada':'Borrador')) ?></span>
                            <?php if (in_array($actividad['edicion_estado'],['en_curso','finalizada'],true)): ?><small class="admin-text-muted">Automático</small><?php endif; ?>
                            <?php endif; ?>
                            <?php if ($actividad['estado'] !== 'publicada'): ?><small class="admin-text-muted">Actividad no publicada</small><?php endif; ?>

                        </td>

                        <td class="admin-table__action">

                            <a
                                href="/admin/actividades/editar.php?id=<?= (int) $actividad['id'] ?>"
                                class="admin-table__link"
                            >
                                Gestionar
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>