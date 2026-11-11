<?php
// Solo lectura: el estado se obtiene de cada correo, no del resultado global del cron.
require_once __DIR__.'/../../app/services/CorreoPanel.php';
$resumenRecordatorios=(new CorreoPanel($pdo))->recordatoriosHoy($edicionRecordatorios ?? null);
?>
<section class="admin-card">
<h2>Recordatorios de hoy</h2>
<?php if (!$resumenRecordatorios): ?>
<p>No hay actividades con participantes confirmados para hoy.</p>
<?php else: foreach ($resumenRecordatorios as $resumen):
$total=(int)$resumen['destinatarios']; $enviados=(int)$resumen['aceptados'];
$fallidos=(int)$resumen['errores']; $revision=(int)$resumen['revision'];
$pendientes=max(0,$total-$enviados-$fallidos-$revision);
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="correo-intento">
<h3><?= $escape($resumen['titulo']) ?></h3>
<p><?= $escape(date('d/m/Y H:i',strtotime($resumen['fecha_inicio']))) ?> hs</p>
<p><strong><?= $enviados===$total ? 'Todos los recordatorios fueron enviados.' : "$enviados de $total recordatorios enviados." ?></strong></p>
<p><?= $enviados ?> enviados · <?= $fallidos ?> fallidos · <?= $pendientes ?> pendientes · <?= $revision ?> sin confirmación de envío</p>
<?php if ($pendientes && date('H:i')<'07:00'): ?><p>El envío automático comienza a las 07:00, hora de Argentina.</p><?php endif; ?>
<?php if ($resumen['ultimo_aceptado']): ?><p>Último envío: <?= $escape(date('d/m/Y H:i',strtotime($resumen['ultimo_aceptado']))) ?>.</p><?php endif; ?>
<a class="activity-action-secondary" href="/admin/correos/historial.php?tipo=recordatorio_clase&amp;edicion=<?= (int)$resumen['edicion_id'] ?>">Ver envíos</a>
<?php if ($enviados<$total): ?><a class="activity-action-secondary" href="/admin/correos/?edicion=<?= (int)$resumen['edicion_id'] ?>">Revisar pendientes</a><?php endif; ?>
</div>
<?php endforeach; ?>
<p class="admin-text-muted">Enviado significa que el servidor de correo aceptó el mensaje. No acredita su lectura ni su llegada a la bandeja de entrada.</p>
<?php endif; ?>
</section>
