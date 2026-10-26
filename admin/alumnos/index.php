<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
$q=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
$page=max(1,(int)($_GET['pagina']??1));$size=25;
$filter="WHERE 1=1";$params=[];
if($q!==''){
    // LOCATE trata % y _ como texto, no como comodines.
    $filter.=' AND (LOCATE(?,CONCAT(p.nombre,\' \',p.apellido))>0 OR LOCATE(?,p.dni)>0 OR LOCATE(?,p.email)>0)';$params=[$q,$q,$q];
}
$stmt=$pdo->prepare('SELECT COUNT(*) FROM personas p '.$filter);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
$stmt=$pdo->prepare("SELECT p.id,p.nombre,p.apellido,p.dni,p.email,p.estado,(SELECT COUNT(*) FROM inscripciones i WHERE i.persona_id=p.id) AS inscripciones FROM personas p $filter ORDER BY p.apellido,p.nombre,p.id LIMIT $size OFFSET $offset");$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Alumnos';$pageSection='alumnos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page alumno-page"><header class="admin-page__header"><div><h1>Alumnos</h1><p>Buscá personas registradas, actualizá sus datos y consultá sus inscripciones.</p></div></header>
<form method="get" class="admin-card alumno-search"><label for="buscar-alumno">Nombre, apellido, DNI o correo</label><div><input class="admin-input" type="search" autocorrect="off" autocapitalize="none" spellcheck="false" autocomplete="off" name="q" id="buscar-alumno" value="<?= AdminForm::escape($q) ?>" placeholder="Buscar una persona" maxlength="100"><button type="submit" class="admin-button">Buscar</button><?php if($q!==''):?><a class="activity-action-secondary" href="/admin/alumnos/">Limpiar</a><?php endif;?></div></form>
<p class="admin-text-muted"><?= $total ?> <?= $total===1?'persona encontrada':'personas encontradas' ?> · Incluye personas todavía sin inscripciones.</p>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona</th><th>Contacto</th><th>Inscripciones</th><th></th></tr></thead><tbody>
<?php foreach($rows as $p):?><tr><td data-label="Persona"><strong><?= AdminForm::escape($p['apellido'].', '.$p['nombre']) ?></strong><small>DNI <?= AdminForm::escape($p['dni']) ?><?= $p['estado']==='inactivo'?' · Inactivo':'' ?></small></td><td data-label="Contacto"><?= AdminForm::escape($p['email']) ?></td><td data-label="Inscripciones"><?= (int)$p['inscripciones'] ?></td><td><a class="activity-action-secondary" href="/admin/alumnos/editar.php?id=<?= (int)$p['id'] ?>">Ver ficha</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="4">No encontramos personas con esa búsqueda.</td></tr><?php endif;?></tbody></table></div>
<?php if($pages>1):?><nav class="alumno-actions" aria-label="Páginas de alumnos"><?php if($page>1):?><a class="activity-action-secondary" href="?<?= AdminForm::escape(http_build_query(['q'=>$q,'pagina'=>$page-1])) ?>">Anterior</a><?php endif;?><span>Página <?= $page ?> de <?= $pages ?></span><?php if($page<$pages):?><a class="activity-action-secondary" href="?<?= AdminForm::escape(http_build_query(['q'=>$q,'pagina'=>$page+1])) ?>">Siguiente</a><?php endif;?></nav><?php endif;?>
</section><?php require __DIR__.'/../includes/footer.php';?>
