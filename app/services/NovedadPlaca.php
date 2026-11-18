<?php
declare(strict_types=1);
final class NovedadPlaca
{
 public static function render(string $path, string $date): GdImage {
  $fecha=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
  if(!$fecha||$fecha->format('Y-m-d')!==$date)throw new InvalidArgumentException('Fecha inválida');
  $info=getimagesize($path);
  if(!$info||$info[0]*$info[1]>16000000||$info[0]/$info[1]<0.5||$info[0]/$info[1]>3)throw new InvalidArgumentException('Imagen inválida');
  $source=match($info['mime']){'image/png'=>imagecreatefrompng($path),'image/jpeg'=>imagecreatefromjpeg($path),'image/webp'=>imagecreatefromwebp($path),default=>false};
  if(!$source)throw new RuntimeException('No se pudo leer la imagen');
  $w=1200;$h=(int)round($w*$info[1]/$info[0]);
  $out=imagecreatetruecolor($w,$h);imagefill($out,0,0,imagecolorallocate($out,7,21,37));
  imagecopyresampled($out,$source,0,0,0,0,$w,$h,$info[0],$info[1]);unset($source);
  $font=__DIR__.'/../../vendor/mpdf/mpdf/ttfonts/FreeSans.ttf';
  $day=$fecha->format('j');$month=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'][(int)$fecha->format('n')-1];
  $scale=min(1,$h/750);$size=160*$scale;$small=28*$scale;$gap=16*$scale;$margin=65*$scale;$base=(int)round($h-$margin);
  $db=imagettfbbox($size,0,$font,$day);$mb=imagettfbbox($small,0,$font,$month);
  $mx=(int)round($w-$margin-($mb[2]-$mb[0]));$dx=(int)round($mx-$gap-($db[2]-$db[0]));
  $white=imagecolorallocate($out,255,255,255);
  imagettftext($out,$size,0,$dx-$db[0],$base,$white,$font,$day);
  imagettftext($out,$small,0,$mx-$mb[0],$base,$white,$font,$month);
  return $out;
 }
}
