<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/services/Novedades.php';
function check(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo "OK $name\n";}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE personas(id INTEGER,estado TEXT,email TEXT);CREATE TABLE consentimientos(id INTEGER,persona_id INTEGER,tipo TEXT,aceptado INTEGER);CREATE TABLE novedades_bajas(email TEXT);
INSERT INTO personas VALUES(1,'activo','ANA@example.test'),(2,'activo','ana@example.test'),(3,'activo','baja@example.test'),(4,'activo','no@example.test'),(5,'inactivo','inactivo@example.test'),(6,'activo','sin@example.test'),(7,'activo','erroneo'),(8,'activo','revocado@example.test');
INSERT INTO consentimientos VALUES(1,1,'email_marketing',1),(2,2,'email_marketing',1),(3,3,'email_marketing',1),(4,4,'email_marketing',0),(5,5,'email_marketing',1),(6,7,'email_marketing',1),(7,8,'email_marketing',1),(8,8,'email_marketing',0);
INSERT INTO novedades_bajas VALUES('baja@example.test');");
$svc=new Novedades($db);$r=$svc->elegibles();
check(count($r)===1&&$r[0]['email']==='ana@example.test','Último consentimiento, deduplicación, bajas, inactivos e inválidos');
$db->exec("INSERT INTO consentimientos VALUES(9,1,'email_marketing',0),(10,2,'email_marketing',0)");
check($svc->elegibles()===[],'Revocación excluye futuros destinatarios');
$e=['fecha_inicio'=>'2026-11-20 14:00:00','slug'=>'control-prevencion','imagen_portada'=>'placa.png','titulo'=>'Control <script>alert(1)</script>','modalidad'=>'online'];
$html=NovedadPlantilla::html($e,'Una invitación <script>alert(2)</script> con "comillas".');
check(!str_contains($html,'<script>'),'Textos administrativos escapados');
check(str_contains($html,'20 de noviembre · 14:00 hs'),'Fecha legible fuera de la imagen');
check(str_contains($html,'align="center"><tr><td align="center"'),'Botón centrado');
check(str_contains($html,'__BAJA_TOKEN__'),'Baja individual prevista');
check(str_contains($html,'Nueva actividad para vos'),'Contexto institucional');
file_put_contents('/tmp/ecap-novedad-test.html',$html);

check(!str_contains($html,'background-image:')&&!str_contains($html,'background-size:'),'Placa sin fondo que pueda estirarse');
check(str_contains($html,'width:100%;max-width:540px;height:auto'),'Imagen fluida con proporción natural sin depender de media queries');
check(!str_contains($html,'font-size:78px')&&!str_contains($html,'height:330px'),'Sin fecha superpuesta ni altura fija');
