<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/CorreoControl.php';
require_once __DIR__.'/../../app/services/CertificadoDocenteService.php';
header('Cache-Control: no-store');
$edicion=filter_input(INPUT_GET,'edicion',FILTER_VALIDATE_INT)?:null;
$control=new CorreoControl($pdo);$error=null;$confirmar=null;
$pendientes=$control->pendientes($edicion);
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 $id=(int)AdminForm::text('inscripcion');$clase=(int)AdminForm::text('clase');
 foreach($pendientes as $p)if($p['tipo']===AdminForm::text('tipo') && (int)$p['inscripcion_id']===$id && (int)$p['clase_id']===$clase)$confirmar=$p;
 try{
  if(!$confirmar)throw new DomainException('El correo ya no requiere atención. Actualizá la página.');
  if($confirmar['ultimo_intento_en'] && strtotime($confirmar['ultimo_intento_en'])>time()-300)throw new DomainException('Hay un intento reciente. Esperá cinco minutos antes de reenviar.');
  if(AdminForm::text('accion')==='enviar'){
   // Confirmar sobre la versión mostrada, para no enviar a una dirección que cambió.
   if(!hash_equals(hash('sha256',$confirmar['email']),AdminForm::text('destino')))throw new DomainException('La dirección cambió. Revisala antes de reintentar.');
   if(in_array($confirmar['tipo'],['certificado','certificado_docente'],true)){
    $svc=$confirmar['tipo']==='certificado_docente'?new CertificadoDocenteService($pdo):new CertificadoService($pdo);$cfg=$svc->config((int)$confirmar['edicion_id']);$persona=null;
    foreach($svc->participantes((int)$confirmar['edicion_id']) as $row)if((int)$row['id']===$id)$persona=$row;
    if(!$persona)throw new DomainException('El certificado ya no está disponible.');
    $enviado=$svc->enviar((int)$confirmar['edicion_id'],$id,(int)Auth::id(),CertificadoService::huella($persona),(int)$cfg['revision'],true);
   }else{$enviado=$control->enviar($id,$clase?:null,'manual',(int)Auth::id(),0,true);}
   if(!$enviado)throw new DomainException('Este correo ya figura enviado. Actualizá la página.');
   header('Location: /admin/correos/?'.http_build_query(array_filter(['edicion'=>$edicion,'guardado'=>1])));exit;
  }
 }catch(Throwable $e){$error=$e instanceof DomainException?$e->getMessage():'No se pudo completar la operación. El resultado queda pendiente de revisión.';$confirmar=null;}
 $pendientes=$control->pendientes($edicion);
}

$cron=$control->cron();$pageTitle='Correos';$pageSection='correos';
require __DIR__.'/../includes/header.php';
?>
<section class="admin-page correo-page">
<header class="admin-page__header"><div><h1>Control de correos</h1><p><?= count($pendientes) ?> correos requieren atención · Confirmaciones, recordatorios y certificados.</p></div><?php if($edicion): ?><a class="activity-action-secondary" href="/admin/actividades/ediciones/editar.php?id=<?= $edicion ?>">Volver a la edición</a><?php endif; ?><a class="activity-action-secondary" href="/admin/correos/historial.php<?= $edicion?'?edicion='.$edicion:'' ?>">Historial de envíos</a></header>
<?php if($error): ?><div class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($error) ?></div><?php endif; ?>
<?php if(isset($_GET['guardado'])): ?><div class="edition-message edition-message--success" role="status">Correo enviado. Ya no requiere atención.</div><?php endif; ?>
<?php if($confirmar): ?>
<section class="admin-card correo-confirm"><h2>Confirmar reenvío</h2><p>Se enviará <?= in_array($confirmar['tipo'],['certificado','certificado_docente'],true)?'el certificado emitido':($confirmar['clase_id']?'un recordatorio':'una confirmación de inscripción') ?> a <strong><?= AdminForm::escape($confirmar['nombre'].' '.$confirmar['apellido']) ?></strong> (<?= AdminForm::escape($confirmar['email']) ?>) para <?= AdminForm::escape($confirmar['titulo']) ?>.</p><p>Verificá la dirección antes de continuar.</p><?php if($confirmar['incierto']): ?><p><strong>No hay constancia del resultado anterior. Si llegó, esta persona recibirá una copia.</strong></p><?php endif; ?><?php if($confirmar['tipo']==='confirmacion_inscripcion' && strtotime($confirmar['fecha'])<=time()): ?><p>La actividad ya comenzó o finalizó. Se enviará la confirmación con su fecha y datos originales.</p><?php endif; ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="enviar"><input type="hidden" name="tipo" value="<?= AdminForm::escape($confirmar['tipo']) ?>"><input type="hidden" name="inscripcion" value="<?= (int)$confirmar['inscripcion_id'] ?>"><input type="hidden" name="clase" value="<?= (int)$confirmar['clase_id'] ?>"><input type="hidden" name="destino" value="<?= hash('sha256',$confirmar['email']) ?>"><button class="admin-button" type="submit">Reenviar correo</button> <a class="activity-action-secondary" href="/admin/correos/<?= $edicion?'?edicion='.$edicion:'' ?>">Cancelar</a></form></section>
<?php endif; ?>
<?php if(!$pendientes): ?><div class="admin-card"><h2>Sin correos que requieran atención</h2><p>No hay fallos ni envíos pendientes detectados dentro del período de seguimiento.</p></div>
<?php else: ?>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona / Actividad</th><th>Correo</th><th>Motivo</th><th>Acción</th></tr></thead><tbody>
<?php foreach($pendientes as $p): $incierto=(bool)$p['incierto']; $reciente=$incierto && strtotime($p['ultimo_intento_en']??'')>time()-300; ?>
<tr><td><strong><?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?></strong><small><?= AdminForm::escape($p['email']) ?></small><small><?= AdminForm::escape($p['titulo']) ?></small></td><td data-label="Correo"><?= $p['tipo']==='certificado_docente'?'Certificado docente':($p['tipo']==='certificado'?'Certificado':($p['clase_id']?'Recordatorio':'Confirmación')) ?><small><?= AdminForm::escape(date('d/m/Y H:i',strtotime($p['fecha']))) ?></small></td><td data-label="Motivo"><span class="admin-status <?= $p['estado']==='error'?'correo-status--error':'' ?>"><?= $incierto?'Envío sin confirmar':($p['estado']==='error'?'No enviado':'Sin envío registrado') ?></span>
<small><?= AdminForm::escape($p['error_detalle'] ?: ($incierto?'El intento anterior no guardó su resultado. No podemos confirmar si salió.':'No se registró un envío completado.')) ?></small>
<?php if($p['ultimo_intento_en']): ?><small>Último intento: <?= AdminForm::escape(date('d/m H:i',strtotime($p['ultimo_intento_en']))) ?></small><?php endif; ?></td>
<td data-label="Acción"><?php if(!$reciente && (!$p['clase_id'] || strtotime($p['fecha'])>time())): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="tipo" value="<?= AdminForm::escape($p['tipo']) ?>"><input type="hidden" name="inscripcion" value="<?= (int)$p['inscripcion_id'] ?>"><input type="hidden" name="clase" value="<?= (int)$p['clase_id'] ?>"><button class="activity-action-secondary" type="submit">Reenviar</button></form><?php else: ?><small><?= $reciente?'En proceso':'La clase ya comenzó; no corresponde reenviar el recordatorio.' ?></small><?php endif; ?><a class="activity-action-secondary" href="/admin/correos/historial.php?<?= $p['tipo']==='certificado_docente'?'docente':'inscripcion' ?>=<?= (int)$p['inscripcion_id'] ?>&amp;edicion=<?= (int)$p['edicion_id'] ?>&amp;tipo=<?= AdminForm::escape($p['tipo']) ?>">Ver detalle</a></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php if($cron['alerta']): ?><div class="admin-alert admin-alert--error" role="alert">Hay recordatorios previstos para hoy, pero no se registró una ejecución completada. Revisá los correos pendientes y la tarea programada.</div><?php endif; ?>
<p class="admin-text-muted">Solo se muestran correos que necesitan atención. “Enviado” indica que el servidor de correo aceptó el mensaje; no confirma su llegada a la bandeja de entrada. Los envíos anteriores al seguimiento sin registro no se consideran fallidos.</p>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
