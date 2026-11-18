<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/Novedades.php';
header('Cache-Control: no-store');
if(!Auth::hasRole('superadmin')){http_response_code(403);exit('Acceso no autorizado.');}
if(date('Y-m-d')!=='2026-11-18'){http_response_code(410);exit('La prueba temporal venció.');}
$id=(int)($_GET['edicion']??0);$svc=new Novedades($pdo);$e=$svc->edicion($id);$c=$svc->campana($id);
if(!$c){http_response_code(422);exit('Primero guardá la invitación en la vista previa.');}
$mensaje='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 $clave='prueba-mobile-20261118';
 $q=$pdo->prepare('INSERT IGNORE INTO novedades_pruebas(clave) VALUES(?)');$q->execute([$clave]);
 if(!$q->rowCount())$mensaje='La prueba ya se intentó. No se envió otra copia.';
 else{
  try{
   $mail=MailTransport::create();$mail->Timeout=15;$mail->addAddress('ivanmontano87@gmail.com');$mail->Subject='[PRUEBA MÓVIL] '.$c['asunto'];$mail->isHTML(true);
   $mail->Body=str_replace('https://ecap.com.ar/novedades/baja.php?token=__BAJA_TOKEN__','https://ecap.com.ar/',NovedadPlantilla::html($e,$c['introduccion']));
   $mail->Body=str_replace('Dejar de recibir novedades','Vista previa: la baja no está activa en esta prueba',$mail->Body);
   $mail->AltBody="Prueba autorizada de la invitación ECAP.\n".$c['introduccion']."\nhttps://ecap.com.ar/actividades/?slug=".rawurlencode($e['slug']);
   if(!$mail->send())throw new RuntimeException('Sin aceptación del transporte.');
   $pdo->prepare("UPDATE novedades_pruebas SET estado='enviado',finalizado_en=NOW() WHERE clave=?")->execute([$clave]);$mensaje='Prueba enviada a ivanmontano87@gmail.com. No se creó ni envió una campaña.';
  }catch(Throwable $x){$pdo->prepare("UPDATE novedades_pruebas SET estado='revision',finalizado_en=NOW() WHERE clave=?")->execute([$clave]);error_log('ECAP prueba novedades: '.$x->getMessage());$mensaje='No se pudo confirmar el resultado. No se repetirá automáticamente.';}
 }
}
$pageTitle='Prueba de invitación';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><div class="admin-card"><h1>Prueba de invitación</h1><p>Una única prueba a ivanmontano87@gmail.com. No envía a la base ni modifica consentimientos.</p><p><?= AdminForm::escape($mensaje) ?></p><form method="post"><?= Csrf::field() ?><button class="admin-button" type="submit">Enviar la prueba autorizada</button></form></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
