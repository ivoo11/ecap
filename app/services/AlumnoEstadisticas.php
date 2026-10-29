<?php
declare(strict_types=1);

final class AlumnoEstadisticas
{
    public static function filtros(array $input): array
    {
        $f=['q'=>'','desde'=>'','hasta'=>'','tipo_fecha'=>'alta','actividad'=>0];
        if(is_string($input['q']??null))$f['q']=mb_substr(trim($input['q']),0,100);
        foreach(['desde','hasta'] as $key){
            $value=$input[$key]??'';
            if(!is_string($value))throw new DomainException('Revisá las fechas del filtro.');
            if($value!==''){
                $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$date || $date->format('Y-m-d')!==$value)throw new DomainException('Ingresá fechas válidas.');
            }
            $f[$key]=$value;
        }
        if($f['desde']!=='' && $f['hasta']!=='' && $f['desde']>$f['hasta'])throw new DomainException('La fecha desde debe ser anterior o igual a la fecha hasta.');
        if(($input['tipo_fecha']??'alta')==='inscripcion')$f['tipo_fecha']='inscripcion';
        $f['actividad']=max(0,(int)(is_scalar($input['actividad']??0)?($input['actividad']??0):0));
        return $f;
    }

    public static function consulta(array $f): array
    {
        $where='WHERE 1=1';$params=[];
        if($f['q']!==''){
            $where.=" AND (LOCATE(?,CONCAT(p.nombre,' ',p.apellido))>0 OR LOCATE(?,p.dni)>0 OR LOCATE(?,p.email)>0)";
            array_push($params,$f['q'],$f['q'],$f['q']);
        }
        $dates=function(string $column)use($f,&$params):string{
            $sql='';
            if($f['desde']!==''){$sql.=" AND $column>=?";$params[]=$f['desde'].' 00:00:00';}
            if($f['hasta']!==''){$sql.=" AND $column<?";$params[]=(new DateTimeImmutable($f['hasta']))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
            return $sql;
        };
        if($f['tipo_fecha']==='alta')$where.=$dates('p.creado_en');
        if($f['actividad'] || ($f['tipo_fecha']==='inscripcion' && ($f['desde']!=='' || $f['hasta']!==''))){
            $where.=' AND EXISTS (SELECT 1 FROM inscripciones i JOIN ediciones e ON e.id=i.edicion_id WHERE i.persona_id=p.id';
            if($f['actividad']){$where.=' AND e.actividad_id=?';$params[]=$f['actividad'];}
            if($f['tipo_fecha']==='inscripcion')$where.=$dates('i.inscripto_en');
            $where.=')';
        }
        return [$where,$params];
    }

    public static function resumen(PDO $pdo, ?DateTimeImmutable $now=null): array
    {
        $now=($now??new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Buenos_Aires')))->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
        $monday=$now->modify('monday this week')->setTime(0,0);
        $weeks=[];
        for($n=7;$n>=0;$n--){
            $start=$monday->modify("-$n weeks");$end=$start->modify('+1 week');
            $counts=[];
            foreach(['personas'=>'creado_en','inscripciones'=>'inscripto_en'] as $table=>$column){
                $q=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column>=? AND $column<? AND $column<=?");
                $q->execute([$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s')]);$counts[$table]=(int)$q->fetchColumn();
            }
            $weeks[]=['desde'=>$start->format('Y-m-d'),'hasta'=>$end->modify('-1 day')->format('Y-m-d'),'label'=>$start->format('d/m'),'actual'=>$n===0]+$counts;
        }
        return ['total'=>(int)$pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn(),'semanas'=>$weeks];
    }
}
