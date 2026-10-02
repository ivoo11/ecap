<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
    }


    private function crearMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = $this->config['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $this->config['username'];
        $mail->Password = $this->config['password'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) $this->config['port'];

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $this->config['from_email'],
            $this->config['from_name']
        );

        return $mail;
    }


        public function enviarConfirmacionInscripcion(
            string $email,
            string $nombre,
            string $titulo,
            string $fechaInicio,
            string $modalidad,
            string $zoomUrl = '',
            string $zoomMeetingId = '',
            string $zoomPasscode = ''
        ): void {

        $mail = $this->crearMailer();

        $headerPath = __DIR__ . '/../../assets/img/headermail.png';

        if (is_file($headerPath)) {
            $mail->addEmbeddedImage(
                $headerPath,
                'ecap_header',
                'headermail.png'
            );
        }

        $fecha = new DateTime($fechaInicio);

        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        $fechaTexto =
            $fecha->format('j') .
            ' de ' .
            $meses[(int) $fecha->format('n')] .
            ' de ' .
            $fecha->format('Y');

        $horaTexto = $fecha->format('H:i') . ' h';

        $modalidadTexto = match ($modalidad) {
            'online' => 'Virtual',
            'presencial' => 'Presencial',
            'hibrida' => 'Híbrida',
            default => ucfirst($modalidad)
        };


        /*
         * Escapamos contenido que viene de la base
         * antes de utilizarlo en el HTML.
         */

        $nombreHtml = htmlspecialchars(
            $nombre,
            ENT_QUOTES,
            'UTF-8'
        );

        $tituloHtml = htmlspecialchars(
            $titulo,
            ENT_QUOTES,
            'UTF-8'
        );

        $fechaHtml = htmlspecialchars(
            $fechaTexto,
            ENT_QUOTES,
            'UTF-8'
        );

        $horaHtml = htmlspecialchars(
            $horaTexto,
            ENT_QUOTES,
            'UTF-8'
        );

        $modalidadHtml = htmlspecialchars(
            $modalidadTexto,
            ENT_QUOTES,
            'UTF-8'
        );

        $zoomUrlHtml = htmlspecialchars(
            $zoomUrl,
            ENT_QUOTES,
            'UTF-8'
        );

        $zoomMeetingIdHtml = htmlspecialchars(
            $zoomMeetingId,
            ENT_QUOTES,
            'UTF-8'
        );

        $zoomPasscodeHtml = htmlspecialchars(
            $zoomPasscode,
            ENT_QUOTES,
            'UTF-8'
        );

        $zoomBlockHtml = '';

        if ($zoomUrl !== '') {

            $zoomBlockHtml = <<<HTML
            <div style="
                margin: 30px 0;
                padding: 28px 0;
                border-top: 1px solid #e3e5e8;
                border-bottom: 1px solid #e3e5e8;
            ">

                <p style="
                    margin: 0 0 22px;
                    font-size: 15px;
                    line-height: 1.65;
                    color: #555b64;
                ">
                    Te recomendamos conectarte
                    <strong>15 minutos antes del horario de inicio</strong>
                    para verificar tu conexión.
                </p>

                <a
                    href="{$zoomUrlHtml}"
                    style="
                        display: inline-block;
                        padding: 14px 24px;
                        background: #101d30;
                        color: #ffffff;
                        text-decoration: none;
                        font-size: 14px;
                        font-weight: 700;
                        letter-spacing: .4px;
                        border-radius: 6px;
                    "
                >
                    ACCEDER A LA CLASE
                </a>

                <div style="
                    margin-top: 24px;
                    font-size: 14px;
                    line-height: 1.7;
                    color: #555b64;
                ">

                    <div>
                        <strong>ID de reunión:</strong>
                        {$zoomMeetingIdHtml}
                    </div>

                    <div>
                        <strong>Clave de acceso:</strong>
                        {$zoomPasscodeHtml}
                    </div>

                </div>

            </div>
            HTML;
        }

        /* =================================================
           DESTINATARIO
           ================================================= */

        $mail->addAddress(
            $email,
            $nombre
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Inscripción confirmada | ECAP';


        /* =================================================
           EMAIL HTML
           ================================================= */

        $mail->Body = <<<HTML
        <div style="
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #101d30;
        ">

        <div style="
            margin: 0 0 32px;
            text-align: center;
        ">
            <img
                src="cid:ecap_header"
                alt="ECAP"
                width="600"
                style="
                    display: block;
                    width: 100%;
                    max-width: 600px;
                    height: auto;
                    margin: 0 auto;
                    border: 0;
                    border-radius: 8px;
                "
            >
        </div>

            <h1 style="
                margin: 0 0 18px;
                font-size: 30px;
                line-height: 1.2;
                color: #101d30;
            ">
                Inscripción confirmada
            </h1>

            <p style="
                margin: 0 0 28px;
                font-size: 16px;
                line-height: 1.6;
                color: #555b64;
            ">
                Hola {$nombreHtml}, tu inscripción fue registrada
                correctamente.
            </p>

            <div style="
                margin: 0 0 30px;
                padding: 24px 0;
                border-top: 1px solid #e3e5e8;
                border-bottom: 1px solid #e3e5e8;
            ">

                <h2 style="
                    margin: 0 0 22px;
                    font-size: 21px;
                    line-height: 1.35;
                    color: #101d30;
                ">
                    {$tituloHtml}
                </h2>

                <p style="
                    margin: 0 0 8px;
                    font-size: 15px;
                    line-height: 1.5;
                ">
                    <strong>Fecha:</strong>
                    {$fechaHtml}
                </p>

                <p style="
                    margin: 0 0 8px;
                    font-size: 15px;
                    line-height: 1.5;
                ">
                    <strong>Hora:</strong>
                    {$horaHtml}
                </p>

                <p style="
                    margin: 0;
                    font-size: 15px;
                    line-height: 1.5;
                ">
                    <strong>Modalidad:</strong>
                    {$modalidadHtml}
                </p>

            </div>

            {$zoomBlockHtml}

            <p style="
                margin: 0;
                font-size: 13px;
                line-height: 1.6;
                color: #858a91;
            ">
                ECAP<br>
                Escuela de Capacitación de la Abogacía Pública
            </p>

        </div>
        HTML;


        /* =================================================
           VERSIÓN TEXTO
           ================================================= */

        $altBody = "INSCRIPCIÓN CONFIRMADA | ECAP\n\n";

        $altBody .= "Hola {$nombre}, tu inscripción fue registrada correctamente.\n\n";

        $altBody .= "{$titulo}\n\n";

        $altBody .= "Fecha: {$fechaTexto}\n";
        $altBody .= "Hora: {$horaTexto} h\n";
        $altBody .= "Modalidad: {$modalidadTexto}\n";

        if ($zoomUrl !== '') {

            $altBody .= "\n";
            $altBody .= "ACCESO A LA CLASE\n\n";

            $altBody .= "Te recomendamos conectarte 15 minutos antes del horario de inicio para verificar tu conexión.\n\n";

            $altBody .= "Acceder a la clase:\n";
            $altBody .= "{$zoomUrl}\n";

            if ($zoomMeetingId !== '') {
                $altBody .= "\nID de reunión: {$zoomMeetingId}\n";
            }

            if ($zoomPasscode !== '') {
                $altBody .= "Clave de acceso: {$zoomPasscode}\n";
            }
        }

        $altBody .= "\n";
        $altBody .= "ECAP\n";
        $altBody .= "Escuela de Capacitación de la Abogacía Pública";

        $mail->AltBody = $altBody;


        $mail->send();
    }
}