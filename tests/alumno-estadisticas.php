<?php
declare(strict_types=1);
require_once __DIR__.'/../app/services/AlumnoEstadisticas.php';
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "OK $name\n";}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('LOCATE',fn($needle,$text)=>($p=strpos($text,$needle))===false?0:$p+1,2);
$db->sqliteCreateFunction('CONCAT',fn(...$v)=>implode('',$v));
$db->exec("CREATE TABLE personas(id INTEGER,nombre TEXT,apellido TEXT,dni TEXT,email TEXT,creado_en TEXT);
CREATE TABLE inscripciones(id INTEGER,persona_id INTEGER,edicion_id INTEGER,inscripto_en TEXT);
CREATE TABLE ediciones(id INTEGER,actividad_id INTEGER);
INSERT INTO personas VALUES(1,'Ana','Prueba','10000001','ana@example.test','2026-10-26 00:00:00'),(2,'Luis','Prueba','10000002','luis@example.test','2026-10-25 23:59:59'),(3,'Sol','Prueba','10000003','sol@example.test','2026-10-28 08:00:00');
INSERT INTO ediciones VALUES(1,10),(2,20);
INSERT INTO inscripciones VALUES(1,1,1,'2026-10-26 00:00:00'),(2,1,2,'2026-10-27 10:00:00'),(3,2,1,'2026-10-25 23:59:59');");
function ids(PDO $db,array $input):array{[$sql,$params]=AlumnoEstadisticas::consulta(AlumnoEstadisticas::filtros($input));$q=$db->prepare('SELECT p.id FROM personas p '.$sql.' ORDER BY p.id');$q->execute($params);return $q->fetchAll(PDO::FETCH_COLUMN);}
check(ids($db,['desde'=>'2026-10-26','hasta'=>'2026-10-26'])===[1],'Límites de alta inclusivos');
check(ids($db,['tipo_fecha'=>'inscripcion','desde'=>'2026-10-26'])===[1],'Una persona con varias inscripciones no se duplica');
check(ids($db,['tipo_fecha'=>'inscripcion','desde'=>'2026-10-27','actividad'=>10])===[],'Actividad y fecha corresponden a la misma inscripción');
check(ids($db,['actividad'=>20,'q'=>'Ana'])===[1],'Búsqueda y actividad combinadas');
check(ids($db,['q'=>'%'])===[],'Búsqueda literal sin comodines');
foreach([['desde'=>'2026-02-30'],['desde'=>'2026-10-28','hasta'=>'2026-10-01'],['hasta'=>[]]] as $bad){try{AlumnoEstadisticas::filtros($bad);throw new RuntimeException('Fecha inválida aceptada');}catch(DomainException $e){}}
check(true,'Fechas inválidas rechazadas');
$r=AlumnoEstadisticas::resumen($db,new DateTimeImmutable('2026-10-28 12:00:00',new DateTimeZone('America/Argentina/Buenos_Aires')));
check($r['total']===3 && $r['semanas'][7]['personas']===2 && $r['semanas'][7]['inscripciones']===2 && $r['semanas'][6]['personas']===1,'Conteos semanales independientes');
check(count($r['semanas'])===8 && $r['semanas'][7]['desde']==='2026-10-26','Ocho semanas desde el lunes');
