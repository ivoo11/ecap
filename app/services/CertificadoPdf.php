<?php
declare(strict_types=1);
require_once __DIR__.'/../config/Environment.php';

final class CertificadoPdf
{
    public const DEFAULT_BACKGROUND = '1dff6b16cd1765f91eebee9c504436a6.png';

    public static function directory(): string
    {
        $path = Environment::isLocal() ? Environment::runtimePath().'/certificados' : dirname(__DIR__,3).'/ecap_certificados';
        if (!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) throw new RuntimeException('No se pudo preparar el almacenamiento de certificados.');
        return $path;
    }

    public static function file(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|png)$/D',$name)) throw new RuntimeException('Archivo de certificado inválido.');
        if ($name === self::DEFAULT_BACKGROUND) return __DIR__.'/../resources/certificados/'.$name;
        return self::directory().'/'.$name;
    }

    private static function engine(): \Mpdf\Mpdf
    {
        $defaults=(new \Mpdf\Config\FontVariables())->getDefaults();
        return new \Mpdf\Mpdf([
            'format'=>'A4-L','margin_left'=>0,'margin_right'=>0,'margin_top'=>0,'margin_bottom'=>0,
            'tempDir'=>self::directory().'/cache',
            'fontDir'=>array_merge((new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],[__DIR__.'/../resources/certificados']),
            'fontdata'=>$defaults['fontdata']+[
                'cinzelecap'=>['R'=>'CinzelBold.ttf'],
                'cormorantecap'=>['R'=>'CormorantBold.ttf'],
                'cormorantnombre'=>['R'=>'CormorantBoldItalic.ttf'],
                'montserratecap'=>['R'=>'Montserrat-Light.ttf'],
            ],
        ]);
    }

    public static function upload(array $file): string
    {
        if (($file['error']??-1)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name'])>10*1024*1024) throw new DomainException('Cargá una plantilla PDF o PNG de hasta 10 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext=match($mime){'application/pdf'=>'pdf','image/png'=>'png',default=>throw new DomainException('La plantilla debe ser PDF o PNG.')};
        if($ext==='pdf'){
            try {
                $p=self::engine();
                if($p->setSourceFile($file['tmp_name'])!==1) throw new RuntimeException();
                $size=$p->getTemplateSize($p->importPage(1));
                if(abs($size['width']-297)>2 || abs($size['height']-210)>2) throw new RuntimeException();
            }catch(Throwable $e){throw new DomainException('Exportá una sola página A4 horizontal, sin contraseña, como PDF 1.4 compatible.');}
        } else {
            $size=@getimagesize($file['tmp_name']);
            if(!$size || $size[0]<1200 || $size[0]*$size[1]>16000000 || abs($size[0]/$size[1]-297/210)>.02) throw new DomainException('Usá un PNG A4 horizontal, de al menos 1200 px de ancho y hasta 16 megapíxeles.');
        }
        $name=bin2hex(random_bytes(16)).'.'.$ext;$path=self::file($name);
        if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('No se pudo guardar la plantilla.');
        chmod($path,0600);return $name;
    }

    public static function fechaEdicion(array $edicion): string
    {
        $fecha = ($edicion['fecha_fin'] ?? null) ?: ($edicion['fecha_inicio'] ?? null);
        if (!$fecha) throw new DomainException('La edición no tiene fecha para el certificado.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($fecha, 0, 10));
        if (!$date || $date->format('Y-m-d') !== substr($fecha, 0, 10)) throw new DomainException('Revisá la fecha de la edición.');
        $meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return $meses[(int)$date->format('n')].' de '.$date->format('Y');
    }

    public static function render(array $config,string $nombre,bool $preview=false): string
    {
        $pdf=self::engine();$pdf->SetAutoPageBreak(false);$pdf->AddPage();
        $path=self::file($config['fondo'] ?: self::DEFAULT_BACKGROUND);
        if(!is_file($path)) throw new DomainException('No se encuentra la plantilla. Volvé a cargarla.');
        if(str_ends_with($path,'.pdf')){
            $pdf->setSourceFile($path);$pdf->useTemplate($pdf->importPage(1),0,0,297,210);
        } else $pdf->Image($path,0,0,297,210,'png');
        // Sustituye la leyenda fija del modelo original; no altera el archivo base.
        $pdf->SetFillColor(255,255,255);$pdf->Rect(105,106.5,87,8,'F');
        $pdf->SetFillColor(30,30,38);$pdf->SetTextColor(30,30,38);self::center($pdf,$config['leyenda']??'POR SU PARTICIPACIÓN EN','montserratecap',12,113.1,230,10);
        $pdf->SetTextColor(0,18,41);
        self::center($pdf,$config['encabezado'],'cinzelecap',35,66.4,260,22);
        self::center($pdf,$nombre,'cormorantnombre',45,101.4,230,24);
        $title=preg_replace('/\s+/u',' ',trim($config['titulo']));
        for($size=26;$size>=20;$size-=.5){
            $pdf->SetFont('cormorantecap','',$size);$lines=[];$line='';
            foreach(explode(' ',$title) as $word){
                if($pdf->GetStringWidth($word)>230) continue 2;
                $candidate=trim($line.' '.$word);
                if($line!=='' && $pdf->GetStringWidth($candidate)>230){$lines[]=$line;$line=$word;}else $line=$candidate;
            }
            $lines[]=$line;if(count($lines)<=3)break;
        }
        if($size<20)throw new DomainException('El título es demasiado largo para la plantilla. Acortalo antes de emitir.');
        foreach($lines as $i=>$line)self::center($pdf,$line,'cormorantecap',$size,125+$i*$size*1.12*25.4/72,230,$size);
        if (!empty($config['fecha_edicion'])) self::center($pdf,$config['fecha_edicion'],'montserratecap',9,155,230,9);
        if($preview){$pdf->SetTextColor(110,110,110);self::center($pdf,'VISTA PREVIA · SIN VALIDEZ','montserratecap',7,197,200,7);}
        $pdf->SetTitle('Certificado ECAP');$pdf->SetAuthor('ECAP');
        return $pdf->Output('','S');
    }

    private static function center(\Mpdf\Mpdf $pdf,string $text,string $font,float $size,float $y,float $width,float $minimum): void
    {
        do{$pdf->SetFont($font,'',$size);$length=$pdf->GetStringWidth($text);if($length<=$width)break;$size-=.5;}while($size>=$minimum);
        if($size<$minimum)throw new DomainException('Un texto no entra en la plantilla. Revisá el encabezado o el nombre.');
        $pdf->Text((297-$length)/2,$y,$text);
    }
}
