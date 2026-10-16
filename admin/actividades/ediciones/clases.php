<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../app/services/AdminForm.php';
$edicionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$claseId = filter_input(INPUT_GET, 'clase', FILTER_VALIDATE_INT) ?: null;
$stmt = $pdo->prepare('SELECT e.*,a.titulo FROM ediciones e JOIN actividades a ON a.id=e.actividad_id WHERE e.id=?');
$stmt->execute([$edicionId]); $edicion = $stmt->fetch();
if (!$edicion) { http_response_code(404); exit('Edición no encontrada.'); }
$clase = null;
if ($claseId) {
    $stmt = $pdo->prepare('SELECT * FROM clases WHERE id=? AND edicion_id=?'); $stmt->execute([$claseId,$edicionId]); $clase = $stmt->fetch();
    if (!$clase) { http_response_code(404); exit('Clase no encontrada.'); }
}
$valores = ['titulo'=>$clase['titulo'] ?? '', 'fecha_inicio'=>isset($clase['fecha_inicio'])?substr(str_replace(' ','T',$clase['fecha_inicio']),0,16):'', 'fecha_fin'=>!empty($clase['fecha_fin'])?substr(str_replace(' ','T',$clase['fecha_fin']),0,16):''];
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminForm::csrf();
    foreach ($valores as $key=>$_) $valores[$key]=AdminForm::text($key);
    $inicio = AdminForm::date($valores['fecha_inicio']); $fin = AdminForm::date($valores['fecha_fin']);
    if (!$inicio || !$fin || $fin <= $inicio) $errores[] = 'Indicá un inicio válido y una finalización posterior.';
    if ($valores['titulo']==='' || mb_strlen($valores['titulo'])>255) $errores[] = 'Ingresá un título de hasta 255 caracteres.';
    if (!$errores) try {
        $pdo->beginTransaction();
        // Mismo orden de bloqueo que la configuración de edición.
        $stmt=$pdo->prepare('SELECT id FROM actividades WHERE id=? FOR UPDATE'); $stmt->execute([$edicion['actividad_id']]);
        $stmt=$pdo->prepare('SELECT * FROM ediciones WHERE id=? FOR UPDATE'); $stmt->execute([$edicionId]); $actual=$stmt->fetch();
        if (!$actual) throw new DomainException('La edición ya no está disponible.');
        if ($inicio < new DateTimeImmutable($actual['fecha_inicio']) || (!$actual['fecha_fin'] || $fin > new DateTimeImmutable($actual['fecha_fin']))) throw new DomainException('La clase debe quedar dentro del período de la edición. Ampliá primero sus fechas en Configuración.');
        $stmt=$pdo->prepare('SELECT * FROM clases WHERE edicion_id=? ORDER BY fecha_inicio,id FOR UPDATE'); $stmt->execute([$edicionId]); $existentes=$stmt->fetchAll();
        if ($claseId && !in_array($claseId,array_column($existentes,'id'),true)) throw new DomainException('La clase ya no está disponible.');
        foreach ($existentes as $otra) {
            if ((int)$otra['id']===$claseId) continue;
            if ($inicio < new DateTimeImmutable($otra['fecha_fin'] ?? $otra['fecha_inicio']) && $fin > new DateTimeImmutable($otra['fecha_inicio'])) throw new DomainException('Ese horario se superpone con otra clase de esta edición.');
        }
        if ($claseId) $pdo->prepare('UPDATE clases SET titulo=?,fecha_inicio=?,fecha_fin=? WHERE id=? AND edicion_id=?')->execute([$valores['titulo'],$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s'),$claseId,$edicionId]);
        else $pdo->prepare('INSERT INTO clases(edicion_id,titulo,fecha_inicio,fecha_fin) VALUES(?,?,?,?)')->execute([$edicionId,$valores['titulo'],$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s')]);
        $stmt=$pdo->prepare('SELECT id FROM clases WHERE edicion_id=? ORDER BY fecha_inicio,id'); $stmt->execute([$edicionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $i=>$id) $pdo->prepare('UPDATE clases SET numero=? WHERE id=?')->execute([$i+1,$id]);
        $pdo->commit(); Csrf::regenerate(); header('Location: /admin/actividades/ediciones/clases.php?id='.$edicionId.'&guardada=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[]=$e instanceof DomainException?$e->getMessage():'No se pudo guardar la clase. Volvé a intentarlo.';
        if (!$e instanceof DomainException) error_log('Error guardando clase ECAP: '.$e->getMessage());
    }
}
$stmt=$pdo->prepare('SELECT * FROM clases WHERE edicion_id=? ORDER BY fecha_inicio,id'); $stmt->execute([$edicionId]); $clases=$stmt->fetchAll();
$pageTitle='Clases'; $pageSection='actividades';
require __DIR__ . '/../../includes/header.php';
?>
<section class="admin-page admin-page--workspace">
<div class="activity-workspace-back"><a href="/admin/actividades/editar.php?id=<?= (int)$edicion['actividad_id'] ?>">← Volver a la actividad</a></div>
<header class="activity-workspace-hero"><h1><?= AdminForm::escape($edicion['titulo']) ?></h1></header>
<nav class="edition-admin-nav" aria-label="Gestión de edición"><a class="edition-admin-nav__item" href="/admin/actividades/ediciones/configuracion.php?id=<?= (int)$edicionId ?>">Configuración y docentes</a><span class="edition-admin-nav__item is-active">Clases</span><a class="edition-admin-nav__item" href="/admin/actividades/ediciones/editar.php?id=<?= (int)$edicionId ?>">Alumnos</a></nav>
<?php if ($errores): ?><div class="admin-alert admin-alert--error" role="alert"><ul><?php foreach ($errores as $error): ?><li><?= AdminForm::escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (isset($_GET['guardada']) && !$errores): ?><div class="edition-message edition-message--success" role="status">Clase guardada. No se enviaron correos.</div><?php endif; ?>
<div class="activity-workspace-content"><h2>Clases de la edición</h2><p>Horarios de Argentina. Período: <?= AdminForm::escape($edicion['fecha_inicio']) ?> a <?= AdminForm::escape($edicion['fecha_fin']) ?>.</p>
<p>Para un curso, configurá primero el período completo y ajustá la primera clase antes de agregar los siguientes encuentros. Los recordatorios usan estos horarios y el acceso de Zoom de la edición. Cambiar una clase no vuelve a enviar recordatorios ya enviados.</p>
<?php foreach ($clases as $item): ?><div class="activity-general-row"><strong><?= (int)$item['numero'] ?>. <?= AdminForm::escape($item['titulo']) ?></strong><span><?= AdminForm::escape($item['fecha_inicio'].' — '.$item['fecha_fin']) ?></span><a href="?id=<?= (int)$edicionId ?>&clase=<?= (int)$item['id'] ?>">Editar clase</a></div><?php endforeach; ?>
<form method="post" class="activity-edition-form"><?= Csrf::field() ?><h2><?= $clase?'Editar clase':'Agregar clase' ?></h2>
<div class="activity-editor-field"><label for="titulo">Título de la clase</label><input id="titulo" name="titulo" maxlength="255" value="<?= AdminForm::escape($valores['titulo']) ?>" required></div>
<div class="activity-edition-fields activity-edition-fields--two"><?php foreach (['fecha_inicio'=>'Inicio','fecha_fin'=>'Finalización'] as $key=>$label): ?><div class="activity-editor-field"><label for="<?= $key ?>"><?= $label ?></label><input type="datetime-local" id="<?= $key ?>" name="<?= $key ?>" value="<?= AdminForm::escape($valores[$key]) ?>" required></div><?php endforeach; ?></div>
<footer class="activity-edition-actions"><a class="activity-action-secondary" href="?id=<?= (int)$edicionId ?>">Volver al listado</a><button class="activity-action-primary" type="submit">Guardar clase</button></footer></form></div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
