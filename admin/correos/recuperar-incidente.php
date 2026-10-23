<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../../app/services/AdminForm.php';
require_once __DIR__.'/../../app/services/CorreoControl.php';
if(date('Y-m-d')!=='2026-10-23'){http_response_code(410);exit('Recuperación vencida.');}

// Registro privado durable: nunca se borra ni se transforma un intento anterior.
$path=dirname(__DIR__,3).'/ecap_private/recuperacion-recordatorios-20261023.json';
$f=fopen($path,'c+');
if(!$f || !flock($f,LOCK_EX|LOCK_NB)){http_response_code(409);exit('Hay una recuperación en proceso.');}
chmod($path,0600);
$raw=stream_get_contents($f);$log=$raw!==''?json_decode($raw,true,512,JSON_THROW_ON_ERROR):null;
$save=static function()use($f,&$log):void{
 $json=json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 rewind($f);if(!ftruncate($f,0)||fwrite($f,$json)!==strlen($json)||!fflush($f))throw new RuntimeException('No se pudo conservar el registro.');
 if(function_exists('fsync'))fsync($f);
};
final class RecuperacionSMTP extends \PHPMailer\PHPMailer\SMTP {
 public $registrar;
 public function sendCommand($command,$commandstring,$expect){
  $ok=parent::sendCommand($command,$commandstring,$expect);
  if(in_array($command,['MAIL FROM','RCPT TO','DATA','DATA END'],true))($this->registrar)(['paso'=>$command,'ok'=>$ok,'respuesta'=>$this->getLastReply(),'hora'=>date(DATE_ATOM)]);
  return $ok;
 }
}
final class RecuperacionMail extends MailService {
 public function __construct(private $registrar){}
 protected function crearMailer(): \PHPMailer\PHPMailer\PHPMailer {
  $m=MailTransport::create();$m->Timeout=20;$m->Timelimit=25;
  $smtp=new RecuperacionSMTP();$smtp->registrar=$this->registrar;$m->setSMTPInstance($smtp);return $m;
 }
}
$error=null;$continuar=false;
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  AdminForm::csrf();
  if(!$log){
   $q=$pdo->query("SELECT c.id,c.fecha_inicio FROM clases c WHERE c.edicion_id=3 AND DATE(c.fecha_inicio)=CURDATE() AND c.fecha_inicio>NOW() ORDER BY c.fecha_inicio LIMIT 1");$clase=$q->fetch();
   if(!$clase)throw new DomainException('No hay una clase pendiente hoy.');
   $ids=$pdo->query("SELECT id FROM inscripciones WHERE edicion_id=3 AND estado='confirmada' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
   if(count($ids)!==94)throw new DomainException('Cambió la cantidad de inscriptos. Revisar antes de enviar.');
   $log=['inicio'=>date(DATE_ATOM),'admin'=>Auth::id(),'clase'=>(int)$clase['id'],'ids'=>array_map('intval',$ids),'resultados'=>[]];$save();
  }
  if(AdminForm::text('accion')==='reanudar'){
   foreach($log['resultados'] as $id=>$r){
    if($r['estado']!=='revisar')continue;
    $ultimo=end($r['smtp']);
    if(!$ultimo || $ultimo['paso']!=='DATA END' || $ultimo['ok'] || !preg_match('/^451\b/',$ultimo['respuesta']))throw new DomainException('El intento no tiene un rechazo temporal confirmado. Requiere revisión.');
    $log['rechazos'][$id][]=$r;unset($log['resultados'][$id]);$save();
   }
  }
  foreach($log['resultados'] as $r)if(!in_array($r['estado'],['aceptado','ya_aceptado'],true))throw new DomainException('Lote detenido para revisar un resultado. No se reenvía automáticamente.');
  $pendientes=array_values(array_diff($log['ids'],array_map('intval',array_keys($log['resultados']))));
  if($pendientes){
   $id=$pendientes[0];$log['resultados'][$id]=['estado'=>'iniciado','hora'=>date(DATE_ATOM),'smtp'=>[]];$save();
   $registrar=static function(array $evento)use(&$log,$id,$save):void{$log['resultados'][$id]['smtp'][]=$evento;$save();};
   try{
    $control=new CorreoControl($pdo,new RecuperacionMail($registrar));
    $sent=$control->enviar($id,(int)$log['clase'],'recuperacion',(int)Auth::id(),0,true);
    $log['resultados'][$id]['estado']=$sent?'aceptado':'ya_aceptado';
   }catch(Throwable $e){$log['resultados'][$id]['estado']='revisar';$log['resultados'][$id]['error']=$e->getMessage();$error='Se detuvo el lote para revisar el envío #'.$id.'.';}
   $log['resultados'][$id]['fin']=date(DATE_ATOM);$save();
   $continuar=$error===null && count($log['resultados'])<count($log['ids']);
  }
 }
}catch(Throwable $e){$error=$e->getMessage();}
flock($f,LOCK_UN);fclose($f);
$aceptados=0;foreach($log['resultados']??[] as $r)if(in_array($r['estado'],['aceptado','ya_aceptado'],true))$aceptados++;
$pageTitle='Recuperación de recordatorios';$pageSection='correos';require __DIR__.'/../includes/header.php';
?>
<section class="admin-page"><div class="admin-card"><h1>Recordatorios de hoy</h1><p>Recuperación autorizada de los 94 inscriptos de Fernández Villa. Se conservan los intentos anteriores; algunos destinatarios podrían recibir un duplicado.</p>
<h2><?= $aceptados ?> de 94 aceptados por el servidor</h2>
<?php if($error): ?><p role="alert"><?= AdminForm::escape($error) ?></p><?php endif; ?>
<?php if($log): foreach($log['resultados'] as $id=>$r): if(!in_array($r['estado'],['aceptado','ya_aceptado'],true)): ?>
<h3>Revisar inscripción #<?= (int)$id ?></h3><?php foreach($r['smtp'] as $evento): ?><p><?= AdminForm::escape($evento['paso'].': '.$evento['respuesta']) ?></p><?php endforeach; ?><p><?= AdminForm::escape($r['error']??'No hay un resultado final. No se repetirá el intento automáticamente.') ?></p>
<?php endif; endforeach; endif; ?>
<?php
$temporal=false;
foreach($log['resultados']??[] as $r){$ultimo=$r['smtp']?end($r['smtp']):null;if($r['estado']==='revisar' && $ultimo && $ultimo['paso']==='DATA END' && !$ultimo['ok'] && preg_match('/^451\b/',$ultimo['respuesta']))$temporal=true;}
if($temporal): ?><p>El proveedor rechazó temporalmente el envío. Reanudar únicamente después de resolver el límite. Los envíos ya aceptados no se repetirán.</p><form method="post"><?= Csrf::field() ?><input type="hidden" name="accion" value="reanudar"><button class="admin-button" type="submit">Reanudar pendientes tras resolver el límite</button></form><?php endif; ?>
<?php if(!$error && !$temporal && $aceptados<94): ?><form id="continuar" method="post"><?= Csrf::field() ?><button class="admin-button" type="submit"><?= $log?'Continuar recuperación':'Iniciar envío a los 94' ?></button></form><?php endif; ?>
<?php if($aceptados===94): ?><p>Recuperación completada. Los resultados individuales quedaron registrados.</p><?php endif; ?>
</div></section>
<?php if($continuar): ?><script>setTimeout(function(){document.getElementById('continuar').requestSubmit();},1200);</script><?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
