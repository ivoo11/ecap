<?php
declare(strict_types=1);
date_default_timezone_set('America/Argentina/Buenos_Aires');
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../services/CorreoControl.php';
$o=getopt('',['run','dry-run','test','fecha:','email:']);
$run=isset($o['run']);$dry=isset($o['dry-run']);$test=isset($o['test']);
if((int)$run+(int)$dry+(int)$test!==1){fwrite(STDERR,"Indicá --run, --dry-run o --test.\n");exit(1);}
if($run && isset($o['fecha'])){fwrite(STDERR,"--run no admite --fecha.\n");exit(1);}
$fecha=$run?date('Y-m-d'):($o['fecha']??date('Y-m-d'));
$f=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
if(!$f || $f->format('Y-m-d')!==$fecha){fwrite(STDERR,"Fecha inválida.\n");exit(1);}
if($test && !filter_var($o['email']??'',FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Indicá --email válido.\n");exit(1);}
$limite=0;$ejecucion=null;
if($run){
 $hora=date('H:i');
 foreach([['07:00','07:09',1],['07:15','07:24',2],['07:30','07:39',3]] as [$a,$b,$n]) if($hora>=$a && $hora<=$b)$limite=$n;
 if(!$limite){fwrite(STDERR,"Fuera de las ventanas de las 07:00, 07:15 y 07:30 (Argentina).\n");exit(1);}
 if((int)$pdo->query("SELECT GET_LOCK('ecap_recordatorios_clase',0)")->fetchColumn()!==1){echo "Ya hay un proceso en ejecución.\n";exit;}
 $pdo->exec("INSERT INTO correo_ejecuciones(estado) VALUES('ejecutando')");$ejecucion=(int)$pdo->lastInsertId();
 register_shutdown_function(static function()use($pdo,$ejecucion):void{
  try{$pdo->prepare("UPDATE correo_ejecuciones SET estado='interrumpido',fin=NOW() WHERE id=? AND estado='ejecutando'")->execute([$ejecucion]);$pdo->query("SELECT RELEASE_LOCK('ecap_recordatorios_clase')");}catch(Throwable $e){error_log('ECAP: no se pudo cerrar registro del cron.');}
 });
}
$q=$pdo->prepare("SELECT c.*,e.modalidad,e.zoom_url,e.zoom_meeting_id,e.zoom_passcode,a.titulo AS actividad_titulo,(SELECT COUNT(*) FROM clases x WHERE x.edicion_id=e.id) AS total_clases FROM clases c JOIN ediciones e ON e.id=c.edicion_id JOIN actividades a ON a.id=e.actividad_id WHERE c.fecha_inicio>=? AND c.fecha_inicio<=? AND e.estado NOT IN ('borrador','cancelada') AND (?=0 OR e.estado<>'finalizada') ORDER BY c.fecha_inicio,c.id");
$q->execute([$fecha.' 00:00:00',$fecha.' 23:59:59',(int)$run]);$clases=$q->fetchAll();
$enviados=0;$errores=0;$destinatarios=0;$pendientes=0;
$control=new CorreoControl($pdo);
try {
 foreach($clases as $c){
  if($run && strtotime($c['fecha_inicio'])<=time())continue;
  $q=$pdo->prepare("SELECT i.id,p.nombre,p.apellido,p.email,ct.estado,ct.intentos FROM inscripciones i JOIN personas p ON p.id=i.persona_id LEFT JOIN comunicaciones_transaccionales ct ON ct.inscripcion_id=i.id AND ct.clase_id=? AND ct.tipo='recordatorio_clase' WHERE i.edicion_id=? AND i.estado='confirmada'");$q->execute([$c['id'],$c['edicion_id']]);
  foreach($q->fetchAll() as $d){
   $destinatarios++;
   if($d['estado']==='enviado' || (int)$d['intentos']>=($run?$limite:3))continue;
   $pendientes++;
   if($dry)continue;
   try{
    if($test){
     // Un ensayo nunca marca como enviado un recordatorio real.
     (new MailService())->enviarRecordatorioClase($o['email'],trim($d['nombre'].' '.$d['apellido']),$c['actividad_titulo'],$c['fecha_inicio'],$c['modalidad'],$c['zoom_url']??'',$c['zoom_meeting_id']??'',$c['zoom_passcode']??'',(int)$c['numero'],$c['titulo']??'',(int)$c['total_clases']);$enviados++;
    } elseif($control->enviar((int)$d['id'],(int)$c['id'],'cron',null,$limite))$enviados++;
   }catch(Throwable $e){$errores++;error_log('ECAP recordatorio inscripción #'.$d['id'].': '.$e->getMessage());}
  }
 }
 if($run)$pdo->prepare("UPDATE correo_ejecuciones SET estado='completado',fin=NOW(),destinatarios=?,enviados=?,errores=? WHERE id=?")->execute([$destinatarios,$enviados,$errores,$ejecucion]);
}catch(Throwable $e){error_log('ECAP cron interrumpido: '.$e->getMessage());exit(1);}
echo 'Modo: '.($dry?'DRY RUN':($test?'TEST (sin registrar entregas reales)':'RUN'))."\nClases: ".count($clases)."\nDestinatarios: $destinatarios\nPendientes: $pendientes\nEnviados: $enviados\nErrores: $errores\n";
if($errores)exit(1);
