<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');
if(!Auth::hasRole('superadmin')){http_response_code(403);exit;}
if(date('Y-m-d')!=='2026-11-11'){http_response_code(410);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
if(!is_callable('exec')){http_response_code(503);exit('exec no disponible');}
$output=[];$code=0;
exec('/bin/bash '.escapeshellarg(dirname(__DIR__,2).'/cron-recordatorios.sh').' --dry-run 2>&1',$output,$code);
echo 'Código de salida: '.$code."\n".implode("\n",$output);
