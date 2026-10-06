<?php

declare(strict_types=1);

require_once __DIR__ . '/MailService.php';

final class InscripcionService
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function inscribirPersonaDesdeAdmin(
    int $personaId,
    int $edicionId
): int {

    if ($personaId <= 0 || $edicionId <= 0) {
        throw new InvalidArgumentException(
            'Persona o edición inválida.'
        );
    }

    try {

        $this->pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Verificar persona
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                estado
            FROM personas
            WHERE id = :persona_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'persona_id' => $personaId,
        ]);

        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            throw new RuntimeException(
                'La persona no se encuentra registrada en ECAP.'
            );
        }

        if ($persona['estado'] !== 'activo') {
            throw new RuntimeException(
                'La persona no se encuentra activa.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Bloquear edición
        |--------------------------------------------------------------------------
        |
        | El alta administrativa NO exige que la inscripción pública esté
        | abierta. Un administrador puede incorporar una persona por una
        | decisión administrativa.
        |
        | Sí bloqueamos estados en los que ya no tiene sentido admitir alumnos.
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                cupo_maximo,
                estado
            FROM ediciones
            WHERE id = :edicion_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'edicion_id' => $edicionId,
        ]);

        $edicion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$edicion) {
            throw new RuntimeException(
                'La edición no existe.'
            );
        }

        if (in_array(
            $edicion['estado'],
            ['finalizada', 'cancelada'],
            true
        )) {
            throw new RuntimeException(
                'No se pueden agregar alumnos a esta edición.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Evitar inscripción duplicada
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                estado
            FROM inscripciones
            WHERE
                persona_id = :persona_id
                AND edicion_id = :edicion_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'persona_id' => $personaId,
            'edicion_id' => $edicionId,
        ]);

        $inscripcionExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inscripcionExistente) {

            throw new RuntimeException(
                'La persona ya posee una inscripción para esta edición.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Validar cupo
        |--------------------------------------------------------------------------
        */

        $cupoMaximo = $edicion['cupo_maximo'] !== null
            ? (int) $edicion['cupo_maximo']
            : null;

        if ($cupoMaximo !== null) {

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM inscripciones
                WHERE
                    edicion_id = :edicion_id
                    AND estado = 'confirmada'
            ");

            $stmt->execute([
                'edicion_id' => $edicionId,
            ]);

            $inscriptosConfirmados = (int) $stmt->fetchColumn();

            if ($inscriptosConfirmados >= $cupoMaximo) {
                throw new RuntimeException(
                    'La edición alcanzó el cupo máximo.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Crear inscripción
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            INSERT INTO inscripciones (
                persona_id,
                edicion_id,
                estado,
                inscripto_en,
                cancelado_en
            )
            VALUES (
                :persona_id,
                :edicion_id,
                'confirmada',
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            'persona_id' => $personaId,
            'edicion_id' => $edicionId,
        ]);

        $inscripcionId = (int) $this->pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Confirmar BD
        |--------------------------------------------------------------------------
        */

        $this->pdo->commit();

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }


        /*
        |--------------------------------------------------------------------------
        | Enviar confirmación
        |--------------------------------------------------------------------------
        |
        | Igual que en el flujo público:
        | la inscripción ya quedó confirmada.
        |
        | Un fallo del correo NO debe borrar la inscripción.
        |--------------------------------------------------------------------------
        */

        try {

            $this->enviarConfirmacion(
                $personaId,
                $edicionId
            );

        } catch (Throwable $e) {

            error_log(
                'Error enviando confirmación de inscripción administrativa ECAP: ' .
                $e->getMessage()
            );
        }


        return $inscripcionId;
    }

    public function enviarConfirmacion(
        int $personaId,
        int $edicionId
    ): void {

        $stmt = $this->pdo->prepare("
            SELECT
                p.nombre,
                p.email,
                a.titulo,
                e.fecha_inicio,
                e.modalidad,
                e.zoom_url,
                e.zoom_meeting_id,
                e.zoom_passcode
            FROM personas p
            INNER JOIN ediciones e
                ON e.id = :edicion_id
            INNER JOIN actividades a
                ON a.id = e.actividad_id
            WHERE p.id = :persona_id
            LIMIT 1
        ");

        $stmt->execute([
            'persona_id' => $personaId,
            'edicion_id' => $edicionId,
        ]);

        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$datos) {
            throw new RuntimeException(
                'No se encontraron los datos necesarios para enviar la confirmación.'
            );
        }

        $mailService = new MailService();

        $mailService->enviarConfirmacionInscripcion(
            (string) $datos['email'],
            (string) $datos['nombre'],
            (string) $datos['titulo'],
            (string) $datos['fecha_inicio'],
            (string) $datos['modalidad'],
            (string) ($datos['zoom_url'] ?? ''),
            (string) ($datos['zoom_meeting_id'] ?? ''),
            (string) ($datos['zoom_passcode'] ?? '')
        );
    }
}