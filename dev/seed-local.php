<?php

/**
 * LOCAL DOCKER TEST SITE ONLY: sets up the demo pages/menus with test-only shortcuts
 * (no account activation e-mail).
 *   docker compose exec -u www-data joomla php /project/dev/seed-local.php
 * Do not run this on the live site — for a fresh real site use deploy/setup-site.php (see docs/NEW-SERVER.md).
 */

$argv = [__FILE__, '--joomla=/var/www/html', '--content=/project/content', '--email=help@pamperins.com', '--phone=(920) 555-0100', '--local'];
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = \count($argv);

require __DIR__ . '/../deploy/setup-site.php';
