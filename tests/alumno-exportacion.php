<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/services/AlumnoExportacion.php';
function ok(bool $test,string $message):void{if(!$test)throw new RuntimeException($message);echo "OK $message\n";}
foreach([[],['password'],['nombre',[]],null] as $bad){try{AlumnoExportacion::columnas($bad);throw new RuntimeException('Selección inválida aceptada');}catch(DomainException $e){}}
ok(true,'Solo columnas permitidas');
$cols=AlumnoExportacion::columnas(['nombre','dni','email']);
$rows=[['nombre'=>'María & José <Prueba>','dni'=>'00123456','email'=>'=HYPERLINK("https://example.test")']];
$data=AlumnoExportacion::xlsx($cols,$rows);$file=tempnam(sys_get_temp_dir(),'export_test_');file_put_contents($file,$data);
$z=new ZipArchive();$z->open($file);$xml=$z->getFromName('xl/worksheets/sheet1.xml');
ok(str_contains($xml,'00123456'),'DNI conserva ceros');ok(!str_contains($xml,'<f>') && str_contains($xml,'t="inlineStr"'),'Texto no ejecutable como fórmula');
ok(str_contains($xml,'María &amp; José &lt;Prueba&gt;'),'Caracteres especiales preservados');
ok(!str_contains($xml,'Teléfono'),'Campos no seleccionados excluidos');
for($i=0;$i<$z->numFiles;$i++){libxml_use_internal_errors(true);ok(simplexml_load_string($z->getFromIndex($i))!==false,'XML válido: '.$z->getNameIndex($i));}
$z->close();unlink($file);
$cols=array_keys(AlumnoExportacion::CAMPOS);$rows=[];
for($i=0;$i<8;$i++){$row=array_fill_keys($cols,'');$row['nombre']='María Bárbara de Prueba';$row['apellido']='Fernández de los Campos';$row['dni']='00123456';$row['email']='persona.de.prueba@example.test';$row['universidad']='Universidad de Prueba';$row['ambitos']='Docencia / ámbito académico; Ejercicio independiente';$row['creado_en']='29/10/2026 10:00';$row['inscripciones']='2';$rows[]=$row;}
if(getenv('ECAP_EXPORT_TEST_DIR')){$dir=getenv('ECAP_EXPORT_TEST_DIR');file_put_contents($dir.'/personas.xlsx',AlumnoExportacion::xlsx($cols,$rows));file_put_contents($dir.'/personas.pdf',AlumnoExportacion::pdf($cols,$rows));echo "Muestras ficticias generadas\n";}
