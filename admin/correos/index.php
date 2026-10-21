<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/CorreoControl.php';
$edicion=filter_input(INPUT_GET,'edicion',FILTER_VALIDATE_INT)?:null;
$control=new CorreoControl($pdo);$error=null;$confirmar=null;
$pendientes=$control->pendientes($edicion);
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 $id=(int)AdminForm::text('inscripcion');$clase=(int)AdminForm::text('clase');
 foreach($pendientes as $p)if($p['tipo']!=='certificado' && (int)$p['inscripcion_id']===$id && (int)$p['clase_id']===$clase)$confirmar=$p;
 try{
  if(!$confirmar)throw new DomainException('El correo ya no requiere atención. Actualizá la página.');
  if($confirmar['incierto'])throw new DomainException('El resultado es incierto. Revisá el servidor de correo antes de reenviar.');
  if(AdminForm::text('accion')==='enviar'){
   // Confirmar sobre la versión mostrada, para no enviar a una dirección que cambió.
   if(!hash_equals(hash('sha256',$confirmar['email']),AdminForm::text('destino')))throw new DomainException('La dirección cambió. Revisala antes de reintentar.');
   $control->enviar($id,$clase?:null,'manual',(int)Auth::id());
   header('Location: /admin/correos/?'.http_build_query(array_filter(['edicion'=>$edicion,'guardado'=>1])));exit;
  }
 }catch(Throwable $e){$error=$e instanceof DomainException?$e->getMessage():'No se pudo completar la operación. El resultado queda pendiente de revisión.';$confirmar=null;}
 $pendientes=$control->pendientes($edicion);
}
$cron=$control->cron();$pageTitle='Correos';$pageSection='correos';
require __DIR__.'/../includes/header.php';
?>
<section class="admin-page correo-page">
<header class="admin-page__header"><div><h1>Control de correos</h1><p><?= count($pendientes) ?> correos requieren atención · Confirmaciones, recordatorios y certificados.</p></div><?php if($edicion): ?><a class="activity-action-secondary" href="/admin/actividades/ediciones/editar.php?id=<?= $edicion ?>">Volver a la edición</a><?php endif; ?></header>
<?php if($error): ?><div class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($error) ?></div><?php endif; ?>
<?php if(isset($_GET['guardado'])): ?><div class="edition-message edition-message--success" role="status">El servidor aceptó el correo. Ya no requiere atención.</div><?php endif; ?>
<?php if($confirmar): ?>
<section class="admin-card correo-confirm"><h2>Confirmar reintento</h2><p>Se enviará <?= $confirmar['clase_id']?'un recordatorio':'una confirmación de inscripción' ?> a <strong><?= AdminForm::escape($confirmar['nombre'].' '.$confirmar['apellido']) ?></strong> (<?= AdminForm::escape($confirmar['email']) ?>) para <?= AdminForm::escape($confirmar['titulo']) ?>.</p><p>Verificá la dirección antes de continuar. Si la persona ya lo recibió, no lo reenvíes.</p><form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="enviar"><input type="hidden" name="inscripcion" value="<?= (int)$confirmar['inscripcion_id'] ?>"><input type="hidden" name="clase" value="<?= (int)$confirmar['clase_id'] ?>"><input type="hidden" name="destino" value="<?= hash('sha256',$confirmar['email']) ?>"><button class="admin-button" type="submit">Reintentar envío</button> <a class="activity-action-secondary" href="/admin/correos/<?= $edicion?'?edicion='.$edicion:'' ?>">Cancelar</a></form></section>
<?php endif; ?>
<?php if(!$pendientes): ?><div class="admin-card"><h2>Sin correos que requieran atención</h2><p>No hay fallos ni envíos pendientes detectados para actividades vigentes dentro del período de seguimiento.</p></div>
<?php else: ?>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona / Actividad</th><th>Correo</th><th>Situación</th><th>Acción</th></tr></thead><tbody>
<?php foreach($pendientes as $p): $incierto=(bool)$p['incierto']; $reciente=$incierto && strtotime($p['ultimo_intento_en']??'')>time()-300; ?>
<tr><td><strong><?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?></strong><small><?= AdminForm::escape($p['email']) ?></small><small><?= AdminForm::escape($p['titulo']) ?></small></td><td data-label="Correo"><?= $p['tipo']==='certificado'?'Certificado':($p['clase_id']?'Recordatorio':'Confirmación') ?><small><?= AdminForm::escape(date('d/m/Y H:i',strtotime($p['fecha']))) ?></small></td><td data-label="Situación"><span class="admin-status <?= $p['estado']==='error'?'correo-status--error':'' ?>"><?= $reciente?'En proceso':($incierto?'Resultado incierto':($p['estado']==='error'?'Falló el envío':'Sin envío registrado')) ?></span><small><?= $p['ultimo_intento_en']?'Último intento: '.AdminForm::escape(date('d/m H:i',strtotime($p['ultimo_intento_en']))):'No se registró un intento.' ?></small></td><td data-label="Acción"><?php if($p['tipo']==='certificado'): ?><a class="activity-action-secondary" href="/admin/actividades/ediciones/certificados.php?id=<?= (int)$p['edicion_id'] ?>">Revisar certificado</a><?php elseif(!$incierto): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="inscripcion" value="<?= (int)$p['inscripcion_id'] ?>"><input type="hidden" name="clase" value="<?= (int)$p['clase_id'] ?>"><button class="activity-action-secondary" type="submit">Reintentar</button></form><?php else: ?><span class="admin-text-muted"><?= $reciente?'Actualizá en unos minutos':'Revisar servidor' ?></span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<details class="admin-card correo-cron" <?= $cron['alerta']?'open':'' ?>><summary>Recordatorios automáticos · <?= $cron['alerta']?'Requieren revisión':($cron['ultima']?'Ver última ejecución':'Esperando primera ejecución') ?></summary>
<?php if($cron['alerta']): ?><p role="alert"><strong>No hay una ejecución completada registrada hoy.</strong> Revisá la tarea programada de Hostinger.</p><?php endif; ?>
<p><?= $cron['ultima']?'Última ejecución: '.AdminForm::escape(date('d/m/Y H:i',strtotime($cron['ultima']['inicio']))).' · '.($cron['ultima']['estado']==='completado'?'Completada':'Requiere revisión'):'Todavía no hay ejecuciones registradas.' ?></p>
<?php if($cron['ultima']): ?><p class="admin-text-muted"><?= (int)$cron['ultima']['enviados'] ?> aceptados por el servidor · <?= (int)$cron['ultima']['errores'] ?> errores en esa ejecución.</p><?php endif; ?>
<p class="admin-text-muted">Horario de Argentina: 07:00, 07:15 y 07:30. Los recordatorios sin intento se señalan después de las 07:40 para inscripciones anteriores a las 07:30.</p></details>
<p class="admin-text-muted">Seguimiento desde <?= AdminForm::escape(date('d/m/Y H:i',strtotime($cron['desde']))) ?>. Los correos históricos sin registro no se consideran fallidos. «Aceptado» no confirma llegada a la bandeja de entrada.</p>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
