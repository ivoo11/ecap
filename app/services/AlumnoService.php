<?php
declare(strict_types=1);

final class AlumnoService
{
    public function __construct(private PDO $pdo) {}
    public function obtener(int $id): array
    {
        $q=$this->pdo->prepare('SELECT id,nombre,apellido,dni,email,telefono,estado,actualizado_en FROM personas WHERE id=?');$q->execute([$id]);
        return $q->fetch() ?: throw new DomainException('La persona no existe.');
    }
    public static function version(array $p): string
    {
        return hash('sha256',json_encode(array_intersect_key($p,array_flip(['id','nombre','apellido','dni','email','telefono','estado','actualizado_en'])),JSON_UNESCAPED_UNICODE));
    }
    public function protegida(int $id): bool
    {
        $q=$this->pdo->prepare("SELECT COUNT(*) FROM usuarios u JOIN usuario_roles ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE u.persona_id=? AND r.codigo IN ('administrador','superadmin') AND r.activo=1");$q->execute([$id]);return (int)$q->fetchColumn()>0;
    }
    public function guardar(int $id,array $d,string $version,int $actor): bool
    {
        foreach(['nombre'=>100,'apellido'=>100,'email'=>190,'telefono'=>50,'dni'=>20] as $field=>$limit){
            if(!is_string($d[$field]??null) || mb_strlen($d[$field])>$limit || preg_match('/[\x00-\x1f\x7f]/u',$d[$field]))throw new DomainException('Revisá los datos ingresados.');
            $d[$field]=trim($d[$field]);
        }
        if($d['nombre']==='' || $d['apellido']==='')throw new DomainException('Completá nombre y apellido.');
        if(!preg_match('/^[0-9]{7,8}$/D',$d['dni']))throw new DomainException('Ingresá un DNI de 7 u 8 dígitos, sin puntos.');
        if(!filter_var($d['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('Ingresá un correo válido.');
        $d['email']=mb_strtolower($d['email']);$d['telefono']=$d['telefono']===''?null:$d['telefono'];
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare("SELECT r.codigo FROM usuarios u JOIN personas p ON p.id=u.persona_id JOIN usuario_roles ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE u.id=? AND u.estado='activo' AND p.estado='activo' AND r.activo=1");$q->execute([$actor]);$roles=$q->fetchAll(PDO::FETCH_COLUMN);
            if(!array_intersect(['administrador','superadmin'],$roles))throw new DomainException('No tenés permiso para editar estos datos.');
            if($this->protegida($id) && !in_array('superadmin',$roles,true))throw new DomainException('Este perfil también tiene acceso administrativo. Debe editarlo un superadministrador.');
            $q=$this->pdo->prepare('SELECT id,nombre,apellido,dni,email,telefono,estado,actualizado_en FROM personas WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
            if(!$old || !hash_equals(self::version($old),$version))throw new DomainException('Los datos cambiaron desde que abriste la ficha. Recargá la página antes de guardar.');
            $changes=[];foreach($d as $key=>$value)if(array_key_exists($key,$old) && $old[$key]!==$value)$changes[$key]=['antes'=>$old[$key],'despues'=>$value];
            if(!$changes){$this->pdo->rollBack();return false;}
            if(isset($changes['email'])){
                $q=$this->pdo->prepare('SELECT id FROM personas WHERE email=? AND id<>? LIMIT 1 FOR UPDATE');$q->execute([$d['email'],$id]);
                if($q->fetchColumn())throw new DomainException('Ese correo ya está asociado a otra persona. Revisá la dirección antes de guardar.');
            }
            if(isset($changes['dni'])){
                $q=$this->pdo->prepare('SELECT id FROM personas WHERE dni=? AND id<>? LIMIT 1');$q->execute([$d['dni'],$id]);
                if($q->fetchColumn())throw new DomainException('Ese DNI ya pertenece a otra persona.');
            }
            $this->pdo->prepare('UPDATE personas SET nombre=?,apellido=?,dni=?,email=?,telefono=? WHERE id=?')->execute([$d['nombre'],$d['apellido'],$d['dni'],$d['email'],$d['telefono'],$id]);
            if(isset($changes['email']) || isset($changes['dni']))$this->pdo->prepare('UPDATE verificaciones_email SET expira_en=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE persona_id=? AND verificado_en IS NULL AND expira_en>=NOW()')->execute([$id]);
            $this->pdo->prepare("INSERT INTO auditoria(usuario_id,accion,entidad,entidad_id,detalle) VALUES(?,'actualizar_datos','personas',?,?)")->execute([$actor,$id,json_encode(['cambios'=>$changes],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();return true;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('El DNI ya está registrado. Revisá los datos.');throw $e;}
    }
}
