<?php
declare(strict_types=1);
// Base efímera propia, sin datos reales y sin red SMTP.
putenv('ECAP_ENV=local');
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../app/services/Novedades.php';
function ok(bool $v,string $n):void{if(!$v)throw new RuntimeException($n);echo "OK $n\n";}
$db=new PDO('mysql:unix_socket=/tmp/ecap-novedades-db57/mysql.sock','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ecap_novedades_test');$db->exec('CREATE DATABASE ecap_novedades_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$db->exec('USE ecap_novedades_test');
$db->exec("CREATE TABLE personas(id INT PRIMARY KEY,nombre TEXT,apellido TEXT,estado VARCHAR(20),email VARCHAR(190));CREATE TABLE consentimientos(id INT AUTO_INCREMENT PRIMARY KEY,persona_id INT,tipo VARCHAR(30),aceptado INT,origen VARCHAR(100));CREATE TABLE actividades(id INT PRIMARY KEY,titulo TEXT,slug TEXT,imagen_portada TEXT,estado VARCHAR(30));CREATE TABLE ediciones(id INT PRIMARY KEY,actividad_id INT,fecha_inicio DATETIME,modalidad VARCHAR(20),estado VARCHAR(30),cupo_maximo INT,inscripcion_desde DATETIME,inscripcion_hasta DATETIME);CREATE TABLE inscripciones(edicion_id INT,estado VARCHAR(30));");
$db->exec(file_get_contents(__DIR__.'/../database/migrations/20261116_novedades.sql'));
$db->exec("INSERT INTO personas VALUES(1,'Ana','Prueba','activo','ana@example.test'),(2,'Sol','Prueba','activo','sol@example.test');INSERT INTO consentimientos(persona_id,tipo,aceptado) VALUES(1,'email_marketing',1),(2,'email_marketing',1);INSERT INTO actividades VALUES(1,'Jornada de prueba','prueba',NULL,'publicada');INSERT INTO ediciones VALUES(1,1,'2099-11-20 14:00:00','online','inscripcion_abierta',100,NULL,NULL);");
$s=new Novedades($db);$s->guardar(1,'Nueva actividad','Invitación de prueba, sin envíos reales.',1);$c=$s->campana(1);$h=$s->huella($c,$s->elegibles());
try{$s->iniciar(1,'incorrecta');throw new RuntimeException('Aceptó una vista previa modificada');}catch(DomainException $e){ok(true,'Rechaza confirmación obsoleta');}
$s->iniciar(1,$h);ok(count($s->resultados((int)$c['id']))===2,'Cola congelada con 2 destinatarios');
try{$s->iniciar(1,$h);throw new RuntimeException('Duplicó');}catch(DomainException $e){ok(true,'Impide volver a anunciar la edición');}
$rows=$s->resultados((int)$c['id']);$s->baja($rows[1]['token']);ok(count($s->elegibles())===1,'Baja revoca consentimiento');
ok($s->procesar((int)$c['id'])===1,'Captura un correo local y excluye la baja pendiente');
ok($s->procesar((int)$c['id'])===0,'Segunda ejecución no duplica envíos');
$rows=$s->resultados((int)$c['id']);ok($rows[0]['estado']==='enviado'&&$rows[1]['estado']==='omitido','Resultados individuales persistidos');
$s->reintentar((int)$c['id'],(int)$rows[0]['id']);ok($s->resultados((int)$c['id'])[0]['estado']==='enviado','No permite reintentar un envío exitoso');
$db->exec('DROP DATABASE ecap_novedades_test');
