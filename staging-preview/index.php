<?php
declare(strict_types=1);
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header('Location: /dashboard.html', true, 302);
exit;
