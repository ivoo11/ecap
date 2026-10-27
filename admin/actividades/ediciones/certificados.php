<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../../app/services/AdminForm.php';
require_once __DIR__.'/../../../app/services/CertificadoDocenteService.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
$docentes=($_GET['destinatarios']??'')==='docentes';
$destinatarios=$docentes?'docentes':'participantes';
$service=$docentes?new CertificadoDocenteService($pdo):new CertificadoService($pdo);
try{$edicion=$service->edicion($id);$config=$service->config($id);}catch(DomainException $e){http_response_code(404);exit(AdminForm::escape($e->getMessage()));}
$error=null;$confirmar=null;$rows=$service->participantes($id);
header('Cache-Control: no-store');
if(isset($_GET['pdf'])){
    try{
        $ins=(int)($_GET['persona']??0);$person=null;foreach($rows as $p)if((int)$p['id']===$ins)$person=$p;
        if($_GET['pdf']==='emitido'){
            if(!$person || $person['certificado_estado']!=='emitido' || !$person['archivo'])throw new DomainException('Certificado no disponible.');
            $pdf=file_get_contents(CertificadoPdf::file($person['archivo']));
        }else{
            $preview=$config;if(!$preview['encabezado'])$preview['encabezado']='CERTIFICADO DE CURSADA';
            if($docentes)$preview['leyenda']=CertificadoDocenteService::leyenda((string)($_GET['rol']??$person['rol']??'expositor/a'));
            $pdf=CertificadoPdf::render($preview,$person?trim($person['nombre'].' '.$person['apellido']):'Usuario de Prueba',true);
        }
        header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="certificado-ecap.pdf"');header('X-Content-Type-Options: nosniff');echo $pdf;exit;
    }catch(Throwable $e){http_response_code(422);exit($e instanceof DomainException?AdminForm::escape($e->getMessage()):'No se pudo preparar el PDF.');}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    AdminForm::csrf();$accion=AdminForm::text('accion');
    if($accion==='procesar'){
        header('Content-Type: application/json; charset=utf-8');$token=AdminForm::text('lote');$lote=$_SESSION['certificado_lotes'][$token]??null;$ins=(int)AdminForm::text('persona');
        try{
            if(!$lote || ($lote['destinatarios']??'participantes')!==$destinatarios || $lote['edicion']!==$id || $lote['vence']<time() || !isset($lote['personas'][$ins]))throw new DomainException('La selección venció. Revisá los participantes nuevamente.');
            if(isset($lote['resultados'][$ins])){echo json_encode($lote['resultados'][$ins]);exit;}
            $sent=$docentes?$service->enviar($id,$ins,(int)Auth::id(),$lote['personas'][$ins],$lote['revision'],false,$lote['roles'][$ins]):$service->enviar($id,$ins,(int)Auth::id(),$lote['personas'][$ins],$lote['revision']);
            $result=['ok'=>true,'mensaje'=>$sent?'Correo enviado':'Ya estaba enviado'];
        }catch(Throwable $e){error_log('ECAP certificados: '.$e->getMessage());$result=['ok'=>false,'mensaje'=>$e instanceof DomainException?$e->getMessage():'No se pudo completar. Revisá el estado antes de reintentar.'];}
        if($lote && ($lote['destinatarios']??'participantes')===$destinatarios && $lote['edicion']===$id && isset($lote['personas'][$ins]))$_SESSION['certificado_lotes'][$token]['resultados'][$ins]=$result;
        echo json_encode($result);exit;
    }
    try{
        if($accion==='guardar'){
            $newFile=null;
            if(($_FILES['fondo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$newFile=CertificadoPdf::upload($_FILES['fondo']);
            $d=['encabezado'=>AdminForm::text('encabezado'),'titulo'=>AdminForm::text('titulo'),'fondo'=>$newFile?:$config['fondo'],'aprobado'=>isset($_POST['aprobado'])?1:0];
            try{$service->guardar($id,$d,(int)Auth::id(),(int)AdminForm::text('revision'));}
            catch(Throwable $e){if($newFile)@unlink(CertificadoPdf::file($newFile));throw $e;}
            header('Location: certificados.php?id='.$id.($docentes?'&destinatarios=docentes':'').'&guardado=1');exit;
        }
        if($accion==='seleccionar'){
            if(!$config['aprobado'])throw new DomainException('Primero revisá y habilitá la plantilla definitiva.');
            $selected=$_POST['personas']??[];if(!is_array($selected))throw new DomainException('Selección inválida.');
            $selected=array_unique(array_map('intval',$selected));$confirmar=[];
            foreach($rows as $p)if(in_array((int)$p['id'],$selected,true) && $service::elegible($p))$confirmar[]=$p;
            if(!$confirmar || count($confirmar)!==count($selected))throw new DomainException('Elegí participantes disponibles. La lista puede haber cambiado.');
            if(count($confirmar)>500)throw new DomainException('Seleccioná hasta 500 personas por envío.');
            foreach(($_SESSION['certificado_lotes']??[]) as $key=>$old)if($old['vence']<time())unset($_SESSION['certificado_lotes'][$key]);
            if(count($_SESSION['certificado_lotes']??[])>=5)array_shift($_SESSION['certificado_lotes']);
            $roles=[];if($docentes)foreach($confirmar as $p)$roles[(int)$p['id']]=CertificadoDocenteService::rol((string)($p['rol']??$_POST['roles'][$p['id']]??'expositor/a'));
            $token=bin2hex(random_bytes(24));$people=[];foreach($confirmar as $p)$people[(int)$p['id']]=CertificadoService::huella($p);
            $_SESSION['certificado_lotes'][$token]=['destinatarios'=>$destinatarios,'roles'=>$roles,'edicion'=>$id,'revision'=>(int)$config['revision'],'personas'=>$people,'vence'=>time()+7200,'resultados'=>[]];
        }
    }catch(Throwable $e){$confirmar=null;$error=$e instanceof DomainException?$e->getMessage():'No se pudo guardar. Revisá los datos e intentá nuevamente.';error_log('ECAP certificados: '.$e->getMessage());}
}
$finished=!in_array($edicion['estado'],['borrador','cancelada'],true) && strtotime($edicion['fecha_fin']??$edicion['fecha_inicio'])<=time();
$pageTitle='Certificados';$pageSection='actividades';require __DIR__.'/../../includes/header.php';
?>
<section class="admin-page certificado-page <?= $docentes?'certificado-page--docentes':'' ?>">
<header class="admin-page__header"><div><h1>Certificados</h1><p><?= AdminForm::escape($edicion['titulo']) ?> · <?= date('d/m/Y',strtotime($edicion['fecha_inicio'])) ?></p></div><a class="activity-action-secondary" href="editar.php?id=<?= $id ?>">Volver a la edición</a></header>
<nav class="certificado-tabs" aria-label="Destinatarios de certificados"><a href="certificados.php?id=<?= $id ?>" <?= !$docentes?'aria-current="page"':'' ?>>Participantes</a><a href="certificados.php?id=<?= $id ?>&amp;destinatarios=docentes" <?= $docentes?'aria-current="page"':'' ?>>Docentes</a></nav>
<?php if($error):?><div class="admin-alert admin-alert--error" role="alert"><?= AdminForm::escape($error) ?></div><?php endif;?>
<?php if(isset($_GET['guardado'])):?><div class="edition-message edition-message--success" role="status">Plantilla guardada. Revisá la vista previa antes de emitir.</div><?php endif;?>
<?php if(!$finished):?><div class="admin-card"><p>Podés preparar la plantilla ahora. La emisión estará disponible cuando termine la edición.</p></div><?php endif;?>
<?php if($confirmar):?>
<section class="admin-card"><h2>Revisar <?= count($confirmar) ?> destinatarios</h2><p><?= $docentes?'Confirmás la participación de los docentes seleccionados en el carácter indicado.':'Confirmás que estas personas cumplen el criterio de asistencia.' ?> Se emitirá un certificado por persona y se enviará como PDF adjunto.</p><p>Los certificados ya emitidos conservarán su contenido original. Mantené esta página abierta mientras se procesan los envíos.</p>
<div class="certificado-actions"><a class="activity-action-secondary" target="_blank" rel="noopener" href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>&pdf=previa&persona=<?= (int)$confirmar[0]['id'] ?><?= $docentes?'&amp;rol='.urlencode($roles[$confirmar[0]['id']]):'' ?>">Ver certificado de muestra</a><button type="button" class="admin-button" id="certificado-enviar">Emitir y enviar (<?= count($confirmar) ?>)</button><a class="activity-action-secondary" href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>">Volver a la selección</a></div>
<p id="certificado-progreso" role="status" aria-live="polite"></p>
<table class="admin-table"><thead><tr><th>Persona</th><th>Resultado</th></tr></thead><tbody><?php foreach($confirmar as $p):?><tr data-envio="<?= (int)$p['id'] ?>"><td><?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?><small><?= AdminForm::escape($p['email']) ?></small><?php if($docentes):?><small><?= AdminForm::escape(CertificadoDocenteService::ROLES[$roles[$p['id']]]) ?></small><?php endif;?></td><td data-resultado>Por enviar</td></tr><?php endforeach;?></tbody></table>
<form id="certificado-lote" hidden><?= Csrf::field() ?><input name="lote" value="<?= $token ?>"></form></section>
<script>
document.getElementById('certificado-enviar').addEventListener('click',async function(){
 this.disabled=true;const rows=[...document.querySelectorAll('[data-envio]')],status=document.getElementById('certificado-progreso');let done=0,failed=0;
 for(const row of rows){const data=new FormData(document.getElementById('certificado-lote'));data.set('accion','procesar');data.set('persona',row.dataset.envio);row.querySelector('[data-resultado]').textContent='Procesando…';
 try{const response=await fetch(location.href,{method:'POST',body:data});if(!response.ok)throw new Error();const result=await response.json();row.querySelector('[data-resultado]').textContent=result.mensaje;if(!result.ok)failed++;}
 catch(e){row.querySelector('[data-resultado]').textContent='Resultado sin confirmar. Revisá la lista antes de reintentar.';status.textContent='Se interrumpió el proceso. Los restantes no se enviaron; volvé a la selección para revisar los estados.';return;}
 done++;status.textContent=`${done} de ${rows.length} procesados · ${failed} requieren revisión.`;
 }
 this.textContent='Proceso terminado';
});
</script>
<?php else:?>
<details class="admin-card" <?= !$config['aprobado']?'open':'' ?>><summary><strong>Plantilla del certificado</strong> · <?= $config['aprobado']?'Lista para emitir':'En preparación' ?></summary>
<form method="post" enctype="multipart/form-data" class="certificado-config">
<?= Csrf::field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="revision" value="<?= (int)$config['revision'] ?>">
<label>Encabezado<input name="encabezado" maxlength="120" required placeholder="Ej.: Certificado de participación" value="<?= AdminForm::escape($config['encabezado']) ?>"></label>
<label>Título que aparece en el certificado<textarea name="titulo" maxlength="500" rows="3" required><?= AdminForm::escape($config['titulo']) ?></textarea><small>Solo el título de la actividad. <?= $docentes?'La leyenda indicará la participación en calidad de expositor o expositora.':'La leyenda será «Por su participación en».' ?></small></label>
<label>Fondo con logo y firmas<input type="file" name="fondo" accept="application/pdf,image/png"><small>A4 horizontal, PDF o PNG de hasta 10 MB. Sin encabezado, nombre ni título de actividad. <?= $config['fondo']===CertificadoPdf::DEFAULT_BACKGROUND?'Se usa la plantilla oficial de ECAP con ambas firmas. Elegí un archivo solo si querés otro diseño.':'Ya hay un fondo cargado. Elegí otro solo para reemplazarlo.' ?></small></label>
<label class="certificado-check"><input type="checkbox" name="aprobado" value="1" <?= $config['aprobado']?'checked':'' ?>>Revisé la plantilla y las firmas; está lista para emitir.</label>
<div class="certificado-actions"><button class="admin-button" type="submit">Guardar plantilla</button><a class="activity-action-secondary" target="_blank" rel="noopener" href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>&pdf=previa">Ver vista previa guardada</a></div>
</form></details>
<section class="admin-card"><div class="certificado-toolbar"><div><h2><?= $docentes?'Docentes':'Participantes' ?></h2><p><?= $docentes?'Docentes asignados a esta edición. Revisá el carácter de su participación y el correo antes de enviar.':'Seleccioná a quienes cumplen la asistencia. No se envía a todos automáticamente.' ?></p></div><label>Buscar <?= $docentes?'docente':'participante' ?><input type="search" autocorrect="off" autocapitalize="none" spellcheck="false" autocomplete="off" id="certificado-buscar" placeholder="Nombre o correo"></label></div>
<form method="post" id="certificado-seleccion"><?= Csrf::field() ?><input type="hidden" name="accion" value="seleccionar">
<div class="certificado-actions"><label class="certificado-check"><input type="checkbox" id="certificado-todos">Seleccionar visibles disponibles</label><span id="certificado-cantidad" role="status">0 seleccionados</span><button class="admin-button" id="certificado-revisar" disabled type="submit">Revisar y enviar</button></div>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Seleccionar</th><th><?= $docentes?'Docente':'Participante' ?></th><?php if($docentes):?><th>Participación</th><?php endif;?><th>Certificado</th><th>Correo</th><th>Vista previa</th></tr></thead><tbody>
<?php foreach($rows as $p):$available=$finished && $config['aprobado'] && $service::elegible($p); ?>
<tr data-persona="<?= AdminForm::escape(mb_strtolower($p['nombre'].' '.$p['apellido'].' '.$p['email'])) ?>"><td><input type="checkbox" name="personas[]" value="<?= (int)$p['id'] ?>" aria-label="Seleccionar a <?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?>" <?= !$available?'disabled':'' ?>></td><td data-label="<?= $docentes?'Docente':'Participante' ?>"><strong><?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?></strong><small><?= AdminForm::escape($p['email']) ?></small><?php if(!$docentes && $p['inscripcion_estado']!=='confirmada'):?><small>Inscripción <?= AdminForm::escape($p['inscripcion_estado']) ?></small><?php endif;?><?php if($docentes && !$p['asignado']):?><small>Ya no está asignado a esta edición.</small><?php endif;?><?php if($docentes && !filter_var($p['email'],FILTER_VALIDATE_EMAIL)):?><small>Completá un correo válido en su ficha antes de enviar.</small><?php endif;?></td>
<?php if($docentes): $rolActual=$p['rol']??(isset(CertificadoDocenteService::ROLES[mb_strtolower($p['rol_asignado']??'')])?mb_strtolower($p['rol_asignado']):'expositor/a'); ?>
<td data-label="Participación"><?php if($p['certificado_id']):?><?= AdminForm::escape(CertificadoDocenteService::ROLES[$rolActual]) ?><?php else:?><select class="admin-select certificado-rol" name="roles[<?= (int)$p['id'] ?>]" aria-label="Participación de <?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?>"><?php foreach(CertificadoDocenteService::ROLES as $key=>$label):?><option value="<?= $key ?>" <?= $key===$rolActual?'selected':'' ?>><?= $label ?></option><?php endforeach;?></select><?php endif;?></td><?php endif;?><td data-label="Certificado"><span class="admin-status"><?= match($p['certificado_estado']){'emitido'=>'Emitido','anulado'=>'Anulado',default=>'Sin emitir'} ?></span></td><td data-label="Correo"><?= $p['incierto']?'Envío sin confirmar · revisar Control de correos':match($p['correo_estado']){'enviado'=>'Correo enviado','error'=>'Falló · disponible para reintentar','pendiente'=>'Pendiente',default=>'Sin envío'} ?></td><td data-label="Documento"><a class="activity-action-secondary" target="_blank" rel="noopener" data-certificado-preview href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>&persona=<?= (int)$p['id'] ?>&pdf=<?= $p['certificado_estado']==='emitido'?'emitido':'previa' ?><?= $docentes?'&amp;rol='.urlencode($rolActual):'' ?>">Ver PDF</a></td></tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="<?= $docentes?6:5 ?>"><?= $docentes?'Todavía no hay docentes asignados a esta edición. Asignalos desde la gestión de la edición.':'Todavía no hay inscripciones en esta edición.' ?></td></tr><?php endif;?></tbody></table></div>
</form><p class="admin-text-muted">«Correo enviado» significa que el servidor de correo aceptó el mensaje para su entrega; no confirma que llegó a la bandeja de entrada. Los fallos también aparecen en Control de correos.</p></section>
<script>
(()=>{const form=document.getElementById('certificado-seleccion'),all=document.getElementById('certificado-todos'),rows=[...document.querySelectorAll('[data-persona]')];
const update=()=>{const n=form.querySelectorAll('input[name="personas[]"]:checked').length;document.getElementById('certificado-cantidad').textContent=n+' seleccionados';document.getElementById('certificado-revisar').disabled=n===0;const visible=rows.filter(r=>!r.hidden).map(r=>r.querySelector('input')).filter(c=>!c.disabled);all.checked=visible.length>0&&visible.every(c=>c.checked);all.indeterminate=visible.some(c=>c.checked)&&!all.checked;};
form.addEventListener('change',e=>{update();if(e.target.matches('.certificado-rol')){const a=e.target.closest('tr').querySelector('[data-certificado-preview]');const url=new URL(a.href);url.searchParams.set('rol',e.target.value);a.href=url.href;}});all.addEventListener('change',()=>{rows.filter(r=>!r.hidden).forEach(r=>{const c=r.querySelector('input');if(!c.disabled)c.checked=all.checked;});update();});
document.getElementById('certificado-buscar').addEventListener('input',e=>{rows.forEach(r=>r.hidden=!r.dataset.persona.includes(e.target.value.toLocaleLowerCase().trim()));update();});})();
</script>
<?php endif;?>
</section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
