<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../../app/services/AdminForm.php';
require_once __DIR__.'/../../../app/services/CertificadoDocenteService.php';
require_once __DIR__.'/../../../app/services/CertificadoCola.php';
$cola=CertificadoCola::production();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
$docentes=($_GET['destinatarios']??'')==='docentes';
$destinatarios=$docentes?'docentes':'participantes';
$service=$docentes?new CertificadoDocenteService($pdo):new CertificadoService($pdo);
try{$edicion=$service->edicion($id);$config=$service->config($id);}catch(DomainException $e){http_response_code(404);exit(AdminForm::escape($e->getMessage()));}
$error=null;$confirmar=null;$rows=$service->participantes($id);
header('Cache-Control: no-store');
if(isset($_GET['estado_lotes'])){
    session_write_close();header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_map([CertificadoCola::class,'resumen'],$cola->listado($id,$destinatarios)));exit;
}
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
    if($accion==='iniciar_lote'){
        header('Content-Type: application/json; charset=utf-8');
        try {
            if(!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request'))throw new DomainException('El servidor no permite iniciar el envío en segundo plano. Contactá al soporte de ECAP.');
            $token=AdminForm::text('lote');$selection=$_SESSION['certificado_lotes'][$token]??null;
            $existing=$cola->leer($token);
            if(!$existing && (!$selection || $selection['edicion']!==$id || $selection['destinatarios']!==$destinatarios))throw new DomainException('La selección venció. Volvé a revisarla.');
            if($existing && ($existing['edicion']!==$id || $existing['destinatarios']!==$destinatarios))throw new DomainException('Tanda inválida.');
            $job=$existing?:$cola->iniciar($token,$selection,(int)Auth::id());
            session_write_close();ignore_user_abort(true);set_time_limit(0);
            echo json_encode(['ok'=>true,'lote'=>CertificadoCola::resumen($job)]);
            if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();else litespeed_finish_request();
            try{$cola->ejecutar($pdo);}catch(Throwable $e){error_log('ECAP certificados cola: '.$e->getMessage());}
            exit;
        }catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e instanceof DomainException?$e->getMessage():'No se pudo iniciar la tanda. Volvé a intentar.']);exit;}
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
<section class="admin-card" id="certificado-tandas"><h2>Progreso de envíos</h2>
<p>Las tandas confirmadas se guardan y siguen en segundo plano, aunque pases a docentes o cierres esta pantalla. Si el servidor interrumpe el proceso, los pendientes se recuperan en la siguiente ejecución de respaldo.</p>
<div id="certificado-tandas-lista"></div><p id="certificado-tandas-estado" role="status" aria-live="polite"></p>
</section>
<?php if($confirmar):?>
<section class="admin-card"><h2>Revisar <?= count($confirmar) ?> destinatarios</h2><p><?= $docentes?'Confirmás la participación de los docentes seleccionados en el carácter indicado.':'Confirmás que estas personas cumplen el criterio de asistencia.' ?> Se emitirá un certificado por persona y se enviará como PDF adjunto.</p><p>Los certificados ya emitidos conservarán su contenido original. Al confirmar, la tanda queda guardada y continúa en segundo plano. Podés cambiar de pantalla o cerrar el navegador.</p>
<div class="certificado-actions"><a class="activity-action-secondary" target="_blank" rel="noopener" href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>&pdf=previa&persona=<?= (int)$confirmar[0]['id'] ?><?= $docentes?'&amp;rol='.urlencode($roles[$confirmar[0]['id']]):'' ?>">Ver certificado de muestra</a><button type="button" class="admin-button" id="certificado-enviar">Emitir y enviar (<?= count($confirmar) ?>)</button><a class="activity-action-secondary" href="certificados.php?id=<?= $id ?><?= $docentes?'&amp;destinatarios=docentes':'' ?>">Volver a la selección</a></div>
<p id="certificado-progreso" role="status" aria-live="polite"></p>
<table class="admin-table"><thead><tr><th>Persona</th><th>Resultado</th></tr></thead><tbody><?php foreach($confirmar as $p):?><tr data-envio="<?= (int)$p['id'] ?>"><td><?= AdminForm::escape($p['nombre'].' '.$p['apellido']) ?><small><?= AdminForm::escape($p['email']) ?></small><?php if($docentes):?><small><?= AdminForm::escape(CertificadoDocenteService::ROLES[$roles[$p['id']]]) ?></small><?php endif;?></td><td data-resultado>Por enviar</td></tr><?php endforeach;?></tbody></table>
<form id="certificado-lote" hidden><?= Csrf::field() ?><input name="lote" value="<?= $token ?>"></form></section>
<script>
document.getElementById('certificado-enviar').addEventListener('click',async function(){
 this.disabled=true;const status=document.getElementById('certificado-progreso');status.textContent='Guardando la tanda…';
 const data=new FormData(document.getElementById('certificado-lote'));data.set('accion','iniciar_lote');
 try{const response=await fetch(location.href,{method:'POST',body:data});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.mensaje||'No se pudo iniciar.');
 this.textContent='Envío iniciado';status.textContent='La tanda quedó guardada. Podés salir de esta pantalla; el envío continúa en segundo plano.';window.actualizarCertificados();}
 catch(e){status.textContent='No pudimos confirmar el inicio. Consultá el progreso de abajo o volvé a tocar el botón: no se duplicará la tanda.';this.disabled=false;}
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

<script>
(()=>{
 const nombres=<?= json_encode(array_column(array_map(fn($p)=>['id'=>$p['id'],'nombre'=>trim($p['nombre'].' '.$p['apellido'])],$rows),'nombre','id'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 const list=document.getElementById('certificado-tandas-lista'),notice=document.getElementById('certificado-tandas-estado');let busy=false,timer;
 async function update(){
  if(busy)return;busy=true;clearTimeout(timer);
  try{
   const url=new URL(location.href);url.searchParams.set('estado_lotes','1');
   const response=await fetch(url,{cache:'no-store'});if(!response.ok||response.redirected)throw new Error();const jobs=await response.json();
   for(const job of jobs){
    let box=document.getElementById('tanda-'+job.token);
    if(!box){box=document.createElement('div');box.id='tanda-'+job.token;const title=document.createElement('p');title.dataset.resumen='';box.append(title);const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Ver destinatarios';details.append(summary);const ul=document.createElement('ul');details.append(ul);box.append(details);list.append(box);}
    const c=job.conteos,remaining=c.pendiente+c.procesando;
    box.querySelector('[data-resumen]').textContent=new Date(job.creado*1000).toLocaleString('es-AR')+' · '+c.enviado+' de '+job.total+' enviados · '+remaining+' pendientes · '+c.revision+' para revisar'+(remaining===0?' · Tanda finalizada':'');
    for(const [id,item] of Object.entries(job.items)){
     let line=box.querySelector('[data-item="'+id+'"]');if(!line){line=document.createElement('li');line.dataset.item=id;box.querySelector('ul').append(line);}line.textContent=(nombres[id]||'Destinatario '+id)+' · '+item.mensaje;
     const selected=document.querySelector('[data-envio="'+id+'"] [data-resultado]');
     if(selected && document.querySelector('#certificado-lote input[name="lote"]')?.value===job.token)selected.textContent=item.mensaje;
    }
   }
   notice.textContent=jobs.length?'Estado actualizado. Los casos para revisar se consultan en la lista de participantes o en Control de correos.':'Todavía no hay tandas en segundo plano para esta sección.';
  }catch(e){notice.textContent='No pudimos actualizar el estado. Esto no detiene el envío; volveremos a consultar.';}
  finally{busy=false;timer=setTimeout(()=>{if(!document.hidden)update();},5000);}
 }
 window.actualizarCertificados=update;document.addEventListener('visibilitychange',()=>{if(!document.hidden)update();});update();
})();
</script>
</section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
