<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/AlumnoEstadisticas.php';
$error='';
try {$f=AlumnoEstadisticas::filtros($_GET);}catch(DomainException $e){$error=$e->getMessage();$f=AlumnoEstadisticas::filtros([]);}
[$filter,$params]=AlumnoEstadisticas::consulta($f);
if($error!=='')$filter.=' AND 1=0';
$q=$f['q'];
$page=max(1,(int)($_GET['pagina']??1));$size=25;
$resumen=AlumnoEstadisticas::resumen($pdo);$weeks=$resumen['semanas'];$current=$weeks[7];$previous=$weeks[6];
$actividades=$pdo->query('SELECT id,titulo FROM actividades ORDER BY titulo,id')->fetchAll();
$linkSemana=static fn(array $w,string $tipo):string=>'/admin/alumnos/?'.http_build_query(['tipo_fecha'=>$tipo,'desde'=>$w['desde'],'hasta'=>$w['hasta']]);
$stmt=$pdo->prepare('SELECT COUNT(*) FROM personas p '.$filter);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
$stmt=$pdo->prepare("SELECT p.id,p.nombre,p.apellido,p.dni,p.email,p.estado,p.creado_en,(SELECT COUNT(*) FROM inscripciones i WHERE i.persona_id=p.id) AS inscripciones FROM personas p $filter ORDER BY p.apellido,p.nombre,p.id LIMIT $size OFFSET $offset");$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Alumnos';$pageSection='alumnos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page alumno-page"><header class="admin-page__header"><div><h1>Alumnos</h1><p>Buscá personas registradas, actualizá sus datos y consultá sus inscripciones.</p></div></header>
<div class="alumno-stats" aria-label="Resumen general de alumnos">
<a class="admin-card" href="/admin/alumnos/"><span>Personas registradas</span><strong><?= $resumen['total'] ?></strong><small>Total de registros en ECAP</small></a>
<a class="admin-card" href="<?= AdminForm::escape($linkSemana($current,'alta')) ?>"><span>Altas esta semana</span><strong><?= $current['personas'] ?></strong><small><?= $previous['personas'] ?> en la semana anterior completa</small></a>
<a class="admin-card" href="<?= AdminForm::escape($linkSemana($current,'inscripcion')) ?>"><span>Inscripciones esta semana</span><strong><?= $current['inscripciones'] ?></strong><small><?= $previous['inscripciones'] ?> en la semana anterior completa</small></a>
</div>
<details class="admin-card alumno-evolution"><summary>Evolución de las últimas ocho semanas</summary><p class="admin-text-muted">Semanas de lunes a domingo, hora de Argentina. La semana actual está en curso. Las altas reflejan la fecha de registro, que puede incluir importaciones. Las inscripciones incluyen todos sus estados.</p>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Semana desde</th><th>Altas</th><th>Inscripciones</th></tr></thead><tbody>
<?php foreach(array_reverse($weeks) as $w):?><tr><td data-label="Semana desde"><?= $w['label'] ?><?= $w['actual']?' · En curso':'' ?></td><td data-label="Altas"><a href="<?= AdminForm::escape($linkSemana($w,'alta')) ?>"><?= $w['personas'] ?></a></td><td data-label="Inscripciones"><a href="<?= AdminForm::escape($linkSemana($w,'inscripcion')) ?>"><?= $w['inscripciones'] ?></a></td></tr><?php endforeach;?></tbody></table></div></details>
<?php if($error!==''):?><p role="alert"><?= AdminForm::escape($error) ?></p><?php endif;?>
<form method="get" class="admin-card alumno-filters">
<div class="alumno-filter-search"><label for="buscar-alumno">Nombre, apellido, DNI o correo</label><input class="admin-input" type="search" autocorrect="off" autocapitalize="none" spellcheck="false" autocomplete="off" name="q" id="buscar-alumno" value="<?= AdminForm::escape($q) ?>" placeholder="Buscar una persona" maxlength="100"></div>
<div><label for="tipo-fecha">Filtrar fechas por</label><select class="admin-input" id="tipo-fecha" name="tipo_fecha"><option value="alta" <?= $f['tipo_fecha']==='alta'?'selected':'' ?>>Alta de la persona</option><option value="inscripcion" <?= $f['tipo_fecha']==='inscripcion'?'selected':'' ?>>Inscripción a una actividad</option></select></div>
<div><label for="desde">Desde</label><input class="admin-input" type="date" id="desde" name="desde" value="<?= AdminForm::escape($f['desde']) ?>"></div>
<div><label for="hasta">Hasta</label><input class="admin-input" type="date" id="hasta" name="hasta" value="<?= AdminForm::escape($f['hasta']) ?>"></div>
<div class="alumno-filter-search"><label for="actividad">Actividad</label><select class="admin-input" id="actividad" name="actividad"><option value="0">Todas las actividades</option><?php foreach($actividades as $a):?><option value="<?= (int)$a['id'] ?>" <?= $f['actividad']===(int)$a['id']?'selected':'' ?>><?= AdminForm::escape($a['titulo']) ?></option><?php endforeach;?></select></div>
<div class="alumno-actions"><button type="submit" class="admin-button">Filtrar</button><a class="activity-action-secondary" href="/admin/alumnos/">Limpiar</a></div>
</form>
<p class="admin-text-muted"><?= $total ?> <?= $total===1?'persona encontrada':'personas encontradas' ?> · Cada persona aparece una sola vez. Los indicadores superiores muestran el total general.</p>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona</th><th>Contacto</th><th>Alta en ECAP</th><th>Inscripciones</th><th></th></tr></thead><tbody>
<?php foreach($rows as $p):?><tr><td data-label="Persona"><strong><?= AdminForm::escape($p['apellido'].', '.$p['nombre']) ?></strong><small>DNI <?= AdminForm::escape($p['dni']) ?><?= $p['estado']==='inactivo'?' · Inactivo':'' ?></small></td><td data-label="Contacto"><?= AdminForm::escape($p['email']) ?></td><td data-label="Alta en ECAP"><?= !empty($p['creado_en'])?AdminForm::escape((new DateTimeImmutable($p['creado_en']))->format('d/m/Y')):'Sin fecha' ?></td><td data-label="Inscripciones"><?= (int)$p['inscripciones'] ?></td><td><a class="activity-action-secondary" href="/admin/alumnos/editar.php?id=<?= (int)$p['id'] ?>">Ver ficha</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5">No encontramos personas con esa búsqueda.</td></tr><?php endif;?></tbody></table></div>
<?php if($pages>1):?><nav class="alumno-actions" aria-label="Páginas de alumnos"><?php if($page>1):?><a class="activity-action-secondary" href="?<?= AdminForm::escape(http_build_query(array_merge($f,['pagina'=>$page-1]))) ?>">Anterior</a><?php endif;?><span>Página <?= $page ?> de <?= $pages ?></span><?php if($page<$pages):?><a class="activity-action-secondary" href="?<?= AdminForm::escape(http_build_query(array_merge($f,['pagina'=>$page+1]))) ?>">Siguiente</a><?php endif;?></nav><?php endif;?>
</section><?php require __DIR__.'/../includes/footer.php';?>
