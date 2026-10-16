<?php
declare(strict_types=1);
require_once __DIR__ . '/PublicImage.php';

/** Helpers compartidos por los formularios privados, después del bootstrap. */
final class AdminForm
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function text(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }

    public static function csrf(): void
    {
        if (!Csrf::validate(self::text('_csrf'))) {
            http_response_code(419);
            exit('La sesión del formulario venció. Recargá la página.');
        }
    }

    public static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date : null;
    }

    /** Guarda únicamente una imagen decodificada y regenerada, nunca el archivo original. */
    public static function image(string $field, string $folder): ?string
    {
        if (!in_array($folder, ['docentes', 'actividades'], true)) throw new LogicException('Carpeta inválida.');
        $file = $_FILES[$field] ?? null;
        if (!$file || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 2 * 1024 * 1024) {
            throw new DomainException('No se pudo cargar la imagen. Usá un archivo de hasta 2 MB.');
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] * $info[1] > 16000000) {
            throw new DomainException('Usá una imagen JPG, PNG o WebP de hasta 16 megapíxeles.');
        }
        if (!function_exists('imagecreatefromstring')) throw new DomainException('El servidor necesita habilitar GD para cargar imágenes.');
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$image) throw new DomainException('No se pudo leer la imagen.');
        imagesavealpha($image, true);
        $directory = PublicImage::directory($folder);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
        $name = 'ecap-' . bin2hex(random_bytes(16)) . '.png';
        if (!imagepng($image, $directory . '/' . $name)) throw new RuntimeException('No se pudo guardar la imagen.');
        return $name;
    }

    public static function discardImage(?string $name, string $folder): void
    {
        if ($name && preg_match('/^ecap-[a-f0-9]{32}\.png$/D', $name)) {
            @unlink(PublicImage::directory($folder) . '/' . $name);
        }
    }
}
