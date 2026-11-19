<?php
declare(strict_types=1);
putenv('ECAP_ENV=local');
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/services/InscripcionService.php';
function verify(bool $v,string $m):void {if(!$v)throw new RuntimeException($m);echo "OK $m\n";}
$db=new PDO('mysql:unix_socket=/tmp/ecap-altas-db/mysql.sock','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ecap_alta_test');$db->exec('CREATE DATABASE ecap_alta_test');$db->exec('USE ecap_alta_test');
$db->exec("CREATE TABLE personas(id INT PRIMARY KEY,estado VARCHAR(20),nombre TEXT,apellido TEXT,email TEXT) ENGINE=InnoDB;
CREATE TABLE actividades(id INT PRIMARY KEY,titulo TEXT) ENGINE=InnoDB;
CREATE TABLE ediciones(id INT PRIMARY KEY,actividad_id INT,cupo_maximo INT NULL,estado VARCHAR(30),fecha_inicio DATETIME,fecha_fin DATETIME,modalidad VARCHAR(20),zoom_url TEXT,zoom_meeting_id TEXT,zoom_passcode TEXT) ENGINE=InnoDB;
CREATE TABLE inscripciones(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,persona_id INT,edicion_id INT,estado VARCHAR(20),inscripto_en DATETIME,cancelado_en DATETIME,UNIQUE(persona_id,edicion_id)) ENGINE=InnoDB;
CREATE TABLE comunicaciones_transaccionales(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,inscripcion_id BIGINT,clase_id INT NULL,tipo VARCHAR(80),destinatario_email TEXT,estado VARCHAR(20),intentos INT,ultimo_intento_en DATETIME,enviado_en DATETIME,creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,error_detalle TEXT) ENGINE=InnoDB;");
$db->exec(file_get_contents(__DIR__.'/../database/migrations/20261019_control_correos.sql'));
$db->exec(file_get_contents(__DIR__.'/../database/migrations/20261022_correo_eventos.sql'));
$db->exec("INSERT INTO actividades VALUES(1,'Prueba local');INSERT INTO ediciones VALUES(1,1,1,'inscripcion_abierta','2099-01-01',NULL,'online','https://example.test/reunion','123','456');");
for($i=1;$i<=6;$i++)$db->exec("INSERT INTO personas VALUES($i,'activo','Persona','Prueba','persona$i@example.test')");
$s=new InscripcionService($db);
$s->inscribirPersonaDesdeAdmin(1,1,1);
verify((int)$db->query('SELECT cupo_maximo FROM ediciones WHERE id=1')->fetchColumn()===1,'Usa vacante existente sin aumentar cupo');
$id=$s->inscribirPersonaDesdeAdmin(2,1,1);
verify((int)$db->query('SELECT cupo_maximo FROM ediciones WHERE id=1')->fetchColumn()===2,'Amplía cupo completo exactamente un lugar');
verify((int)$db->query("SELECT COUNT(*) FROM inscripciones WHERE estado='confirmada'")->fetchColumn()===2,'El lugar agregado queda ocupado');
verify((int)$db->query("SELECT COUNT(*) FROM comunicaciones_transaccionales WHERE estado='enviado' AND tipo='confirmacion_inscripcion'")->fetchColumn()===2,'Confirmaciones capturadas inmediatamente, sin SMTP real');
try{$s->inscribirPersonaDesdeAdmin(2,1,1);throw new LogicException('Duplicado aceptado');}catch(RuntimeException $e){}
verify((int)$db->query('SELECT cupo_maximo FROM ediciones WHERE id=1')->fetchColumn()===2,'Duplicado no aumenta cupo ni reenvía');
$db->exec("CREATE TRIGGER fallo_alta BEFORE INSERT ON inscripciones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo simulado'");
try{$s->inscribirPersonaDesdeAdmin(3,1,1);throw new LogicException('Debía fallar');}catch(PDOException $e){}
verify((int)$db->query('SELECT cupo_maximo FROM ediciones WHERE id=1')->fetchColumn()===2,'Fallo de inscripción revierte aumento de cupo');
$db->exec('DROP TRIGGER fallo_alta');
$db->exec('UPDATE ediciones SET cupo_maximo=NULL WHERE id=1');$s->inscribirPersonaDesdeAdmin(3,1,1);
verify($db->query('SELECT cupo_maximo FROM ediciones WHERE id=1')->fetchColumn()===null,'Cupo ilimitado se conserva');
$db->exec("UPDATE ediciones SET estado='finalizada' WHERE id=1");
try{$s->inscribirPersonaDesdeAdmin(4,1,1);throw new LogicException('Finalizada aceptada');}catch(RuntimeException $e){}
verify((int)$db->query('SELECT COUNT(*) FROM inscripciones')->fetchColumn()===3,'Edición finalizada rechazada');
$db->exec('DROP DATABASE ecap_alta_test');
