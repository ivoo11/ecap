<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../../app/services/AdminForm.php';
$docentes = $pdo->query('SELECT d.id,d.activo,d.titulo_profesional,p.nombre,p.apellido FROM docentes d JOIN personas p ON p.id=d.persona_id ORDER BY p.apellido,p.nombre')->fetchAll();
$pageTitle = 'Docentes'; $pageSection = 'docentes';
require __DIR__ . '/../includes/header.php';
?>
<section class="admin-page">
<header class="admin-page__header"><div><h1>Docentes</h1><p>Perfiles para vincular a conferencias, cursos y otras actividades.</p></div><a class="activity-action-primary" href="/admin/docentes/editar.php">Crear docente</a></header>
<div class="activity-editor-surface"><div class="activity-editor-body">
<?php if (!$docentes): ?><p>Todavía no hay docentes cargados.</p><?php endif; ?>
<?php foreach ($docentes as $docente): ?><div class="activity-general-row"><strong><?= AdminForm::escape(trim($docente['titulo_profesional'].' '.$docente['nombre'].' '.$docente['apellido'])) ?></strong><span><?= $docente['activo'] ? 'Activo' : 'Inactivo' ?></span><a href="/admin/docentes/editar.php?id=<?= (int)$docente['id'] ?>">Editar perfil</a></div><?php endforeach; ?>
</div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
