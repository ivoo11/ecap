<?php
declare(strict_types=1);

final class PublicImage
{
    public static function directory(string $folder): string
    {
        if (!in_array($folder, ['docentes', 'actividades'], true)) throw new InvalidArgumentException('Carpeta inválida.');
        return dirname(__DIR__, 3) . '/ecap_media/' . $folder;
    }

    public static function path(string $folder, string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:png|jpg|jpeg|webp)$/iD', $name)) return null;
        foreach ([self::directory($folder), dirname(__DIR__, 2) . '/assets/img/' . $folder] as $directory) {
            $path = $directory . '/' . $name;
            if (is_file($path) && !is_link($path) && is_readable($path)) return $path;
        }
        return null;
    }

    public static function url(string $folder, string $name): string
    {
        self::directory($folder);
        return '/imagen.php?tipo=' . rawurlencode($folder) . '&archivo=' . rawurlencode($name);
    }
}
