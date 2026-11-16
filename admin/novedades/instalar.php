<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
header('Cache-Control: no-store');
if(!Auth::hasRole('superadmin')){http_response_code(403);exit('Acceso no autorizado.');}
if(date('Y-m-d')!=='2026-11-16'){http_response_code(410);exit('La instalación temporal venció.');}
$mensaje='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 try{
  $sql=file_get_contents(__DIR__.'/../../database/migrations/20261116_novedades.sql');
  foreach(explode(';',$sql) as $sentencia)if(trim($sentencia)!=='')$pdo->exec($sentencia);
  $mensaje='Tablas de novedades preparadas. No se crearon campañas ni se enviaron correos.';
 }catch(Throwable $e){error_log('ECAP instalación novedades: '.$e->getMessage());$mensaje='La instalación no se completó. Revisar el registro antes de continuar.';}
}
$pageTitle='Preparar novedades';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><div class="admin-card"><h1>Preparar novedades</h1><p>Crea las tablas de anuncios, destinatarios, intentos, bajas y la prueba autorizada. No modifica las inscripciones ni los correos existentes.</p><p role="status"><?= AdminForm::escape($mensaje) ?></p><form method="post"><?= Csrf::field() ?><button type="submit" class="admin-button">Preparar las tablas autorizadas</button></form></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
