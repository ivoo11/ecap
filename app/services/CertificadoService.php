<?php
declare(strict_types=1);
require_once __DIR__.'/CertificadoPdf.php';
require_once __DIR__.'/MailService.php';

class CertificadoService
{
    protected const CONFIG_TABLE = 'certificado_configuracion';
    protected const DEFAULT_HEADER = '';
    public function __construct(protected PDO $pdo,protected ?MailService $mail=null) {}
    public function edicion(int $id): array
    {
        $q=$this->pdo->prepare('SELECT e.*,a.titulo FROM ediciones e JOIN actividades a ON a.id=e.actividad_id WHERE e.id=?');$q->execute([$id]);
        return $q->fetch() ?: throw new DomainException('Edición no encontrada.');
    }
    public function config(int $id): array
    {
        $q=$this->pdo->prepare('SELECT * FROM '.static::CONFIG_TABLE.' WHERE edicion_id=?');$q->execute([$id]);
        $config = $q->fetch() ?: ['edicion_id'=>$id,'encabezado'=>static::DEFAULT_HEADER,'titulo'=>$this->edicion($id)['titulo'],'fondo'=>null,'aprobado'=>0,'revision'=>0];
        $config['fondo'] = $config['fondo'] ?: CertificadoPdf::DEFAULT_BACKGROUND;
        $config['fecha_edicion'] = CertificadoPdf::fechaEdicion($this->edicion($id));
        return $config;
    }
    public function guardar(int $id,array $d,int $admin,int $revision): void
    {
        $d['fecha_edicion'] = CertificadoPdf::fechaEdicion($this->edicion($id));
        foreach(['encabezado'=>120,'titulo'=>500] as $field=>$max){
            if(!is_string($d[$field]??null) || trim($d[$field])==='' || mb_strlen($d[$field])>$max || preg_match('/[\x00-\x1f]/u',$d[$field])) throw new DomainException('Completá el encabezado y el título con una longitud válida.');
        }
        if($d['aprobado'] && empty($d['fondo']))throw new DomainException('Cargá la plantilla definitiva con las firmas antes de habilitar la emisión.');
        CertificadoPdf::render($d,'Usuario de Prueba',true);
        if($revision===0){
            try{$this->pdo->prepare('INSERT INTO '.static::CONFIG_TABLE.'(edicion_id,encabezado,titulo,fondo,aprobado,actualizado_por) VALUES(?,?,?,?,?,?)')->execute([$id,$d['encabezado'],$d['titulo'],$d['fondo'],$d['aprobado'],$admin]);}
            catch(PDOException $e){if($e->getCode()==='23000')throw new DomainException('La configuración cambió. Recargá la página.');throw $e;}
        } else {
            $q=$this->pdo->prepare('UPDATE '.static::CONFIG_TABLE.' SET encabezado=?,titulo=?,fondo=?,aprobado=?,revision=revision+1,actualizado_por=? WHERE edicion_id=? AND revision=?');
            $q->execute([$d['encabezado'],$d['titulo'],$d['fondo'],$d['aprobado'],$admin,$id,$revision]);
            if(!$q->rowCount())throw new DomainException('La configuración cambió. Recargá la página antes de guardar.');
        }
    }
    public function participantes(int $id): array
    {
        $q=$this->pdo->prepare("SELECT i.id,i.estado AS inscripcion_estado,p.nombre,p.apellido,p.email,c.id AS certificado_id,c.estado AS certificado_estado,c.archivo,c.emitido_en,ct.estado AS correo_estado,ct.ultimo_intento_en,
        EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto' AND ci.id=(SELECT MAX(ultimo.id) FROM correo_intentos ultimo WHERE ultimo.comunicacion_id=ct.id)) AS incierto
        FROM inscripciones i JOIN personas p ON p.id=i.persona_id LEFT JOIN certificados c ON c.inscripcion_id=i.id
        LEFT JOIN comunicaciones_transaccionales ct ON ct.id=(SELECT MAX(x.id) FROM comunicaciones_transaccionales x WHERE x.inscripcion_id=i.id AND x.tipo='certificado' AND x.clase_id IS NULL)
        WHERE i.edicion_id=? ORDER BY p.apellido,p.nombre,i.id");$q->execute([$id]);return $q->fetchAll();
    }
    public static function elegible(array $p): bool
    {
        return $p['inscripcion_estado']==='confirmada' && $p['certificado_estado']!=='anulado' && $p['correo_estado']!=='enviado' && !$p['incierto'];
    }
    public static function huella(array $p): string
    {
        return hash('sha256',json_encode([$p['id'],$p['nombre'],$p['apellido'],$p['email']],JSON_UNESCAPED_UNICODE));
    }
    public function enviar(int $edicion,int $inscripcion,int $admin,string $huella,int $revision,bool $confirmarReenvio=false): bool
    {
        $lock='ecap_certificado_'.$inscripcion;$q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new DomainException('Este certificado tiene un envío en proceso.');
        try {
            $e=$this->edicion($edicion);$cfg=$this->config($edicion);
            if(in_array($e['estado'],['borrador','cancelada'],true) || strtotime($e['fecha_fin']??$e['fecha_inicio'])>time())throw new DomainException('La edición debe haber terminado para emitir certificados.');
            if(!$cfg['aprobado'] || !$cfg['fondo'] || (int)$cfg['revision']!==$revision)throw new DomainException('La plantilla cambió o no está habilitada. Revisá la selección nuevamente.');
            $p=null;foreach($this->participantes($edicion) as $row)if((int)$row['id']===$inscripcion)$p=$row;
            if(!$p || !hash_equals(self::huella($p),$huella))throw new DomainException('Los datos de la persona cambiaron. Revisalos antes de enviar.');
            if($p['correo_estado']==='enviado')return false;
            if(!self::elegible($p) && !($confirmarReenvio && $admin>0 && $p['inscripcion_estado']==='confirmada' && $p['certificado_estado']==='emitido'))throw new DomainException('Esta inscripción no admite la emisión o requiere revisar un envío incierto.');
            if($p['ultimo_intento_en'] && strtotime($p['ultimo_intento_en'])>time()-60)throw new DomainException('Esperá un minuto antes de reintentar.');
            if(!filter_var($p['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('La dirección de correo no es válida.');
            if($p['certificado_estado']!=='emitido'){
                $name=trim($p['nombre'].' '.$p['apellido']);$pdf=CertificadoPdf::render($cfg,$name);$file=bin2hex(random_bytes(16)).'.pdf';$path=CertificadoPdf::file($file);
                if(file_put_contents($path,$pdf,LOCK_EX)!==strlen($pdf))throw new RuntimeException('No se pudo guardar el certificado.');chmod($path,0600);
                $this->pdo->beginTransaction();
                try {
                    if($p['certificado_id']){
                        $this->pdo->prepare("UPDATE certificados SET archivo=?,estado='emitido',emitido_en=NOW(),disponible_desde=NOW() WHERE id=? AND estado='pendiente'")->execute([$file,$p['certificado_id']]);$cert=(int)$p['certificado_id'];
                    } else {
                        $this->pdo->prepare("INSERT INTO certificados(inscripcion_id,codigo,archivo,estado,emitido_en,disponible_desde) VALUES(?,?,?,'emitido',NOW(),NOW())")->execute([$inscripcion,bin2hex(random_bytes(24)),$file]);$cert=(int)$this->pdo->lastInsertId();
                    }
                    $this->pdo->prepare('INSERT INTO certificado_emision(certificado_id,nombre,titulo,encabezado,plantilla_sha256,pdf_sha256,administrador_id) VALUES(?,?,?,?,?,?,?)')->execute([$cert,$name,$cfg['titulo'],$cfg['encabezado'],hash_file('sha256',CertificadoPdf::file($cfg['fondo'])),hash('sha256',$pdf),$admin]);
                    $this->pdo->prepare("INSERT INTO comunicaciones_transaccionales(inscripcion_id,clase_id,tipo,destinatario_email,estado) VALUES(?,NULL,'certificado',?,'pendiente')")->execute([$inscripcion,$p['email']]);
                    $this->pdo->commit();
                } catch(Throwable $ex){if($this->pdo->inTransaction())$this->pdo->rollBack();@unlink($path);throw $ex;}
            }
            $q=$this->pdo->prepare("SELECT c.archivo,m.nombre,m.titulo,m.pdf_sha256 FROM certificados c JOIN certificado_emision m ON m.certificado_id=c.id WHERE c.inscripcion_id=? AND c.estado='emitido'");$q->execute([$inscripcion]);$doc=$q->fetch();
            if(!$doc || !is_file(CertificadoPdf::file($doc['archivo'])) || !hash_equals($doc['pdf_sha256'],hash_file('sha256',CertificadoPdf::file($doc['archivo']))))throw new DomainException('El archivo emitido necesita revisión. No se regenerará automáticamente.');
            $q=$this->pdo->prepare("SELECT id FROM comunicaciones_transaccionales WHERE inscripcion_id=? AND tipo='certificado' AND clase_id IS NULL ORDER BY id DESC LIMIT 1");$q->execute([$inscripcion]);$ct=(int)$q->fetchColumn();
            if(!$ct)throw new DomainException('El registro de envío necesita revisión.');
            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare("INSERT INTO correo_intentos(comunicacion_id,origen,administrador_id) VALUES(?,'manual',?)")->execute([$ct,$admin]);$intento=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare("UPDATE comunicaciones_transaccionales SET estado='pendiente',intentos=intentos+1,ultimo_intento_en=NOW(),destinatario_email=? WHERE id=?")->execute([$p['email'],$ct]);$this->pdo->commit();
            }catch(Throwable $ex){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $ex;}
            try {($this->mail??new MailService())->enviarCertificado($p['email'],$doc['nombre'],$doc['titulo'],CertificadoPdf::file($doc['archivo']));}
            catch(Throwable $ex){$this->resultado($ct,$intento,'error');error_log('ECAP certificado correo #'.$ct.': '.$ex->getMessage());throw new DomainException('El certificado fue emitido, pero el servidor no confirmó el correo. Podés reintentar desde Certificados.');}
            $this->resultado($ct,$intento,'enviado');return true;
        } finally {$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    private function resultado(int $ct,int $intento,string $estado): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE correo_intentos SET resultado=?,finalizado_en=NOW() WHERE id=?')->execute([$estado,$intento]);
            $this->pdo->prepare("UPDATE comunicaciones_transaccionales SET estado=?,enviado_en=IF(?=1,NOW(),enviado_en),error_detalle=? WHERE id=?")->execute([$estado,(int)($estado==='enviado'),$estado==='error'?'El servidor no confirmó el envío del certificado.':null,$ct]);$this->pdo->commit();
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
