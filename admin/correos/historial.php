<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
$q=mb_substr(trim((string)($_GET['q']??'')),0,100);
$tipo=in_array($_GET['tipo']??'', ['confirmacion_inscripcion','recordatorio_clase','certificado','certificado_docente'],true)?$_GET['tipo']:'confirmacion_inscripcion';
$estado=in_array($_GET['estado']??'', ['enviado','error','pendiente','sin_registro'],true)?$_GET['estado']:'';
$edicion=filter_input(INPUT_GET,'edicion',FILTER_VALIDATE_INT)?:null;
$inscripcion=filter_input(INPUT_GET,'inscripcion',FILTER_VALIDATE_INT)?:null;
$docente=filter_input(INPUT_GET,'docente',FILTER_VALIDATE_INT)?:null;
$esDocente=$tipo==='certificado_docente';
$page=max(1,(int)($_GET['pagina']??1));$size=25;
$params=[$tipo];
$from=" FROM inscripciones i JOIN personas p ON p.id=i.persona_id JOIN ediciones e ON e.id=i.edicion_id JOIN actividades a ON a.id=e.actividad_id LEFT JOIN comunicaciones_transaccionales ct ON ct.inscripcion_id=i.id AND ct.tipo=?";
if($esDocente){
 $params=[];
 $from=" FROM certificados_docentes cd JOIN docentes d ON d.id=cd.docente_id JOIN personas p ON p.id=d.persona_id JOIN ediciones e ON e.id=cd.edicion_id JOIN actividades a ON a.id=e.actividad_id JOIN comunicaciones_transaccionales ct ON ct.certificado_docente_id=cd.id";
}
$where=" WHERE 1=1";
if($tipo==='recordatorio_clase')$where.=' AND ct.id IS NOT NULL';
if($esDocente && $docente){$where.=' AND d.id=?';$params[]=$docente;}
if(!$esDocente && $inscripcion){$where.=' AND i.id=?';$params[]=$inscripcion;}
if($edicion){$where.=' AND e.id=?';$params[]=$edicion;}
if($q!==''){$where.=" AND LOCATE(?,CONCAT_WS(' ',p.nombre,p.apellido,p.dni,p.email,ct.destinatario_email,a.titulo))>0";$params[]=$q;}
$count=$pdo->prepare("SELECT COALESCE(ct.estado,'sin_registro') estado,COUNT(*) total".$from.$where." GROUP BY COALESCE(ct.estado,'sin_registro')");$count->execute($params);$totals=array_column($count->fetchAll(),'total','estado');
if($estado==='sin_registro')$where.=' AND ct.id IS NULL';elseif($estado!==''){$where.=' AND ct.estado=?';$params[]=$estado;}
$count=$pdo->prepare('SELECT COUNT(*)'.$from.$where);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
$identidad=$esDocente?'d.id AS inscripcion_id,cd.emitido_en AS inscripto_en':'i.id AS inscripcion_id,i.inscripto_en';
$orden=$esDocente?'cd.emitido_en':'i.inscripto_en';$ordenId=$esDocente?'cd.id':'i.id';
$stmt=$pdo->prepare("SELECT $identidad,p.id AS persona_id,p.nombre,p.apellido,a.titulo,e.id AS edicion_id,ct.id,ct.estado,ct.destinatario_email,ct.enviado_en,ct.ultimo_intento_en,ct.error_detalle,ct.intentos,EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto') incierto".$from.$where." ORDER BY COALESCE(ct.ultimo_intento_en,$orden) DESC,$ordenId DESC LIMIT $size OFFSET $offset");$stmt->execute($params);$rows=$stmt->fetchAll();
$descartes=$pdo->query('SELECT comunicacion_id,descartado_en FROM correo_descartes')->fetchAll(PDO::FETCH_KEY_PAIR);
$eventos=[];$traceReady=true;
try{$pdo->query('SELECT id FROM correo_eventos LIMIT 0');}catch(PDOException $ex){$traceReady=false;}
$ids=array_values(array_filter(array_column($rows,'id')));$intentos=[];
if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$pdo->prepare("SELECT * FROM correo_intentos WHERE comunicacion_id IN ($marks) ORDER BY id DESC");$stmt->execute($ids);foreach($stmt->fetchAll() as $t)$intentos[$t['comunicacion_id']][]=$t;
 if($traceReady){$stmt=$pdo->prepare("SELECT ce.* FROM correo_eventos ce JOIN correo_intentos ci ON ci.id=ce.intento_id WHERE ci.comunicacion_id IN ($marks) ORDER BY ce.id");$stmt->execute($ids);foreach($stmt->fetchAll() as $ev)$eventos[$ev['intento_id']][]=$ev;}}
$fases=['preparando'=>'Preparando mensaje','conectando'=>'Conectando al servidor','autenticando'=>'Autenticando','destinatario'=>'Validando destinatario','transmitiendo'=>'Transmitiendo mensaje','esperando_aceptacion'=>'Esperando aceptación final','aceptado'=>'Aceptado por SMTP','capturado_local'=>'Capturado en entorno local','interrumpido'=>'Proceso interrumpido','fallido'=>'Envío fallido','revision'=>'Requiere revisión'];
$pageTitle='Historial de correos';$pageSection='correos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page correo-page">
<header class="admin-page__header"><div><h1>Historial de correos</h1><p>Confirmaciones, recordatorios y certificados, con el resultado de cada intento.</p></div><a class="activity-action-secondary" href="/admin/correos/">Requieren atención</a></header>
<?php if(!$traceReady):?><div class="admin-alert admin-alert--error">Falta activar la migración de trazabilidad. Los intentos anteriores se pueden consultar, pero aún no se registran sus etapas.</div><?php endif;?>
<form class="admin-card correo-filtros" method="get"><label>Buscar persona o actividad<input class="admin-input" name="q" autocorrect="off" autocapitalize="none" spellcheck="false" autocomplete="off" value="<?= AdminForm::escape($q) ?>" placeholder="Nombre, DNI, correo o actividad"></label><label>Correo<select class="admin-select" name="tipo"><?php foreach(['confirmacion_inscripcion'=>'Confirmación de inscripción','recordatorio_clase'=>'Recordatorio','certificado'=>'Certificado de participante','certificado_docente'=>'Certificado de docente'] as $k=>$v):?><option value="<?= $k ?>" <?= $tipo===$k?'selected':'' ?>><?= $v ?></option><?php endforeach;?></select></label><label>Resultado<select class="admin-select" name="estado"><?php foreach([''=>'Todos','enviado'=>'Aceptado por el servidor','error'=>'Fallido','pendiente'=>'Pendiente / requiere revisión','sin_registro'=>'Sin registro'] as $k=>$v):?><option value="<?= $k ?>" <?= $estado===$k?'selected':'' ?>><?= $v ?></option><?php endforeach;?></select></label><?php if($docente):?><input type="hidden" name="docente" value="<?= $docente ?>"><?php endif;?><?php if($inscripcion):?><input type="hidden" name="inscripcion" value="<?= $inscripcion ?>"><?php endif;?><?php if($edicion):?><input type="hidden" name="edicion" value="<?= $edicion ?>"><?php endif;?><button class="admin-button" type="submit">Buscar</button></form>
<p><?= (int)($totals['enviado']??0) ?> aceptados · <?= (int)($totals['error']??0) ?> fallidos · <?= (int)($totals['pendiente']??0) ?> pendientes o en revisión · <?= (int)($totals['sin_registro']??0) ?> sin registro</p>
<p class="admin-text-muted">«Aceptado» acredita que el servidor recibió el mensaje para enviarlo, no su llegada a la bandeja de entrada. Los registros históricos ausentes no se consideran éxitos ni fallos. En recordatorios se muestran los envíos registrados; los faltantes del día se detectan en «Requieren atención».</p>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Persona / Actividad</th><th>Resultado</th><th>Detalle</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><strong><?= AdminForm::escape($r['nombre'].' '.$r['apellido']) ?></strong><small><?= AdminForm::escape($r['titulo']) ?></small><small><?= $esDocente?'Docente':'Inscripción' ?> #<?= (int)$r['inscripcion_id'] ?> · <?= AdminForm::escape($r['inscripto_en']) ?></small></td><td data-label="Resultado"><span class="admin-status <?= $r['estado']==='error'?'correo-status--error':'' ?>"><?= !$r['id']?'Sin registro':($r['estado']==='enviado'?'Aceptado por el servidor':($r['incierto']?'Requiere revisión':($r['estado']==='error'?'Falló el envío':'Pendiente'))) ?></span><small><?= AdminForm::escape($r['destinatario_email']??'') ?></small><small><?= AdminForm::escape($r['enviado_en']??$r['ultimo_intento_en']??'') ?></small></td><td data-label="Detalle">
<?php if(isset($descartes[$r['id']])):?><small>Aviso descartado el <?= AdminForm::escape(date('d/m/Y H:i',strtotime($descartes[$r['id']]))) ?>. Se conserva el resultado del envío.</small><?php endif;?>
<?php if($r['id']):?><details><summary><?= (int)$r['intentos'] ?> intento(s) · Ver detalle</summary><?php if($r['error_detalle']):?><p><?= AdminForm::escape($r['error_detalle']) ?></p><?php endif;?>
<?php foreach($intentos[$r['id']]??[] as $t):?><div class="correo-intento"><strong>#<?= (int)$t['id'] ?> · <?= AdminForm::escape($t['iniciado_en']) ?></strong><small><?= AdminForm::escape(match($t['origen']){'manual'=>'Reintento administrativo','admin'=>'Inscripción administrativa','cron'=>'Tarea de recordatorios','recuperacion'=>'Recuperación autorizada',default=>'Inscripción automática'}) ?> · <?= AdminForm::escape($t['resultado']==='incierto'?'Sin cierre confirmado':$t['resultado']) ?></small>
<?php if(empty($eventos[$t['id']])):?><p>No hay etapas registradas para este intento anterior. Si quedó sin cierre, revisar el registro del servidor antes de reenviar.</p><?php else:?><ol><?php foreach($eventos[$t['id']] as $ev):?><li><?= AdminForm::escape($ev['creado_en'].' · '.($fases[$ev['fase']]??$ev['fase'])) ?><?= $ev['smtp_codigo']?' · SMTP '.(int)$ev['smtp_codigo']:'' ?><?php if($ev['detalle']):?><small><?= AdminForm::escape($ev['detalle']) ?></small><?php endif;?></li><?php endforeach;?></ol><small>ID del mensaje: <?= AdminForm::escape($eventos[$t['id']][0]['message_id']) ?></small><?php endif;?></div><?php endforeach;?></details><?php else:?>No hay constancia de envío.<?php endif;?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="3">No hay resultados para estos filtros.</td></tr><?php endif;?></tbody></table></div>
<nav class="correo-pagination" aria-label="Páginas del historial"><?php foreach(['Anterior'=>$page-1,'Siguiente'=>$page+1] as $label=>$n):if($n<1 || $n>$pages)continue;?><a class="activity-action-secondary" href="?<?= AdminForm::escape(http_build_query(['q'=>$q,'tipo'=>$tipo,'estado'=>$estado,'edicion'=>$edicion,'inscripcion'=>$inscripcion,'docente'=>$docente,'pagina'=>$n])) ?>"><?= $label ?></a><?php endforeach;?><span>Página <?= $page ?> de <?= $pages ?> · <?= $total ?> registros</span></nav>
</section><?php require __DIR__.'/../includes/footer.php';?>
