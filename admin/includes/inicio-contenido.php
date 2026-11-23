<?php
$escapeInicio=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$incidencias=count($correosPendientes);
$iconoInicio=static function(string $tipo):string {
    $paths=[
        'actividades'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/>',
        'alumnos'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v2"/>',
        'docentes'=>'<rect x="3" y="3" width="18" height="13" rx="2"/><path d="M8 21l4-5 4 5M7 8h10M7 11h6"/>',
        'correo'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$tipo].'</svg>';
};
?>
<section class="admin-page inicio-page">
    <header class="inicio-heading"><h1>Hola, <?= $escapeInicio($usuario['nombre']) ?></h1></header>
    <section class="inicio-comunicaciones <?= $incidencias||$cronCorreo['alerta']?'inicio-comunicaciones--atencion':'' ?>" aria-labelledby="inicio-correo">
        <div class="inicio-correo-heading">
            <div class="inicio-section-title"><?= $iconoInicio('correo') ?><h2 id="inicio-correo">Correos</h2></div>
            <a class="inicio-link" href="/admin/correos/"><?= $incidencias?'Revisar '.$incidencias.' '.($incidencias===1?'correo':'correos'):'Ver correos' ?></a>
        </div>
        <p class="inicio-estado"><span class="inicio-status-dot" aria-hidden="true"></span><?= $incidencias? $incidencias.' '.($incidencias===1?'correo requiere':'correos requieren').' atención.':'Sin incidencias detectadas en actividades vigentes.' ?></p>
        <div class="inicio-recordatorios">
            <h3>Recordatorios de hoy</h3>
            <?php if(!$resumenRecordatorios): ?>
                <p>No hay actividades con participantes confirmados para hoy.</p>
            <?php else: foreach($resumenRecordatorios as $r):
                $total=(int)$r['destinatarios'];$enviados=(int)$r['aceptados'];$fallidos=(int)$r['errores'];$revision=(int)$r['revision'];$pendientes=max(0,$total-$enviados-$fallidos-$revision);
            ?>
                <article class="inicio-recordatorio">
                    <div><h4><?= $escapeInicio($r['titulo']) ?></h4><p><?= date('H:i',strtotime($r['fecha_inicio'])) ?> hs · <?= $enviados ?> de <?= $total ?> enviados</p>
                    <?php if($enviados<$total): ?><p><?= $pendientes ?> pendientes · <?= $fallidos ?> fallidos · <?= $revision ?> sin confirmación</p><?php endif; ?>
                    <?php if($pendientes && date('H:i')<'07:00'): ?><p>El envío comienza a las 07:00.</p><?php endif; ?>
                    <?php if($r['ultimo_aceptado']): ?><p>Último envío: <?= date('H:i',strtotime($r['ultimo_aceptado'])) ?> hs</p><?php endif; ?></div>
                    <a class="inicio-link" href="/admin/correos/historial.php?tipo=recordatorio_clase&amp;edicion=<?= (int)$r['edicion_id'] ?>">Ver envíos</a>
                </article>
            <?php endforeach; endif; ?>
            <?php if($cronCorreo['alerta']): ?><p class="inicio-alerta">Hay recordatorios pendientes y no se registró una ejecución completada hoy. <a href="/admin/correos/">Revisar recordatorios</a></p><?php endif; ?>
        </div>
    </section>
    <nav class="inicio-accesos" aria-label="Gestión de ECAP">
        <?php foreach(['actividades'=>['Actividades','Ediciones, clases y certificados.'],'alumnos'=>['Alumnos','Personas e inscripciones.'],'docentes'=>['Docentes','Perfiles y participación.']] as $ruta=>[$titulo,$descripcion]): ?>
        <a class="inicio-acceso" href="/admin/<?= $ruta ?>/">
            <span class="inicio-acceso-icon"><?= $iconoInicio($ruta) ?></span>
            <div><h2><?= $titulo ?></h2><p><?= $descripcion ?></p></div>
        </a>
        <?php endforeach; ?>
    </nav>
</section>
