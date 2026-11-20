<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--run',$argv,true)){fwrite(STDERR,"Usar --run para procesar las novedades pendientes.\n");exit(2);}
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/config/database.php';
require_once __DIR__.'/../app/services/Novedades.php';
// El cron existente también recupera tandas de certificados sin depender del navegador.
require_once __DIR__.'/../app/services/CertificadoCola.php';
try{CertificadoCola::production()->ejecutar($pdo,120);}catch(Throwable $e){error_log('ECAP certificados respaldo: '.$e->getMessage());}
try{echo (new Novedades($pdo))->procesar()." novedades enviadas.\n";}catch(Throwable $e){fwrite(STDERR,"No se completó el envío. Revisar el panel de novedades.\n");exit(1);}
