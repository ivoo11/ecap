<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/config/database.php';
require_once __DIR__.'/../app/services/Novedades.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
$token=is_string($_GET['token']??null)?$_GET['token']:'';$ok=false;$valido=false;
if(preg_match('/^[a-f0-9]{64}$/D',$token)){
 $q=$pdo->prepare('SELECT id FROM novedades_destinatarios WHERE token=?');$q->execute([$token]);$valido=(bool)$q->fetchColumn();
 // GET no cambia preferencias: evita bajas por escáneres automáticos de enlaces.
 if($valido&&$_SERVER['REQUEST_METHOD']==='POST')$ok=(new Novedades($pdo))->baja($token);
}
?>
<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Novedades · ECAP</title><style>body{margin:0;background:#f4f5f7;color:#101e31;font:16px/1.6 Arial}main{max-width:520px;margin:10vh auto;padding:32px;background:white}button{padding:14px 20px;border:0;border-radius:5px;color:white;background:#101e31;font:inherit}h1{font-size:26px}</style><main><p>ECAP</p><h1><?= $ok?'Dejaste de recibir novedades':($valido?'¿Querés dejar de recibir novedades?':'Enlace no válido') ?></h1><p><?= $ok?'Tu preferencia quedó guardada.':($valido?'Podés confirmar la baja a continuación.':'Abrí el enlace que aparece al pie de un correo de novedades.') ?></p><p>Seguirás recibiendo las confirmaciones, los recordatorios y los certificados de las actividades en las que te inscribas.</p><?php if($valido&&!$ok): ?><form method="post"><button type="submit">Dejar de recibir novedades</button></form><?php endif ?></main></html>
