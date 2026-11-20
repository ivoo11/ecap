<?php
declare(strict_types=1);
require_once __DIR__.'/CertificadoDocenteService.php';

/** Cola persistente en el mismo almacenamiento privado que los PDF.
 * La sesión sólo contiene borradores; una tanda confirmada sobrevive al navegador.
 */
final class CertificadoCola
{
    public function __construct(private string $dir) {
        if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))throw new RuntimeException('No se pudo preparar la cola.');
    }
    public static function production():self {return new self(CertificadoPdf::directory().'/lotes');}
    private function path(string $token):string {
        if(!preg_match('/^[a-f0-9]{48}$/D',$token))throw new DomainException('Tanda inválida.');
        return $this->dir.'/'.$token.'.json';
    }
    public function leer(string $token):?array {
        $path=$this->path($token);
        if(!is_file($path))return null;
        return json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    }
    private function guardar(string $token,array $job):void {
        $path=$this->path($token);$tmp=tempnam($this->dir,'.escritura-');
        if($tmp===false)throw new RuntimeException('No se pudo guardar la tanda.');
        try {
            chmod($tmp,0600);$data=json_encode($job,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            if(file_put_contents($tmp,$data)!==strlen($data) || !rename($tmp,$path))throw new RuntimeException('No se pudo guardar la tanda.');
        }finally{if(is_file($tmp))unlink($tmp);}
    }
    public function iniciar(string $token,array $selection,int $admin):array {
        $path=$this->path($token);$lock=fopen($path.'.lock','c');
        if(!$lock)throw new RuntimeException('No se pudo reservar la tanda.');
        try {
            flock($lock,LOCK_EX);
            if($existing=$this->leer($token))return $existing;
            if($admin<1 || empty($selection['personas']) || count($selection['personas'])>500 || $selection['vence']<time())throw new DomainException('La selección venció. Volvé a revisarla.');
            $selection['admin']=$admin;$selection['token']=$token;$selection['creado']=time();$selection['actualizado']=time();
            $selection['items']=[];
            foreach($selection['personas'] as $id=>$hash)$selection['items'][$id]=['estado'=>'pendiente','mensaje'=>'Pendiente'];
            $this->guardar($token,$selection);return $selection;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function listado(int $edicion,string $tipo):array {
        $jobs=[];
        foreach(glob($this->dir.'/*.json') as $path){
            $job=$this->leer(basename($path,'.json'));
            if($job && $job['edicion']===$edicion && $job['destinatarios']===$tipo)$jobs[]=$job;
        }
        usort($jobs,fn($a,$b)=>$b['creado']<=>$a['creado']);return $jobs;
    }
    public static function resumen(array $job):array {
        $counts=array_fill_keys(['pendiente','procesando','enviado','revision'],0);
        foreach($job['items'] as $item)$counts[$item['estado']]++;
        return ['token'=>$job['token'],'total'=>count($job['items']),'conteos'=>$counts,'items'=>$job['items'],'creado'=>$job['creado']];
    }
    /** Una sola ejecución por tanda, independiente de sesiones y peticiones HTTP.
     * Un intento interrumpido no se repite automáticamente: se concilia o revisa.
     */
    public function procesar(callable $send,callable $accepted,int $seconds=3600):void {
        $deadline=microtime(true)+$seconds;
        foreach(glob($this->dir.'/*.json') as $path){
            if(microtime(true)>=$deadline)return;
            $token=basename($path,'.json');$lock=fopen($path.'.lock','c');
            if(!$lock)continue;
            if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);continue;}
            try {
                $job=$this->leer($token);if(!$job)continue;
                foreach($job['items'] as $id=>$item){
                    if($item['estado']==='procesando'){
                        $ok=$accepted($job,(int)$id);
                        $job['items'][$id]=['estado'=>$ok?'enviado':'revision','mensaje'=>$ok?'Correo enviado':'El intento se interrumpió. Revisá Control de correos antes de reenviar.'];
                        $this->guardar($token,$job);continue;
                    }
                    if($item['estado']!=='pendiente')continue;
                    if(microtime(true)>=$deadline)return;
                    $job['items'][$id]=['estado'=>'procesando','mensaje'=>'Enviando'];$job['actualizado']=time();$this->guardar($token,$job);
                    try {
                        $sent=$send($job,(int)$id);
                        $job['items'][$id]=['estado'=>'enviado','mensaje'=>$sent?'Correo enviado':'Ya estaba enviado'];
                    }catch(Throwable $e){
                        error_log('ECAP tanda '.$token.' destinatario '.$id.': '.$e->getMessage());
                        $job['items'][$id]=['estado'=>'revision','mensaje'=>$e instanceof DomainException?$e->getMessage():'No se completó el intento. Revisá Control de correos antes de reintentar.'];
                    }
                    $job['actualizado']=time();$this->guardar($token,$job);
                }
            }finally{flock($lock,LOCK_UN);fclose($lock);}
        }
    }
    public function ejecutar(PDO $pdo,int $seconds=3600):void {
        $services=['docentes'=>new CertificadoDocenteService($pdo),'participantes'=>new CertificadoService($pdo)];
        $this->procesar(function($job,$id)use($services){
            $svc=$services[$job['destinatarios']];
            if($job['destinatarios']==='docentes')return $svc->enviar($job['edicion'],$id,$job['admin'],$job['personas'][$id],$job['revision'],false,$job['roles'][$id]);
            return $svc->enviar($job['edicion'],$id,$job['admin'],$job['personas'][$id],$job['revision']);
        },function($job,$id)use($services){
            foreach($services[$job['destinatarios']]->participantes($job['edicion']) as $p)if((int)$p['id']===$id)return $p['correo_estado']==='enviado';
            return false;
        },$seconds);
    }
}
