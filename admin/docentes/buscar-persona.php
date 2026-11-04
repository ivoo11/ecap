<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!Csrf::validate(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) { http_response_code(403); exit; }
$dni = is_string($_POST['dni'] ?? null) ? trim($_POST['dni']) : '';
if (!preg_match('/^[0-9]{7,9}$/D', $dni)) { http_response_code(422); exit; }
try {
    $stmt = $pdo->prepare('SELECT p.id,p.nombre,p.apellido,p.email,p.estado,d.id AS docente_id FROM personas p LEFT JOIN docentes d ON d.persona_id=p.id WHERE p.dni=? LIMIT 1');
    $stmt->execute([$dni]);
    echo json_encode(['persona'=>$stmt->fetch(PDO::FETCH_ASSOC) ?: null], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ECAP búsqueda docente: '.$e->getMessage());
    http_response_code(500); echo json_encode(['error'=>'No se pudo consultar el DNI.']);
}
