<?php
declare(strict_types=1);
require_once __DIR__.'/CertificadoService.php';
require_once __DIR__.'/CorreoTraza.php';

/** Certificados académicos: reutiliza diseño y correo sin crear inscripciones. */
final class CertificadoDocenteService extends CertificadoService
{
    protected const CONFIG_TABLE = 'certificado_docente_configuracion';
    protected const DEFAULT_HEADER = 'CERTIFICADO DE PARTICIPACIÓN';
    public const ROLES = ['expositor'=>'Expositor','expositora'=>'Expositora','expositor/a'=>'Expositor/a'];

    public static function rol(string $rol): string
    {
        if(!isset(self::ROLES[$rol]))throw new DomainException('Elegí Expositor o Expositora para el certificado.');
        return $rol;
    }
    public static function leyenda(string $rol): string
    {
        return 'POR SU PARTICIPACIÓN EN CALIDAD DE '.mb_strtoupper(self::rol($rol)).' EN';
    }
    public function config(int $id): array
    {
        $cfg=parent::config($id);
        if((int)$cfg['revision']===0){
            $base=(new CertificadoService($this->pdo))->config($id);
            $cfg['fondo']=$base['fondo'];$cfg['titulo']=$base['titulo'];
        }
        $cfg['leyenda']=self::leyenda('expositor/a');
        return $cfg;
    }
    public function participantes(int $id): array
    {
        $q=$this->pdo->prepare("SELECT d.id,d.persona_id,p.nombre,p.apellido,p.email,
        (ed.docente_id IS NOT NULL) AS asignado,ed.rol AS rol_asignado,
        cd.id AS certificado_id,cd.archivo,cd.rol,cd.emitido_en,
        IF(cd.id IS NULL,NULL,'emitido') AS certificado_estado,
        ct.estado AS correo_estado,ct.ultimo_intento_en,
        EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto' AND ci.id=(SELECT MAX(u.id) FROM correo_intentos u WHERE u.comunicacion_id=ct.id)) AS incierto
        FROM docentes d JOIN personas p ON p.id=d.persona_id
        LEFT JOIN edicion_docentes ed ON ed.docente_id=d.id AND ed.edicion_id=?
        LEFT JOIN certificados_docentes cd ON cd.docente_id=d.id AND cd.edicion_id=?
        LEFT JOIN comunicaciones_transaccionales ct ON ct.certificado_docente_id=cd.id
        WHERE ed.docente_id IS NOT NULL OR cd.id IS NOT NULL ORDER BY p.apellido,p.nombre,d.id");
        $q->execute([$id,$id]);return $q->fetchAll();
    }
    public static function elegible(array $p): bool
    {
        return (bool)$p['asignado'] && filter_var($p['email'],FILTER_VALIDATE_EMAIL)!==false && $p['correo_estado']!=='enviado' && !$p['incierto'];
    }
    public function enviar(int $edicion,int $docente,int $admin,string $huella,int $revision,bool $confirmarReenvio=false,string $rol='expositor/a'): bool
    {
        self::rol($rol);
        $lock='ecap_cert_docente_'.$edicion.'_'.$docente;
        $q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new DomainException('Este certificado tiene un envío en proceso.');
        try {
            $e=$this->edicion($edicion);$cfg=$this->config($edicion);
            if(in_array($e['estado'],['borrador','cancelada'],true) || strtotime($e['fecha_fin']??$e['fecha_inicio'])>time())throw new DomainException('La edición debe haber terminado para emitir certificados.');
            if(!$cfg['aprobado'] || (int)$cfg['revision']!==$revision)throw new DomainException('La plantilla cambió o no está habilitada. Revisá la selección.');
            $p=null;foreach($this->participantes($edicion) as $row)if((int)$row['id']===$docente)$p=$row;
            if(!$p || !$p['asignado'] || !hash_equals(self::huella($p),$huella))throw new DomainException('Los datos o la asignación del docente cambiaron. Revisá la selección.');
            if($p['correo_estado']==='enviado')return false;
            if(!filter_var($p['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('El docente necesita un correo válido antes de enviar.');
            if($p['incierto'] && !($confirmarReenvio && $admin>0))throw new DomainException('El envío no tiene resultado confirmado. Reintentá desde Control de correos.');
            if($p['ultimo_intento_en'] && strtotime($p['ultimo_intento_en'])>time()-300)throw new DomainException('Esperá cinco minutos antes de reintentar.');
            if(!$p['certificado_id']){
                $nombre=trim($p['nombre'].' '.$p['apellido']);$cfg['leyenda']=self::leyenda($rol);
                $pdf=CertificadoPdf::render($cfg,$nombre);$file=bin2hex(random_bytes(16)).'.pdf';$path=CertificadoPdf::file($file);
                if(file_put_contents($path,$pdf,LOCK_EX)!==strlen($pdf)){@unlink($path);throw new RuntimeException('No se pudo guardar el PDF.');}chmod($path,0600);
                $this->pdo->beginTransaction();
                try {
                    $this->pdo->prepare('INSERT INTO certificados_docentes(edicion_id,docente_id,nombre,titulo,encabezado,rol,archivo,plantilla_sha256,pdf_sha256,administrador_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$edicion,$docente,$nombre,$cfg['titulo'],$cfg['encabezado'],$rol,$file,hash_file('sha256',CertificadoPdf::file($cfg['fondo'])),hash('sha256',$pdf),$admin]);
                    $cert=(int)$this->pdo->lastInsertId();
                    $this->pdo->prepare("INSERT INTO comunicaciones_transaccionales(inscripcion_id,certificado_docente_id,tipo,destinatario_email,estado) VALUES(NULL,?,'certificado_docente',?,'pendiente')")->execute([$cert,$p['email']]);
                    $this->pdo->commit();
                }catch(Throwable $ex){if($this->pdo->inTransaction())$this->pdo->rollBack();@unlink($path);throw $ex;}
            }
            $q=$this->pdo->prepare('SELECT cd.*,ct.id AS comunicacion FROM certificados_docentes cd JOIN comunicaciones_transaccionales ct ON ct.certificado_docente_id=cd.id WHERE cd.edicion_id=? AND cd.docente_id=?');$q->execute([$edicion,$docente]);$doc=$q->fetch();
            if(!$doc || !is_file(CertificadoPdf::file($doc['archivo'])) || !hash_equals($doc['pdf_sha256'],hash_file('sha256',CertificadoPdf::file($doc['archivo']))))throw new DomainException('El PDF emitido necesita revisión. No se regenerará automáticamente.');
            $ct=(int)$doc['comunicacion'];
            $this->pdo->beginTransaction();
            try{
                $this->pdo->prepare("INSERT INTO correo_intentos(comunicacion_id,origen,administrador_id) VALUES(?,'manual',?)")->execute([$ct,$admin]);$intento=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare("UPDATE comunicaciones_transaccionales SET estado='pendiente',intentos=intentos+1,ultimo_intento_en=NOW(),destinatario_email=?,error_detalle=NULL WHERE id=?")->execute([$p['email'],$ct]);$this->pdo->commit();
            }catch(Throwable $ex){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $ex;}
            $traza=null;
            try{
                $traza=new CorreoTraza($this->pdo,$intento,'<ecap-docente-'.$ct.'-'.$intento.'-'.bin2hex(random_bytes(8)).'@ecap.com.ar>');
                $mail=$this->mail??new MailService();$mail->observar($traza);
                $mail->enviarCertificadoDocente($p['email'],$doc['nombre'],$doc['titulo'],CertificadoPdf::file($doc['archivo']),$doc['rol']);
            }catch(Throwable $ex){
                if($traza && $traza->aceptado){$this->resultadoDocente($ct,$intento,'enviado');$traza->cerrar();return true;}
                $detalle=$traza?$traza->diagnostico():'No se pudo iniciar el registro del envío. Revisar la conexión a la base de datos.';
                if($traza && $traza->ambiguo()){
                    $traza->evento('revision',null,$detalle);$traza->cerrar();
                    $this->pdo->prepare('UPDATE comunicaciones_transaccionales SET error_detalle=? WHERE id=?')->execute([$detalle,$ct]);
                    throw new DomainException('Envío sin confirmar. Revisá el detalle en Control de correos antes de reintentar.');
                }
                $this->resultadoDocente($ct,$intento,'error',$detalle);
                if($traza){$traza->evento('fallido',null,$detalle);$traza->cerrar();}
                error_log('ECAP certificado docente #'.$ct.': '.$ex->getMessage());
                throw new DomainException('El PDF está guardado, pero no se envió el correo. Podés reintentar desde Control de correos.');
            }
            if(!$traza->aceptado)$traza->evento(Environment::isLocal()?'capturado_local':'aceptado');
            $this->resultadoDocente($ct,$intento,'enviado');$traza->cerrar();return true;
        }finally{$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    private function resultadoDocente(int $ct,int $intento,string $estado,?string $detalle=null): void
    {
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE correo_intentos SET resultado=?,finalizado_en=NOW() WHERE id=?')->execute([$estado,$intento]);
            $this->pdo->prepare('UPDATE comunicaciones_transaccionales SET estado=?,enviado_en=IF(?=1,NOW(),enviado_en),error_detalle=? WHERE id=?')->execute([$estado,(int)($estado==='enviado'),$detalle,$ct]);
            $this->pdo->commit();
        }catch(Throwable $ex){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $ex;}
    }
}
