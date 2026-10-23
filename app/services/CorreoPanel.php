<?php
declare(strict_types=1);

/** Consultas de lectura: estado actual separado de ejecuciones históricas. */
final class CorreoPanel
{
    public function __construct(private PDO $pdo) {}

    public function recordatoriosHoy(?int $edicion=null): array
    {
        $sql="SELECT c.id,c.edicion_id,c.fecha_inicio,a.titulo,COUNT(i.id) AS destinatarios,
        SUM(CASE WHEN ct.estado='enviado' THEN 1 ELSE 0 END) AS aceptados,
        SUM(CASE WHEN ct.estado='error' THEN 1 ELSE 0 END) AS errores,
        SUM(CASE WHEN ct.estado='pendiente' AND EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='incierto') THEN 1 ELSE 0 END) AS revision,
        MAX(ct.enviado_en) AS ultimo_aceptado,
        SUM(CASE WHEN ct.estado='enviado' AND EXISTS(SELECT 1 FROM correo_intentos ci WHERE ci.comunicacion_id=ct.id AND ci.resultado='enviado' AND ci.origen IN ('manual','recuperacion')) THEN 1 ELSE 0 END) AS recuperados
        FROM clases c JOIN ediciones e ON e.id=c.edicion_id JOIN actividades a ON a.id=e.actividad_id
        LEFT JOIN inscripciones i ON i.edicion_id=e.id AND i.estado='confirmada'
        LEFT JOIN comunicaciones_transaccionales ct ON ct.id=(SELECT MAX(x.id) FROM comunicaciones_transaccionales x WHERE x.inscripcion_id=i.id AND x.clase_id=c.id AND x.tipo='recordatorio_clase')
        WHERE DATE(c.fecha_inicio)=CURDATE() AND e.estado NOT IN ('borrador','cancelada')";
        $params=[];
        if($edicion){$sql.=' AND e.id=?';$params[]=$edicion;}
        $sql.=' GROUP BY c.id,c.edicion_id,c.fecha_inicio,a.titulo HAVING COUNT(i.id)>0 ORDER BY c.fecha_inicio,c.id';
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetchAll();
    }

    public function contexto(array $inscripciones): array
    {
        if(!$inscripciones)return [];
        $ids=array_values(array_unique(array_map('intval',$inscripciones)));
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->pdo->prepare("SELECT i.id,i.estado,MAX(ct.enviado_en) AS recordatorio_aceptado
        FROM inscripciones i LEFT JOIN comunicaciones_transaccionales ct ON ct.inscripcion_id=i.id AND ct.tipo='recordatorio_clase' AND ct.estado='enviado'
        AND EXISTS(SELECT 1 FROM clases c WHERE c.id=ct.clase_id AND DATE(c.fecha_inicio)=CURDATE())
        WHERE i.id IN ($marks) GROUP BY i.id,i.estado");
        $q->execute($ids);return array_column($q->fetchAll(),null,'id');
    }
}
