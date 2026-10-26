<?php
declare(strict_types=1);
require_once __DIR__.'/MailService.php';
require_once __DIR__.'/CorreoTraza.php';

final class CorreoControl
{
    public function __construct(private PDO $pdo, private ?MailService $mailer = null) {}

    public function enviar(int $inscripcion, ?int $clase = null, string $origen = 'automatico', ?int $admin = null, int $limite = 0, bool $confirmarReenvio = false): bool
    {
        // El mismo lock del cron impide reintentos simultáneos desde el administrador.
        $lock = $clase ? 'ecap_recordatorios_clase' : 'ecap_confirmacion_'.$inscripcion;
        $q=$this->pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new DomainException('Hay un envío en proceso. Esperá y actualizá la página.');
        try {
            $q=$this->pdo->prepare("SELECT i.*,p.nombre,p.apellido,p.email,e.estado AS edicion_estado,e.fecha_inicio,e.fecha_fin,e.modalidad,e.zoom_url,e.zoom_meeting_id,e.zoom_passcode,a.titulo FROM inscripciones i JOIN personas p ON p.id=i.persona_id JOIN ediciones e ON e.id=i.edicion_id JOIN actividades a ON a.id=e.actividad_id WHERE i.id=?");
            $q->execute([$inscripcion]);$d=$q->fetch();
            if (!$d || $d['estado']!=='confirmada' || in_array($d['edicion_estado'],['borrador','cancelada'],true)) throw new DomainException('La inscripción o actividad ya no admite este envío.');
            if (!$clase && $origen==='manual' && strtotime($d['fecha_inicio'])<=time() && !($confirmarReenvio && $admin)) throw new DomainException('La actividad ya comenzó. No corresponde reenviar su confirmación.');
            if($d['edicion_estado']==='finalizada' && !($origen==='manual' && $confirmarReenvio && $admin))throw new DomainException('La actividad finalizada solo admite un reenvío manual confirmado.');
            $c=null;
            if ($clase) {
                $q=$this->pdo->prepare('SELECT c.*,(SELECT COUNT(*) FROM clases WHERE edicion_id=c.edicion_id) AS total FROM clases c WHERE c.id=? AND c.edicion_id=?');$q->execute([$clase,$d['edicion_id']]);$c=$q->fetch();
                if (!$c || substr($c['fecha_inicio'],0,10)!==date('Y-m-d') || strtotime($c['fecha_inicio'])<=time() || date('H:i')<'07:00') throw new DomainException('El recordatorio solo corresponde hoy, desde las 07:00 y antes del inicio de la clase.');
            }
            $tipo=$clase?'recordatorio_clase':'confirmacion_inscripcion';
            $q=$this->pdo->prepare('SELECT * FROM comunicaciones_transaccionales WHERE inscripcion_id=? AND clase_id <=> ? AND tipo=? ORDER BY id DESC LIMIT 1');$q->execute([$inscripcion,$clase,$tipo]);$ct=$q->fetch();
            if ($ct && $ct['estado']==='enviado') return false;
            if ($ct) {
                $q=$this->pdo->prepare("SELECT COUNT(*) FROM correo_intentos WHERE comunicacion_id=? AND resultado='incierto'");$q->execute([$ct['id']]);
                if ($q->fetchColumn() && !($origen==='manual' && $confirmarReenvio && $admin)) throw new DomainException('El resultado del intento anterior es incierto. Revisá el servidor de correo antes de reenviar para evitar duplicados.');
                if ($limite && (int)$ct['intentos'] >= $limite) return false;
                if ($origen==='manual' && $ct['ultimo_intento_en'] && strtotime($ct['ultimo_intento_en'])>time()-60) throw new DomainException('Esperá un minuto antes de volver a intentar.');
            } else {
                $this->pdo->prepare("INSERT INTO comunicaciones_transaccionales(inscripcion_id,clase_id,tipo,destinatario_email,estado,intentos) VALUES(?,?,?,?,'pendiente',0)")->execute([$inscripcion,$clase,$tipo,$d['email']]);
                $ct=['id'=>(int)$this->pdo->lastInsertId()];
            }
            // Se persiste ANTES de SMTP: una interrupción no queda como un éxito ni se reenvía a ciegas.
            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare("INSERT INTO correo_intentos(comunicacion_id,origen,administrador_id) VALUES(?,?,?)")->execute([$ct['id'],$origen,$admin]);$intento=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare("UPDATE comunicaciones_transaccionales SET estado='pendiente',intentos=intentos+1,ultimo_intento_en=NOW(),destinatario_email=? WHERE id=?")->execute([$d['email'],$ct['id']]);
                $this->pdo->commit();
            } catch(Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
            $traza=null;
            try {
                $traza=new CorreoTraza($this->pdo,$intento,'<ecap-'.$ct['id'].'-'.$intento.'-'.bin2hex(random_bytes(8)).'@ecap.com.ar>');
                $mail=$this->mailer ?? new MailService();
                $mail->observar($traza);
                if ($clase) $mail->enviarRecordatorioClase($d['email'],trim($d['nombre'].' '.$d['apellido']),$d['titulo'],$c['fecha_inicio'],$d['modalidad'],$d['zoom_url']??'',$d['zoom_meeting_id']??'',$d['zoom_passcode']??'',(int)$c['numero'],$c['titulo']??'',(int)$c['total']);
                else $mail->enviarConfirmacionInscripcion($d['email'],$d['nombre'],$d['titulo'],$d['fecha_inicio'],$d['modalidad'],$d['zoom_url']??'',$d['zoom_meeting_id']??'',$d['zoom_passcode']??'');
            } catch(Throwable $e) {
                $detalle=$traza ? $traza->diagnostico() : 'No se pudo iniciar la trazabilidad. Revisar la migración correo_eventos y la conexión a la base.';
                if ($traza && $traza->aceptado) {
                    // La respuesta final 250 ya está persistida: un fallo posterior no cambia esa evidencia.
                    $this->resultado((int)$ct['id'],$intento,'enviado');
                    $traza->cerrar();
                    return true;
                }
                if ($traza && $traza->ambiguo()) {
                    $traza->evento('revision',null,$traza->aceptado?'El servidor aceptó el mensaje; el cierre del proceso requiere revisión.':$detalle);
                    $traza->cerrar();
                    $this->pdo->prepare('UPDATE comunicaciones_transaccionales SET error_detalle=? WHERE id=?')->execute([$detalle,$ct['id']]);
                    throw new DomainException('El intento requiere revisión. No se reenvió para evitar duplicados.');
                }
                $this->resultado((int)$ct['id'],$intento,'error',$detalle);
                if($traza){$traza->evento('fallido',null,$detalle);$traza->cerrar();}
                error_log('ECAP correo fallido #'.$ct['id'].': '.$e->getMessage());
                throw new DomainException('El servidor no confirmó el envío. Revisá la dirección y la configuración de correo.');
            }
            // Los transportes de prueba también deben registrar su aceptación explícita.
            if (!$traza->aceptado) $traza->evento(Environment::isLocal()?'capturado_local':'aceptado');
            $this->resultado((int)$ct['id'],$intento,'enviado');
            $traza->cerrar();
            return true;
        } finally {$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }

    private function resultado(int $id,int $intento,string $estado,?string $detalle=null): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE correo_intentos SET resultado=?,finalizado_en=NOW() WHERE id=?')->execute([$estado,$intento]);
            $this->pdo->prepare("UPDATE comunicaciones_transaccionales SET estado=?,enviado_en=IF(?=1,NOW(),enviado_en),error_detalle=? WHERE id=?")->execute([$estado,(int)($estado==='enviado'),$estado==='error'?($detalle??'El servidor no confirmó el envío.'):null,$id]);
            $this->pdo->commit();
        } catch(Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function pendientes(?int $edicion=null): array
    {
        // No se infiere el resultado de confirmaciones anteriores a la activación.
        $sql="SELECT i.id AS inscripcion_id,NULL AS clase_id,'confirmacion_inscripcion' AS tipo,p.nombre,p.apellido,p.email,a.titulo,i.edicion_id,e.fecha_inicio AS fecha,ct.id AS comunicacion_id,ct.estado,ct.intentos,ct.ultimo_intento_en,ct.error_detalle,
        EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto' AND ci.id=(SELECT MAX(ultimo.id) FROM correo_intentos ultimo WHERE ultimo.comunicacion_id=ct.id)) AS incierto
        FROM inscripciones i JOIN personas p ON p.id=i.persona_id JOIN ediciones e ON e.id=i.edicion_id JOIN actividades a ON a.id=e.actividad_id CROSS JOIN correo_control cc
        LEFT JOIN comunicaciones_transaccionales ct ON ct.id=(SELECT MAX(x.id) FROM comunicaciones_transaccionales x WHERE x.inscripcion_id=i.id AND x.tipo='confirmacion_inscripcion' AND x.clase_id IS NULL)
        WHERE cc.id=1 AND i.estado='confirmada' AND e.estado NOT IN ('borrador','cancelada')
        AND (ct.estado='error' OR (ct.estado='pendiente' AND COALESCE(ct.ultimo_intento_en,ct.creado_en)<=DATE_SUB(NOW(),INTERVAL 5 MINUTE)) OR (ct.id IS NULL AND i.id>=cc.inscripcion_desde AND i.inscripto_en<=DATE_SUB(NOW(),INTERVAL 5 MINUTE)))
        AND (? IS NULL OR i.edicion_id=?)
        UNION ALL
        SELECT i.id,c.id,'recordatorio_clase',p.nombre,p.apellido,p.email,a.titulo,i.edicion_id,c.fecha_inicio,ct.id,ct.estado,ct.intentos,ct.ultimo_intento_en,ct.error_detalle,
        EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto' AND ci.id=(SELECT MAX(ultimo.id) FROM correo_intentos ultimo WHERE ultimo.comunicacion_id=ct.id))
        FROM inscripciones i JOIN personas p ON p.id=i.persona_id JOIN ediciones e ON e.id=i.edicion_id JOIN actividades a ON a.id=e.actividad_id JOIN clases c ON c.edicion_id=e.id CROSS JOIN correo_control cc
        LEFT JOIN comunicaciones_transaccionales ct ON ct.inscripcion_id=i.id AND ct.clase_id=c.id AND ct.tipo='recordatorio_clase'
        WHERE cc.id=1 AND i.estado='confirmada' AND e.estado NOT IN ('borrador','cancelada') AND DATE(c.fecha_inicio)=CURDATE()
        AND (ct.estado='error' OR (ct.estado='pendiente' AND COALESCE(ct.ultimo_intento_en,ct.creado_en)<=DATE_SUB(NOW(),INTERVAL 5 MINUTE)) OR (ct.id IS NULL AND TIME(NOW())>='07:40:00' AND cc.desde<=TIMESTAMP(CURDATE(),'07:00:00') AND i.inscripto_en<=TIMESTAMP(CURDATE(),'07:30:00')))
        AND (? IS NULL OR i.edicion_id=?)
        UNION ALL
        SELECT i.id,NULL,'certificado',p.nombre,p.apellido,p.email,a.titulo,i.edicion_id,e.fecha_inicio,ct.id,ct.estado,ct.intentos,ct.ultimo_intento_en,ct.error_detalle,
        EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto' AND ci.id=(SELECT MAX(ultimo.id) FROM correo_intentos ultimo WHERE ultimo.comunicacion_id=ct.id))
        FROM comunicaciones_transaccionales ct JOIN inscripciones i ON i.id=ct.inscripcion_id JOIN personas p ON p.id=i.persona_id JOIN ediciones e ON e.id=i.edicion_id JOIN actividades a ON a.id=e.actividad_id JOIN certificados cert ON cert.inscripcion_id=i.id
        WHERE ct.tipo='certificado' AND cert.estado='emitido'
        AND (ct.estado='error' OR (ct.estado='pendiente' AND COALESCE(ct.ultimo_intento_en,ct.creado_en)<=DATE_SUB(NOW(),INTERVAL 5 MINUTE)))
        AND (? IS NULL OR i.edicion_id=?) ORDER BY fecha,apellido,nombre";
        $q=$this->pdo->prepare($sql);$q->execute([$edicion,$edicion,$edicion,$edicion,$edicion,$edicion]);return $q->fetchAll();
    }

    public function cron(): array
    {
        $last=$this->pdo->query('SELECT * FROM correo_ejecuciones ORDER BY id DESC LIMIT 1')->fetch()?:null;
        $desde=$this->pdo->query('SELECT desde FROM correo_control WHERE id=1')->fetchColumn();
        $hayClases=(bool)$this->pdo->query("SELECT EXISTS(SELECT 1 FROM clases c JOIN ediciones e ON e.id=c.edicion_id JOIN inscripciones i ON i.edicion_id=e.id AND i.estado='confirmada' WHERE DATE(c.fecha_inicio)=CURDATE() AND e.estado NOT IN ('borrador','cancelada') AND i.inscripto_en<=TIMESTAMP(CURDATE(),'07:30:00'))")->fetchColumn();
        $esperado=$hayClases && $desde && $desde<=date('Y-m-d').' 07:00:00' && date('H:i')>='07:40';
        $hoy=$last && substr($last['inicio'],0,10)===date('Y-m-d');
        $alerta=$esperado && (!$hoy || $last['estado']!=='completado');
        return ['ultima'=>$last,'alerta'=>$alerta,'desde'=>$desde];
    }
}
