<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\SMTP;

/** Registra metadatos, nunca credenciales, comandos SMTP ni cuerpos de mensajes. */
final class CorreoTraza
{
    public string $fase='preparando';
    public ?int $codigo=null;
    public bool $aceptado=false;
    public ?string $detalle=null;
    private bool $activo=true;
    public function __construct(private PDO $pdo, private int $intento, public string $messageId)
    {
        $this->evento('preparando');
        register_shutdown_function(function():void {
            if (!$this->activo) return;
            try {$this->evento('interrumpido', $this->codigo, 'El proceso terminó sin cerrar el intento. Última etapa: '.$this->fase);}
            catch(Throwable $e){error_log('ECAP traza interrumpida intento #'.$this->intento);}
        });
    }
    public function evento(string $fase,?int $codigo=null,?string $detalle=null):void
    {
        $this->pdo->prepare('INSERT INTO correo_eventos(intento_id,fase,smtp_codigo,message_id,detalle) VALUES(?,?,?,?,?)')->execute([$this->intento,$fase,$codigo,$this->messageId,$detalle]);
        $this->fase=$fase;$this->codigo=$codigo;$this->detalle=$detalle;
        if($fase==='aceptado')$this->aceptado=true;
    }
    public function cerrar():void{$this->activo=false;}
    public function ambiguo():bool{return !$this->aceptado && in_array($this->fase,['transmitiendo','esperando_aceptacion'],true) && (!$this->codigo || $this->codigo<400);}
    public function diagnostico():string
    {
        $fase=match($this->fase){'preparando'=>'preparación del mensaje','conectando'=>'conexión con el servidor','autenticando'=>'autenticación SMTP','destinatario'=>'validación del destinatario','transmitiendo'=>'transferencia del mensaje','esperando_aceptacion'=>'espera de aceptación final',default=>$this->fase};
        return 'No se completó el envío. Etapa: '.$fase.'.'.($this->codigo?' Respuesta SMTP: '.$this->codigo.'.':' Sin respuesta SMTP registrada.').($this->detalle?' '.$this->detalle:'');
    }
}

final class CorreoSMTP extends SMTP
{
    public function __construct(private CorreoTraza $traza){}
    public function connect($host,$port=null,$timeout=30,$options=[])
    {
        $this->traza->evento('conectando');
        return parent::connect($host,$port,$timeout,$options);
    }
    protected function sendCommand($command,$commandstring,$expect)
    {
        $fase=match($command){'AUTH','Username','Password','User & Password'=>'autenticando','RCPT TO'=>'destinatario','DATA'=>'transmitiendo','DATA END'=>'esperando_aceptacion',default=>null};
        if($fase)$this->traza->evento($fase);
        $ok=parent::sendCommand($command,$commandstring,$expect);
        if($fase){
            $code=preg_match('/^([0-9]{3})/',$this->getLastReply(),$m)?(int)$m[1]:null;
            $detalle=null;
            $reply=strtolower($this->getLastReply());
            if($code && $code>=400){
                $detalle=str_contains($reply,'ratelimit') || str_contains($reply,'rate limit')?'El proveedor alcanzó un límite de envíos. Revisar la cuota antes de reintentar.':match($code){535=>'El servidor rechazó la autenticación.',550,551,553=>'El servidor rechazó el destinatario o el mensaje.',default=>'El servidor rechazó esta etapa del envío.'};
            }
            $this->traza->evento($command==='DATA END' && $ok?'aceptado':$fase,$code,$detalle);
        }
        return $ok;
    }
}
