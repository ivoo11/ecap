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

require __DIR__.'/listado.php';
require __DIR__.'/../includes/footer.php';
