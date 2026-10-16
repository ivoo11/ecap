<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Environment.php';

use PHPMailer\PHPMailer\PHPMailer;

final class LocalCaptureMailer extends PHPMailer
{
    public function send()
    {
        Environment::requireLocal();
        if (!$this->preSend()) {
            return false;
        }
        $directory = Environment::runtimePath() . '/mail';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el buzón local.');
        }
        $path = $directory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.eml';
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('No se pudo capturar el correo local.');
        }
        chmod($path, 0600);
        try {
            $message = $this->getSentMIMEMessage();
            if (fwrite($handle, $message) !== strlen($message)) {
                throw new RuntimeException('Captura local incompleta.');
            }
        } finally {
            fclose($handle);
        }
        return true;
    }
}

final class MailTransport
{
    public static function create(): PHPMailer
    {
        if (Environment::isLocal()) {
            Environment::requireLocal();
            $mail = new LocalCaptureMailer(true);
            $mail->setFrom('noresponder@example.test', 'ECAP · Desarrollo local');
        } else {
            // Se conserva el contrato SMTP actual del servidor hasta coordinar su migración.
            $config = require __DIR__ . '/../config/mail.php';
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int) $config['port'];
            $mail->setFrom($config['from_email'], $config['from_name']);
        }
        $mail->CharSet = 'UTF-8';
        return $mail;
    }
}
