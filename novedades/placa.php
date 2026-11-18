<?php
declare(strict_types=1);
require_once __DIR__.'/../app/services/PublicImage.php';
require_once __DIR__.'/../app/services/NovedadPlaca.php';
$name=is_string($_GET['archivo']??null)?$_GET['archivo']:'';
$date=is_string($_GET['fecha']??null)?$_GET['fecha']:'';
$path=PublicImage::path('actividades',$name);
if(!$path){http_response_code(404);exit;}
try{$image=NovedadPlaca::render($path,$date);}catch(Throwable $e){http_response_code(404);exit;}
header('Content-Type: image/jpeg');header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
if($_SERVER['REQUEST_METHOD']!=='HEAD')imagejpeg($image,null,92);
unset($image);
