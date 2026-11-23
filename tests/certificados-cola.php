<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/services/CertificadoCola.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "OK $message\n";}
$dir=sys_get_temp_dir().'/ecap-cola-test-'.bin2hex(random_bytes(8));$q=new CertificadoCola($dir);
$token=str_repeat('a',48);$selection=['edicion'=>4,'destinatarios'=>'participantes','personas'=>[11=>'hash1',12=>'hash2',13=>'hash3'],'roles'=>[],'revision'=>1,'vence'=>time()+100];
try{
 $q->iniciar($token,$selection,1);$q->iniciar($token,$selection,1);
 check(count($q->listado(4,'participantes'))===1,'Doble confirmación guarda una sola tanda');
 check(count($q->listado(4,'docentes'))===0,'Separa alumnos y docentes');
 $calls=[];
 $q->procesar(function($job,$id)use(&$calls){$calls[]=$id;if($id===12)throw new DomainException('Correo inválido');return true;},fn()=>false);
 $s=CertificadoCola::resumen($q->leer($token));
 check($s['conteos']['enviado']===2 && $s['conteos']['revision']===1,'Continúa después de un fallo y guarda cada resultado');
 $q->procesar(function()use(&$calls){$calls[]=99;return true;},fn()=>false);
 check($calls===[11,12,13],'No reenvía resultados ni fallos automáticamente');
 $original=$q->leer($token);
 $current=CertificadoCola::resumenActual($original,[12=>true]);
 check($current['conteos']['enviado']===3 && $current['conteos']['revision']===0,'El reenvío exitoso resuelve la incidencia en el progreso');
 check($q->leer($token)===$original,'Consultar progreso conserva el historial original');
 check(CertificadoCola::resumenActual($original,[])['conteos']['revision']===1,'Sin aceptación confirmada sigue para revisar');
 check(CertificadoCola::resumenActual($original,[999=>true])['conteos']['revision']===1,'Un envío de otra persona no resuelve la incidencia');
 // Simular la terminación abrupta del worker después de marcar un destinatario.
 $token2=str_repeat('b',48);$q->iniciar($token2,$selection,1);
 if(function_exists('pcntl_fork')){
  $pid=pcntl_fork();if($pid===0){$q->procesar(function(){exit(0);},fn()=>false);exit(0);}pcntl_waitpid($pid,$status);
  check($q->leer($token2)['items'][11]['estado']==='procesando','Sobrevive a la caída de un proceso');
  $recovered=[];$q->procesar(function($job,$id)use(&$recovered){$recovered[]=$id;return true;},fn()=>true);
  check($recovered===[12,13],'Recupera pendientes sin repetir el aceptado antes de la caída');
  check(CertificadoCola::resumen($q->leer($token2))['conteos']['enviado']===3,'Concilia aceptación registrada');
 }
 if(function_exists('pcntl_fork')){
  $token4=str_repeat('e',48);$q->iniciar($token4,$selection,1);
  $pid=pcntl_fork();if($pid===0){$q->procesar(function(){exit(0);},fn()=>false);exit(0);}pcntl_waitpid($pid,$status);
  $after=[];$q->procesar(function($job,$id)use(&$after){$after[]=$id;return true;},fn()=>false);
  check($after===[12,13] && $q->leer($token4)['items'][11]['estado']==='revision','No repite un intento sin resultado conocido; sí continúa los restantes');
 }
 $token3=str_repeat('c',48);$q->iniciar($token3,$selection,1);
 $h=fopen($dir.'/'.$token3.'.json.lock','c');flock($h,LOCK_EX);$lockedCalls=0;
 $q->procesar(function()use(&$lockedCalls){$lockedCalls++;return true;},fn()=>false);
 check($lockedCalls===0,'Un segundo worker no procesa una tanda bloqueada');flock($h,LOCK_UN);fclose($h);
 $q->procesar(fn()=>true,fn()=>false,0);
 check($q->leer($token3)['items'][11]['estado']==='pendiente','El límite de ejecución conserva pendientes');
 check(!$q->estado($q->leer($token3))['activa'],'La tanda libre permite continuar');
 $h=fopen($dir.'/'.$token3.'.json.lock','c');flock($h,LOCK_EX);
 check($q->estado($q->leer($token3))['activa'],'Detecta una ejecución activa sin interrumpirla');
 flock($h,LOCK_UN);fclose($h);
 $scoped=str_repeat('f',48);$q->iniciar($scoped,$selection,1);$scopeCalls=[];
 $q->procesar(function($job,$id)use(&$scopeCalls){$scopeCalls[]=$job['token'];return true;},fn()=>false,3600,$scoped);
 check($scopeCalls===[$scoped,$scoped,$scoped] && $q->leer($token3)['items'][11]['estado']==='pendiente','Continuar procesa únicamente la tanda elegida');
 $q->procesar(function(){throw new RuntimeException('No debe reenviar');},fn()=>false,3600,$token);
 check($q->leer($token)['items'][12]['estado']==='revision','Continuar preserva enviados y casos para revisar');
 try{$q->leer('../otro');throw new RuntimeException('Traversal permitido');}catch(DomainException $e){echo "OK Rechaza rutas inválidas\n";}
 $expired=$selection;$expired['vence']=time()-1;
 try{$q->iniciar(str_repeat('d',48),$expired,1);throw new RuntimeException('Aceptó selección vencida');}catch(DomainException $e){echo "OK Selección vencida rechazada\n";}
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
