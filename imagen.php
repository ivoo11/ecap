<?php
declare(strict_types=1);
require_once __DIR__ . '/app/services/PublicImage.php';
$folder = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$name = is_string($_GET['archivo'] ?? null) ? $_GET['archivo'] : '';
if (!in_array($folder, ['docentes', 'actividades'], true)) { http_response_code(404); exit; }
$path = PublicImage::path($folder, $name);
$info = $path ? @getimagesize($path) : false;
if (!$info || !in_array($info['mime'] ?? '', ['image/png','image/jpeg','image/webp'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $info['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($path));
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($path);
