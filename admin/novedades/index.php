<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/Novedades.php';
header('Cache-Control: no-store');
if(!Auth::hasRole('superadmin')){http_response_code(403);exit('Acceso reservado al superadministrador.');}
$id=(int)($_GET['edicion']??0);$svc=new Novedades($pdo);$error='';$mensaje='';
try{$e=$svc->edicion($id);$c=$svc->campana($id);}catch(Throwable $x){http_response_code(503);exit('Anuncios no disponible. Verificá la actualización de la base de datos y la edición.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 try{
  $accion=AdminForm::text('accion');
  if($accion==='guardar'){$svc->guardar($id,AdminForm::text('asunto'),AdminForm::text('introduccion'),(int)Auth::id());$mensaje='Borrador guardado. Revisá la vista previa antes de confirmar.';}
  elseif($accion==='iniciar'){$svc->iniciar($id,AdminForm::text('huella'));$mensaje='Anuncio preparado. Los envíos se procesan por tandas.';}
  elseif($accion==='pausar'&&$c){$svc->pausar((int)$c['id']);$mensaje='Anuncio pausado. Un correo que ya esté en curso puede terminar su envío.';}
  elseif($accion==='continuar'&&$c){$svc->continuar((int)$c['id']);$mensaje='Anuncio listo para continuar.';}
  elseif($accion==='tanda'){if(!$c)throw new DomainException('Primero prepará el anuncio.');$n=$svc->procesar((int)$c['id']);$mensaje=$n.' correos enviados en esta tanda. Revisá los resultados de abajo.';}
  elseif($accion==='reintentar'&&$c){$svc->reintentar((int)$c['id'],(int)($_POST['destinatario']??0));$mensaje='Reintento pendiente para la próxima tanda.';}
  else throw new DomainException('Acción no válida.');
 }catch(Throwable $x){$error=$x instanceof DomainException?$x->getMessage():'No se completó la operación. Revisá el estado antes de volver a intentarlo.';error_log('ECAP anuncios: '.$x->getMessage());}
 $c=$svc->campana($id);
}
$dest=$svc->elegibles();$rows=$c?$svc->resultados((int)$c['id']):[];
$draft=!$c||$c['estado']==='borrador';
$asunto=$c['asunto']??'Nueva actividad ECAP: '.$e['titulo'];
$intro=$c['introduccion']??'Ya está abierta la inscripción a una nueva actividad de ECAP: '.$e['titulo'].'.';
$html=$c['contenido']??NovedadPlantilla::html($e,$intro);
$counts=array_count_values(array_column($rows,'estado'));
$labels=['enviado'=>'Enviados','pendiente'=>'Pendientes','enviando'=>'En proceso','error'=>'No enviados','revision'=>'Revisar intento','omitido'=>'Omitidos'];
$pageTitle='Anunciar actividad';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page">
<header class="admin-page__header"><div><h1>Anunciar actividad</h1><p><?= AdminForm::escape($e['titulo']) ?></p></div><a class="activity-action-secondary" href="/admin/actividades/ediciones/editar.php?id=<?= $id ?>">Volver a la edición</a></header>
<?php if($error): ?><p class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($error) ?></p><?php endif ?>
<?php if($mensaje): ?><p class="edition-message edition-message--success" role="status"><?= AdminForm::escape($mensaje) ?></p><?php endif ?>
<?php if($draft): ?>
<section class="admin-card"><h2>Preparar invitación</h2><p><?= count($dest) ?> direcciones con consentimiento vigente. Una copia por dirección.</p>
<form method="post" class="admin-form" style="display:grid;gap:20px"><?= Csrf::field() ?><input type="hidden" name="accion" value="guardar">
<label>Asunto<input class="admin-input" name="asunto" maxlength="190" required value="<?= AdminForm::escape($asunto) ?>"></label>
<label>Invitación<textarea class="admin-input" name="introduccion" rows="5" minlength="15" maxlength="1600" required><?= AdminForm::escape($intro) ?></textarea></label>
<button class="admin-button" type="submit">Guardar y actualizar vista previa</button></form></section>
<?php endif ?>
<section class="admin-card"><h2>Vista previa</h2><p><?= AdminForm::escape($asunto) ?></p><iframe title="Vista previa del anuncio" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= AdminForm::escape($html) ?>" style="width:100%;height:850px;border:0;background:#f4f5f7"></iframe></section>
<?php if($draft&&$c): ?>
<section class="admin-card"><h2>Confirmar anuncio</h2><p>Se enviará a <?= count($dest) ?> direcciones. El contenido quedará guardado tal como lo ves y no se repetirá al editar o volver a publicar la actividad.</p>
<?php if(!$svc->disponible($e)): ?><p>Para enviar, la actividad debe estar publicada, con inscripciones abiertas y cupos disponibles.</p><?php else: ?>
<form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="iniciar"><input type="hidden" name="huella" value="<?= $svc->huella($c,$dest) ?>"><button class="admin-button" type="submit">Confirmar envío a <?= count($dest) ?> direcciones</button></form>
<?php endif ?></section>
<?php elseif($c): ?>
<section class="admin-card"><h2>Resultado del anuncio</h2><p>Estado: <?= AdminForm::escape($c['estado']==='en_cola'?'En cola':ucfirst($c['estado'])) ?></p><p><?php foreach($labels as $key=>$label): ?><?= $label ?>: <?= $counts[$key]??0 ?> · <?php endforeach ?></p>
<p>Hasta 5 correos por tanda y 100 intentos de novedades cada 24 horas. Las bajas y los cambios de consentimiento se revisan antes de cada envío.</p>
<?php if(($counts['pendiente']??0)>0&&$c['estado']==='en_cola'): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="tanda"><button class="admin-button" type="submit">Procesar siguiente tanda</button></form><?php endif ?>
<?php if(in_array($c['estado'],['en_cola','pausada'],true)): ?><form method="post" style="margin:16px 0"><?= Csrf::field() ?><input type="hidden" name="accion" value="<?= $c['estado']==='en_cola'?'pausar':'continuar' ?>"><button class="activity-action-secondary" type="submit"><?= $c['estado']==='en_cola'?'Pausar anuncio':'Continuar anuncio' ?></button></form><?php endif ?>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona</th><th>Correo</th><th>Estado</th><th>Detalle</th><th>Acción</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= AdminForm::escape($r['nombre'].' '.$r['apellido']) ?></td><td><?= AdminForm::escape($r['email']) ?></td><td><?= $labels[$r['estado']]??AdminForm::escape($r['estado']) ?></td><td><?= AdminForm::escape($r['motivo']?:$r['enviado_en']) ?><?php if($r['ultimo_intento']): ?><br><small><?= AdminForm::escape($r['ultimo_intento'].' · '.$r['ultima_fase'].($r['smtp_codigo']?' · SMTP '.$r['smtp_codigo']:'')) ?></small><?php endif ?></td><td><?php if($r['estado']==='error'): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="reintentar"><input type="hidden" name="destinatario" value="<?= (int)$r['id'] ?>"><button class="activity-action-secondary" type="submit">Reintentar</button></form><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div><p>“Enviado” indica que el servidor aceptó el correo. No confirma su llegada a la bandeja de entrada.</p></section>
<?php endif ?>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
