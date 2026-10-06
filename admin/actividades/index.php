<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

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

                            <?php if ($actividad['edicion_estado']): ?>

                                <span class="admin-status">
                                    <?= htmlspecialchars(
                                        adminEstadoEdicion(
                                            $actividad['edicion_estado']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            <?php else: ?>

                                <span class="admin-status admin-status--muted">
                                    <?= $actividad['estado'] === 'publicada'
                                        ? 'Publicada'
                                        : 'Borrador' ?>
                                </span>

                            <?php endif; ?>

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