<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--run',$argv,true)){fwrite(STDERR,"Usar --run para procesar una tanda de hasta 5 novedades.\n");exit(2);}
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/config/database.php';
require_once __DIR__.'/../app/services/Novedades.php';
try{echo (new Novedades($pdo))->procesar()." novedades enviadas.\n";}catch(Throwable $e){fwrite(STDERR,"No se completó la tanda. Revisar el panel de novedades.\n");exit(1);}
