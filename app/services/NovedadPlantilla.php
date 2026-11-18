<?php
declare(strict_types=1);
require_once __DIR__.'/PublicImage.php';
final class NovedadPlantilla
{
 public static function html(array $e,string $intro):string {
  $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $fecha=new DateTimeImmutable($e['fecha_inicio']);
  $meses=['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $mes=$meses[(int)$fecha->format('n')];
  $url='https://ecap.com.ar/actividades/?slug='.rawurlencode($e['slug']);
  $imagen=$e['imagen_portada']?'https://ecap.com.ar'.PublicImage::url('actividades',$e['imagen_portada']):'';
  $f=$escape($fecha->format('j').' de '.$mes.' · '.$fecha->format('H:i').' hs');
  // Imagen fluida con altura natural: no depende de media queries ni fondos CSS.
  // La fecha permanece como texto independiente debajo de la placa.
  $placa=$imagen?'<img src="'.$escape($imagen).'" width="540" alt="'.$escape($e['titulo']).'" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:16px;">':'';
  return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>@media(max-width:480px){.pad{padding:22px!important}}</style></head><body style="margin:0;background:#f4f5f7;font-family:Arial,sans-serif;color:#101e31"><table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="600" style="width:100%;max-width:600px;background:white" cellpadding="0" cellspacing="0"><tr><td style="padding:26px 30px;background:#101e31"><img src="https://ecap.com.ar/assets/img/logobc.png" width="178" alt="ECAP" style="display:block;max-width:100%"></td></tr><tr><td class="pad" style="padding:30px;overflow-wrap:break-word"><h1 style="font-size:28px;line-height:1.2;margin:0 0 16px">Nueva actividad para vos</h1><p style="font-size:15px;line-height:1.65;margin:0 0 24px">'.nl2br($escape($intro)).'</p><div class="art">'.$placa.'</div><p style="font-size:16px;line-height:1.5;margin:22px 0 6px">'.$escape($e['titulo']).'</p><p style="font-size:15px;line-height:1.6;margin:12px 0 24px">'.$f.'<br>'.$escape(ucfirst($e['modalidad'])).'</p><table role="presentation" align="center"><tr><td align="center" bgcolor="#101e31" style="border-radius:4px"><a href="'.$escape($url).'" style="display:inline-block;padding:16px 18px;line-height:1.4;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold">Ver actividad e inscribirme</a></td></tr></table></td></tr><tr><td style="padding:22px 30px;background:#f4f5f7;font-size:12px;line-height:1.6;color:#647083">Recibís las novedades de ECAP porque elegiste suscribirte.<br><a href="https://ecap.com.ar/novedades/baja.php?token=__BAJA_TOKEN__" style="color:#647083">Dejar de recibir novedades</a></td></tr></table></td></tr></table></body></html>';
 }
}
