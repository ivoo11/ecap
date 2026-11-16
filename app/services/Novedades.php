<?php
declare(strict_types=1);
require_once __DIR__.'/NovedadPlantilla.php';
require_once __DIR__.'/MailTransport.php';

final class Novedades
{
 public function __construct(private PDO $db) {}
 public function edicion(int $id):array {
  $q=$this->db->prepare('SELECT e.*,a.titulo,a.slug,a.imagen_portada,a.estado AS publicacion FROM ediciones e JOIN actividades a ON a.id=e.actividad_id WHERE e.id=?');$q->execute([$id]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: throw new DomainException('Edición no encontrada.');
 }
 public function disponible(array $e):bool {
  if($e['publicacion']!=='publicada'||$e['estado']!=='inscripcion_abierta'||strtotime($e['fecha_inicio'])<=time())return false;
  if(!empty($e['inscripcion_desde'])&&strtotime($e['inscripcion_desde'])>time())return false;
  if(!empty($e['inscripcion_hasta'])&&strtotime($e['inscripcion_hasta'])<=time())return false;
  if(!empty($e['cupo_maximo'])){$q=$this->db->prepare("SELECT COUNT(*) FROM inscripciones WHERE edicion_id=? AND estado='confirmada'");$q->execute([$e['id']]);if((int)$q->fetchColumn()>=(int)$e['cupo_maximo'])return false;}
  return true;
 }
 public function destinatarios():array {
  // Último consentimiento por persona, sin presuponer consentimiento de la base histórica.
  // Una sola copia por dirección, y una baja compartida prevalece sobre perfiles duplicados.
  return $this->db->query("SELECT MIN(p.id) AS persona_id,LOWER(TRIM(p.email)) AS email FROM personas p JOIN consentimientos c ON c.id=(SELECT MAX(c2.id) FROM consentimientos c2 WHERE c2.persona_id=p.id AND c2.tipo='email_marketing') WHERE p.estado='activo' AND c.aceptado=1 AND NOT EXISTS(SELECT 1 FROM novedades_bajas b WHERE b.email=LOWER(TRIM(p.email))) GROUP BY LOWER(TRIM(p.email)) ORDER BY email")->fetchAll(PDO::FETCH_ASSOC);
 }
 public function elegibles():array {return array_values(array_filter($this->destinatarios(),static fn($r)=>filter_var($r['email'],FILTER_VALIDATE_EMAIL)));}
 public function campana(int $edicion):?array {$q=$this->db->prepare('SELECT * FROM novedades_campanas WHERE edicion_id=?');$q->execute([$edicion]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
 public function guardar(int $edicion,string $asunto,string $intro,int $admin):void {
  if(mb_strlen($asunto)<3||mb_strlen($asunto)>190||preg_match('/[\r\n]/',$asunto)||mb_strlen($intro)<15||mb_strlen($intro)>1600)throw new DomainException('Usá un asunto de 3 a 190 caracteres y una introducción de 15 a 1600 caracteres.');
  $this->edicion($edicion);
  $q=$this->db->prepare("INSERT INTO novedades_campanas(edicion_id,asunto,introduccion,creado_por) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE asunto=IF(estado='borrador',VALUES(asunto),asunto),introduccion=IF(estado='borrador',VALUES(introduccion),introduccion)");$q->execute([$edicion,$asunto,$intro,$admin]);
 }
 public function huella(array $c,array $dest):string {return hash('sha256',json_encode([$c['asunto'],$c['introduccion'],$dest,$this->edicion((int)$c['edicion_id'])]));}
 public function iniciar(int $edicion,string $huella):void {
  $this->db->beginTransaction();
  try{
   $q=$this->db->prepare('SELECT * FROM novedades_campanas WHERE edicion_id=? FOR UPDATE');$q->execute([$edicion]);$c=$q->fetch(PDO::FETCH_ASSOC);
   if(!$c||$c['estado']!=='borrador')throw new DomainException('El anuncio ya fue preparado. No se creará otro envío.');
   $e=$this->edicion($edicion);if(!$this->disponible($e))throw new DomainException('La actividad debe estar publicada, con inscripciones abiertas y cupos disponibles.');
   $dest=$this->elegibles();if(!$dest)throw new DomainException('No hay personas que hayan aceptado recibir novedades.');
   if(!hash_equals($this->huella($c,$dest),$huella))throw new DomainException('Cambió el contenido o la lista de destinatarios. Revisá nuevamente la vista previa.');
   $q=$this->db->prepare('INSERT INTO novedades_destinatarios(campana_id,persona_id,email,token) VALUES(?,?,?,?)');
   foreach($dest as $r)$q->execute([$c['id'],$r['persona_id'],$r['email'],bin2hex(random_bytes(32))]);
   $this->db->prepare("UPDATE novedades_campanas SET estado='en_cola',contenido=?,iniciado_en=NOW() WHERE id=?")->execute([NovedadPlantilla::html($e,$c['introduccion']),$c['id']]);$this->db->commit();
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function baja(string $token):bool {
  $q=$this->db->prepare('SELECT email FROM novedades_destinatarios WHERE token=?');$q->execute([$token]);$email=$q->fetchColumn();if(!$email)return false;
  $this->db->beginTransaction();
  try{
   $this->db->prepare('INSERT IGNORE INTO novedades_bajas(email) VALUES(?)')->execute([$email]);
   $this->db->prepare("INSERT INTO consentimientos(persona_id,tipo,aceptado,origen) SELECT id,'email_marketing',0,'baja_novedades' FROM personas WHERE LOWER(TRIM(email))=?")->execute([$email]);
   $this->db->prepare("UPDATE novedades_destinatarios SET estado='omitido',motivo='La persona dejó de recibir novedades.' WHERE email=? AND estado IN ('pendiente','error')")->execute([$email]);$this->db->commit();return true;
  }catch(Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function resultados(int $id):array {$q=$this->db->prepare('SELECT d.*,p.nombre,p.apellido,i.fase AS ultima_fase,i.codigo AS smtp_codigo,i.iniciado_en AS ultimo_intento FROM novedades_destinatarios d JOIN personas p ON p.id=d.persona_id LEFT JOIN novedades_intentos i ON i.id=(SELECT MAX(x.id) FROM novedades_intentos x WHERE x.destinatario_id=d.id) WHERE campana_id=? ORDER BY d.id');$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);}
 public function pausar(int $campana):void {$this->db->prepare("UPDATE novedades_campanas SET estado='pausada' WHERE id=? AND estado='en_cola'")->execute([$campana]);}
 public function continuar(int $campana):void {$this->db->prepare("UPDATE novedades_campanas SET estado='en_cola' WHERE id=? AND estado='pausada'")->execute([$campana]);}
 public function reintentar(int $campana,int $id):void {
  $this->db->prepare("UPDATE novedades_destinatarios SET estado='pendiente' WHERE id=? AND campana_id=? AND estado='error'")->execute([$id,$campana]);
  $this->db->prepare("UPDATE novedades_campanas SET estado='en_cola' WHERE id=? AND estado='completada'")->execute([$campana]);
 }
 public function procesar(?int $campana=null):int {
  if(!(int)$this->db->query("SELECT GET_LOCK('ecap_novedades_envio',0)")->fetchColumn())return 0;
  $enviados=0;
  try{
   // Recuperar aceptación duradera incluso si se perdió el cierre del proceso.
   $this->db->exec("UPDATE novedades_destinatarios d JOIN novedades_intentos i ON i.id=(SELECT MAX(x.id) FROM novedades_intentos x WHERE x.destinatario_id=d.id) SET d.estado='enviado',d.enviado_en=i.iniciado_en,d.motivo=NULL WHERE d.estado='enviando' AND i.fase='aceptado'");
   // Los intentos abandonados no se reenvían automáticamente: podrían haber sido aceptados.
   $this->db->exec("UPDATE novedades_destinatarios SET estado='revision',motivo='El envío se interrumpió. Revisar el último intento antes de reenviar.' WHERE estado='enviando'");
   // Límite conservador propio: nunca consume más de 100 intentos de novedades en 24 horas.
   $usados=(int)$this->db->query('SELECT COUNT(*) FROM novedades_intentos WHERE iniciado_en>DATE_SUB(NOW(),INTERVAL 24 HOUR)')->fetchColumn();
   $limite=max(0,min(5,100-$usados));if(!$limite)return 0;
   $q=$this->db->query("SELECT d.*,c.asunto,c.contenido,c.edicion_id FROM novedades_destinatarios d JOIN novedades_campanas c ON c.id=d.campana_id JOIN ediciones e ON e.id=c.edicion_id JOIN actividades a ON a.id=e.actividad_id WHERE d.estado='pendiente' AND c.estado='en_cola' AND a.estado='publicada' AND e.estado='inscripcion_abierta' AND e.fecha_inicio>NOW() ".($campana?" AND c.id=".(int)$campana:"")." ORDER BY d.id LIMIT ".$limite);
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
    $actual=$this->db->prepare("SELECT d.estado FROM novedades_destinatarios d JOIN novedades_campanas c ON c.id=d.campana_id WHERE d.id=? AND c.estado='en_cola'");$actual->execute([$r['id']]);
    if($actual->fetchColumn()!=='pendiente')continue;
    if(!$this->disponible($this->edicion((int)$r['edicion_id'])))continue;
    $valido=$this->db->prepare("SELECT p.id FROM personas p JOIN consentimientos c ON c.id=(SELECT MAX(x.id) FROM consentimientos x WHERE x.persona_id=p.id AND x.tipo='email_marketing') WHERE p.id=? AND LOWER(TRIM(p.email))=? AND p.estado='activo' AND c.aceptado=1 AND NOT EXISTS(SELECT 1 FROM novedades_bajas b WHERE b.email=?)");$valido->execute([$r['persona_id'],$r['email'],$r['email']]);
    if(!$valido->fetchColumn()){$this->db->prepare("UPDATE novedades_destinatarios SET estado='omitido',motivo='Cambió el consentimiento, la dirección o el estado de la persona.' WHERE id=?")->execute([$r['id']]);continue;}
    $claim=$this->db->prepare("UPDATE novedades_destinatarios SET estado='enviando',motivo=NULL WHERE id=? AND estado='pendiente'");$claim->execute([$r['id']]);if(!$claim->rowCount())continue;
    $message='<novedad-'.bin2hex(random_bytes(16)).'@ecap.com.ar>';
    $this->db->prepare('INSERT INTO novedades_intentos(destinatario_id,message_id) VALUES(?,?)')->execute([$r['id'],$message]);$intento=(int)$this->db->lastInsertId();
    $smtp=null;
    try{
     $mail=MailTransport::create();$mail->Timeout=15;$mail->MessageID=$message;
     if(!Environment::isLocal()){$smtp=new NovedadSMTP($this->db,$intento);$smtp->Timeout=15;$smtp->Timelimit=20;$mail->setSMTPInstance($smtp);}
     $mail->addAddress($r['email']);$mail->Subject=$r['asunto'];$mail->isHTML(true);
     $mail->Body=str_replace('__BAJA_TOKEN__',$r['token'],$r['contenido']);
     $mail->AltBody=html_entity_decode(strip_tags(str_replace(['</p>','<br>'],"\n",preg_replace('~<style\b[^>]*>.*?</style>~is','',$mail->Body))),ENT_QUOTES,'UTF-8')."\nDejar de recibir novedades: https://ecap.com.ar/novedades/baja.php?token=".$r['token'];
     if(!$mail->send())throw new RuntimeException('Transporte sin confirmación.');
     $this->db->prepare("UPDATE novedades_intentos SET fase='aceptado',terminado_en=NOW() WHERE id=?")->execute([$intento]);
     $this->db->prepare("UPDATE novedades_destinatarios SET estado='enviado',enviado_en=NOW() WHERE id=?")->execute([$r['id']]);$enviados++;
    }catch(Throwable $e){
     $fase=$smtp?->fase??'preparando';$codigo=$smtp?->codigo;
     $estado=$smtp?->aceptado?'enviado':((in_array($fase,['transmitiendo','esperando_aceptacion'],true)&&(!$codigo||$codigo<400))?'revision':'error');
     $motivo=$estado==='enviado'?null:'Etapa: '.$fase.'. '.($codigo?'Respuesta SMTP: '.$codigo.'.':'Sin respuesta SMTP.').' Revisá el detalle del intento.';
     $this->db->prepare('UPDATE novedades_destinatarios SET estado=?,motivo=?,enviado_en=IF(?=\'enviado\',NOW(),enviado_en) WHERE id=?')->execute([$estado,$motivo,$estado,$r['id']]);
     $this->db->prepare('UPDATE novedades_intentos SET terminado_en=NOW() WHERE id=?')->execute([$intento]);
     error_log('ECAP novedades: fallo en intento '.$intento);
     // Detener lote ante fallo: no castigar el servidor con el resto de destinatarios.
     break;
    }
   }
   $this->db->exec("UPDATE novedades_campanas c SET estado='completada' WHERE estado='en_cola' AND NOT EXISTS(SELECT 1 FROM novedades_destinatarios d WHERE d.campana_id=c.id AND d.estado IN ('pendiente','enviando'))");
   return $enviados;
  }finally{$this->db->query("SELECT RELEASE_LOCK('ecap_novedades_envio')");}
 }
}

final class NovedadSMTP extends \PHPMailer\PHPMailer\SMTP
{
 public string $fase='conectando';public ?int $codigo=null;public bool $aceptado=false;
 public function __construct(private PDO $db,private int $intento){}
 protected function sendCommand($command,$commandstring,$expect){
  $fase=match($command){'AUTH','Username','Password','User & Password'=>'autenticando','RCPT TO'=>'destinatario','DATA'=>'transmitiendo','DATA END'=>'esperando_aceptacion',default=>null};
  if($fase){$this->fase=$fase;$this->codigo=null;$this->registrar();}
  $ok=parent::sendCommand($command,$commandstring,$expect);
  if($fase){$this->codigo=(int)substr($this->getLastReply(),0,3);if($command==='DATA END'&&$ok){$this->aceptado=true;$this->fase='aceptado';}$this->registrar();}
  return $ok;
 }
 private function registrar():void {$this->db->prepare('UPDATE novedades_intentos SET fase=?,codigo=? WHERE id=?')->execute([$this->fase,$this->codigo,$this->intento]);}
}
