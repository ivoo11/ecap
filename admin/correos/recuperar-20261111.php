<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/CorreoControl.php';
header('Cache-Control: no-store');
if(!Auth::hasRole('superadmin')){http_response_code(403);exit;}
if(date('Y-m-d')!=='2026-11-11'){http_response_code(410);exit('Recuperación cerrada.');}
$q=$pdo->prepare("SELECT c.id,c.edicion_id,c.fecha_inicio FROM clases c JOIN ediciones e ON e.id=c.edicion_id JOIN actividades a ON a.id=e.actividad_id WHERE c.fecha_inicio='2026-11-11 14:00:00' AND a.titulo=? AND e.estado NOT IN ('borrador','cancelada','finalizada')");
$q->execute(['Inteligencia Artificial y prueba digital en tiempos disruptivos de la Gestión Pública']);$clases=$q->fetchAll();
if(count($clases)!==1){http_response_code(409);exit('No se pudo identificar una única clase.');}
$c=$clases[0];
$sql="SELECT i.id,ct.estado FROM inscripciones i LEFT JOIN comunicaciones_transaccionales ct ON ct.id=(SELECT MAX(x.id) FROM comunicaciones_transaccionales x WHERE x.inscripcion_id=i.id AND x.clase_id=? AND x.tipo='recordatorio_clase') WHERE i.edicion_id=? AND i.estado='confirmada' ORDER BY i.id";
$q=$pdo->prepare($sql);$q->execute([$c['id'],$c['edicion_id']]);$rows=$q->fetchAll();
$mensaje='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 if(time()>=strtotime($c['fecha_inicio'])){http_response_code(409);exit('La clase ya comenzó.');}
 $control=new CorreoControl($pdo);$ok=0;$fallos=0;
 // Solo comunicaciones aún inexistentes: nunca repite éxitos ni intentos ambiguos.
 foreach(array_slice(array_values(array_filter($rows,fn($r)=>$r['estado']===null)),0,5) as $r){
  try{if($control->enviar((int)$r['id'],(int)$c['id'],'manual',(int)Auth::id()))$ok++;}catch(Throwable $e){$fallos++;error_log('Recuperación 20261111 #'.$r['id'].': '.$e->getMessage());}
 }
 $mensaje="$ok enviados en esta tanda; $fallos errores.";
 $q=$pdo->prepare($sql);$q->execute([$c['id'],$c['edicion_id']]);$rows=$q->fetchAll();
}
$total=count($rows);$enviados=count(array_filter($rows,fn($r)=>$r['estado']==='enviado'));$sinIntento=count(array_filter($rows,fn($r)=>$r['estado']===null));$revisar=$total-$enviados-$sinIntento;
$pageTitle='Recuperación de recordatorios';$pageSection='correos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><section class="admin-card"><h1>Recordatorios del 11 de noviembre</h1><p>Inteligencia Artificial y prueba digital en tiempos disruptivos de la Gestión Pública · 14:00 hs</p><h2><?= $enviados ?> de <?= $total ?> enviados</h2><p><?= $sinIntento ?> sin intentar · <?= $revisar ?> requieren revisión</p><p role="status"><?= AdminForm::escape($mensaje) ?></p>
<?php if($sinIntento && time()<strtotime($c['fecha_inicio'])):?><form method="post"><?= Csrf::field() ?><button type="submit" class="admin-button">Enviar siguiente tanda de hasta 5</button></form><?php endif;?>
<p>Se omiten todos los correos con un intento previo. Los resultados se guardan en el historial de envíos.</p><a href="/admin/correos/historial.php?tipo=recordatorio_clase&amp;edicion=<?= (int)$c['edicion_id'] ?>">Ver historial</a></section></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
