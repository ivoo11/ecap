<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sincronización automática de estados de ediciones
|--------------------------------------------------------------------------
|
| Reglas:
|
| - Al alcanzar fecha_inicio + hora:
|       → en_curso
|
| - Al alcanzar fecha_fin + 30 minutos:
|       → finalizada
|
| - Nunca modifica:
|       → borrador
|       → cancelada
|
| La conexión PDO ($pdo) debe existir antes de incluir este archivo.
|
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException(
        'EstadoEdiciones.php requiere una conexión PDO activa.'
    );
}


/*
|--------------------------------------------------------------------------
| 1. FINALIZAR EDICIONES
|--------------------------------------------------------------------------
|
| Se ejecuta primero para permitir que una edición que ya terminó pueda
| pasar directamente a finalizada aunque no haya habido una petición
| durante el período en que estuvo en curso.
|
*/

$pdo->exec("
    UPDATE ediciones
    SET estado = 'finalizada'
    WHERE
        fecha_fin IS NOT NULL
        AND DATE_ADD(fecha_fin, INTERVAL 30 MINUTE) <= NOW()
        AND estado IN (
            'proximamente',
            'inscripcion_abierta',
            'cupo_completo',
            'inscripcion_cerrada',
            'en_curso'
        )
");


/*
|--------------------------------------------------------------------------
| 2. MARCAR EDICIONES EN CURSO
|--------------------------------------------------------------------------
*/

$pdo->exec("
    UPDATE ediciones
    SET estado = 'en_curso'
    WHERE
        fecha_inicio <= NOW()
        AND (
            fecha_fin IS NULL
            OR DATE_ADD(fecha_fin, INTERVAL 30 MINUTE) > NOW()
        )
        AND estado IN (
            'proximamente',
            'inscripcion_abierta',
            'cupo_completo',
            'inscripcion_cerrada'
        )
");