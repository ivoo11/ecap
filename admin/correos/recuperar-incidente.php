<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');exit('La recuperación finalizó. Esta pantalla es de solo lectura.');}
$path=dirname(__DIR__,3).'/ecap_private/recuperacion-recordatorios-20261023.json';
$log=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):null;
$total=count($log['ids']??[]);$aceptados=0;$rechazos=0;
foreach($log['resultados']??[] as $r)if(in_array($r['estado'],['aceptado','ya_aceptado'],true))$aceptados++;
foreach($log['rechazos']??[] as $r)$rechazos+=count($r);
$pageTitle='Resultado de recuperación';$pageSection='correos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><div class="admin-card"><h1>Recordatorios del 23 de octubre</h1><h2><?= $aceptados ?> de <?= $total ?> aceptados por el servidor</h2><p>La recuperación terminó. Cada envío tiene su resultado y fecha registrados. La herramienta de envío ya está deshabilitada.</p><p><?= $rechazos ?> rechazo temporal conservado en el historial de recuperación. El lote se reanudó después de ampliar el plan de correo, sin repetir los envíos ya aceptados.</p><p>La aceptación del servidor no confirma llegada a la bandeja de entrada.</p><a class="admin-button" href="/admin/correos/">Volver al control de correos</a></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
