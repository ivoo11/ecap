<?php

declare(strict_types=1);

require_once __DIR__ . '/Session.php';

final class Auth
{
    private const SESSION_KEY = 'auth';

    /**
     * Intenta autenticar un usuario mediante DNI + contraseña.
     */
    public static function attempt(PDO $pdo, string $dni, string $password): bool
    {
        Session::start();

        $dni = trim($dni);

        if ($dni === '' || $password === '') {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT
                u.id AS usuario_id,
                u.password_hash,
                u.estado AS usuario_estado,
                p.id AS persona_id,
                p.nombre,
                p.apellido,
                p.dni,
                p.email,
                p.estado AS persona_estado
            FROM usuarios u
            INNER JOIN personas p
                ON p.id = u.persona_id
            WHERE p.dni = :dni
            LIMIT 1
        ");

        $stmt->execute([
            'dni' => $dni,
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            return false;
        }

        if ($usuario['persona_estado'] !== 'activo') {
            return false;
        }

        if ($usuario['usuario_estado'] !== 'activo') {
            return false;
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            return false;
        }

        $roles = self::loadRoles(
            $pdo,
            (int) $usuario['usuario_id']
        );

        if ($roles === []) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Login correcto
        |--------------------------------------------------------------------------
        |
        | Regeneramos el ID de sesión para evitar conservar el identificador
        | utilizado antes de la autenticación.
        |
        */

        Session::regenerate();

        $ahora = time();

        $_SESSION[self::SESSION_KEY] = [
            'usuario_id' => (int) $usuario['usuario_id'],
            'persona_id' => (int) $usuario['persona_id'],
            'nombre' => (string) $usuario['nombre'],
            'apellido' => (string) $usuario['apellido'],
            'dni' => (string) $usuario['dni'],
            'email' => (string) $usuario['email'],
            'roles' => $roles,
            'autenticado_en' => $ahora,
            'ultima_actividad' => $ahora,
        ];

        /*
        |--------------------------------------------------------------------------
        | Registrar último acceso
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET ultimo_acceso_en = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $usuario['usuario_id'],
        ]);

        return true;
    }

    /**
     * Indica si existe una sesión autenticada.
     */
    public static function check(): bool
    {
        Session::start();

        return isset($_SESSION[self::SESSION_KEY]['usuario_id'])
            && is_int($_SESSION[self::SESSION_KEY]['usuario_id']);
    }

    /**
     * Devuelve los datos del usuario autenticado.
     */
    public static function user(): ?array
    {
        Session::start();

        if (!self::check()) {
            return null;
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Devuelve el ID del usuario autenticado.
     */
    public static function id(): ?int
    {
        $usuario = self::user();

        return $usuario !== null
            ? (int) $usuario['usuario_id']
            : null;
    }

    /**
     * Comprueba si el usuario posee un rol concreto.
     */
    public static function hasRole(string $role): bool
    {
        $usuario = self::user();

        if ($usuario === null) {
            return false;
        }

        return in_array(
            $role,
            $usuario['roles'],
            true
        );
    }

    /**
     * Comprueba si el usuario posee al menos uno
     * de los roles indicados.
     */
    public static function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::hasRole((string) $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si el usuario puede acceder al administrador.
     */
    public static function canAccessAdmin(): bool
    {
        return self::hasAnyRole([
            'superadmin',
            'administrador',
        ]);
    }

    /**
     * Valida la vigencia de una sesión administrativa.
     *
     * - 30 minutos máximos de inactividad.
     * - Mientras exista actividad, la sesión continúa vigente.
     */
    public static function validateAdminSession(
        int $inactivityLimit = 1800
    ): bool {
        Session::start();

        if (!self::check()) {
            return false;
        }

        $auth = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($auth)) {
            self::logout();
            return false;
        }

        $ultimaActividad = $auth['ultima_actividad'] ?? null;

        if (!is_int($ultimaActividad)) {
            self::logout();
            return false;
        }

        $ahora = time();

        /*
        |--------------------------------------------------------------------------
        | Límite por inactividad
        |--------------------------------------------------------------------------
        |
        | Si pasan 30 minutos sin realizar requests autenticados,
        | la sesión deja de ser válida.
        |
        | Mientras exista actividad, la sesión puede continuar
        | indefinidamente.
        |
        */

        if (($ahora - $ultimaActividad) >= $inactivityLimit) {
            self::logout();
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Renovar actividad
        |--------------------------------------------------------------------------
        */

        $_SESSION[self::SESSION_KEY]['ultima_actividad'] = $ahora;

        return true;
    }

    /**
     * Cierra completamente la sesión actual.
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Obtiene todos los roles activos del usuario.
     */
    private static function loadRoles(PDO $pdo, int $usuarioId): array
    {
        $stmt = $pdo->prepare("
            SELECT r.codigo
            FROM usuario_roles ur
            INNER JOIN roles r
                ON r.id = ur.rol_id
            WHERE ur.usuario_id = :usuario_id
              AND r.activo = 1
            ORDER BY r.id
        ");

        $stmt->execute([
            'usuario_id' => $usuarioId,
        ]);

        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(
            array_filter(
                array_map('strval', $roles)
            )
        );
    }
}