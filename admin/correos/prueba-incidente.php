<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/MailService.php';
if(date('Y-m-d')!=='2026-10-23'){http_response_code(410);exit('Prueba vencida.');}
final class IncidenteSMTP extends \PHPMailer\PHPMailer\SMTP {
 public array $registro=[];
 public function sendCommand($command,$commandstring,$expect){
  $ok=parent::sendCommand($command,$commandstring,$expect);
  if(in_array($command,['MAIL FROM','RCPT TO','DATA','DATA END'],true))$this->registro[]=['paso'=>$command,'ok'=>$ok,'respuesta'=>$this->getLastReply()];
  return $ok;
 }
}
final class IncidenteMail extends MailService {
 public IncidenteSMTP $smtp;
 protected function crearMailer(): \PHPMailer\PHPMailer\PHPMailer {
  $m=MailTransport::create();$m->Timeout=20;$m->Timelimit=25;
  $this->smtp=new IncidenteSMTP();$m->setSMTPInstance($this->smtp);return $m;
 }
}
$path=dirname(__DIR__,3).'/ecap_private/prueba-recordatorio-20261023.json';
$resultado=is_file($path)?file_get_contents($path):null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 AdminForm::csrf();
 $f=@fopen($path,'x');
 if(!$f){$resultado=is_file($path)?file_get_contents($path):'No se pudo guardar el registro. No se envió.';}
 else {
  chmod($path,0600);fwrite($f,json_encode(['estado'=>'iniciado','hora'=>date(DATE_ATOM)]));fflush($f);
  $m=new IncidenteMail();$r=['destino'=>'ivanmontano87@gmail.com','hora'=>date(DATE_ATOM),'admin'=>Auth::id()];
  try {
   $q=$pdo->query("SELECT e.*,a.titulo,c.numero,c.titulo AS clase_titulo,c.fecha_inicio AS clase_fecha,(SELECT COUNT(*) FROM clases WHERE edicion_id=e.id) AS total FROM ediciones e JOIN actividades a ON a.id=e.actividad_id JOIN clases c ON c.edicion_id=e.id WHERE e.id=3 AND DATE(c.fecha_inicio)=CURDATE() ORDER BY c.fecha_inicio LIMIT 1");$d=$q->fetch();
   if(!$d)throw new RuntimeException('No se encontró la clase de hoy.');
   $m->enviarRecordatorioClase('ivanmontano87@gmail.com','Ivo · Prueba de recordatorio',$d['titulo'],$d['clase_fecha'],$d['modalidad'],$d['zoom_url']??'',$d['zoom_meeting_id']??'',$d['zoom_passcode']??'',(int)$d['numero'],$d['clase_titulo']??'',(int)$d['total']);
   $r['estado']='aceptado';
  }catch(Throwable $e){$r['estado']='error';$r['error']=$e->getMessage();}
  $r['smtp']=isset($m->smtp)?$m->smtp->registro:[];$resultado=json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  rewind($f);ftruncate($f,0);fwrite($f,$resultado);fflush($f);fclose($f);
 }
}
$pageTitle='Prueba de recordatorio';$pageSection='correos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><div class="admin-card"><h1>Prueba única del recordatorio</h1><p>Actividad de hoy · Solo a ivanmontano87@gmail.com. No modifica los 93 intentos existentes.</p>
<?php if($resultado!==null): ?><pre style="white-space:pre-wrap"><?= AdminForm::escape($resultado) ?></pre><?php else: ?><form method="post"><?= Csrf::field() ?><button class="admin-button" type="submit">Enviar prueba única</button></form><?php endif; ?></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
