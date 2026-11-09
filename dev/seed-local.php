<?php

/**
 * LOCAL TEST SITE ONLY: fills the docker Joomla site with demo content so every
 * styled view (home, pricing table, contact form, login/register/reset) can be checked.
 *   docker compose exec -u www-data joomla php /project/dev/seed-local.php
 * Do not run this on the live site.
 */

const _JEXEC = 1;
define('JPATH_BASE', '/var/www/html');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Menu;
use Joomla\Component\Contact\Administrator\Table\ContactTable;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
Factory::$application = $container->get(\Joomla\Console\Application::class);

\JLoader::registerNamespace('Joomla\\Component\\Contact\\Administrator', JPATH_ADMINISTRATOR . '/components/com_contact/src');

$db  = $container->get(DatabaseInterface::class);
$now = Factory::getDate()->toSql();

$exists = fn (string $sql) => (int) $db->setQuery($sql)->loadResult();

function article(DatabaseInterface $db, string $title, string $alias, string $html, string $now): int
{
    $existing = (int) $db->setQuery('SELECT id FROM #__content WHERE alias = ' . $db->quote($alias))->loadResult();

    if ($existing) {
        return $existing;
    }

    $t = new Content($db);
    $t->bind([
        'title' => $title, 'alias' => $alias, 'introtext' => $html, 'fulltext' => '',
        'state' => 1, 'catid' => 2, 'access' => 1, 'language' => '*', 'created' => $now,
        'created_by' => 0, 'modified' => $now, 'publish_up' => $now,
        'attribs' => '{"show_category":"0","show_author":"0","show_publish_date":"0","show_create_date":"0","show_modify_date":"0","show_hits":"0","show_item_navigation":"0","show_parent_category":"0"}', 'metadata' => '{"robots":""}', 'images' => '{}', 'urls' => '{}', 'metakey' => '', 'metadesc' => '',
    ]);
    $t->store() or die('Article failed: ' . $t->getError());

    return (int) $t->id;
}

/** Remote Support page + main-menu item after "Services" (safe to run again). */
function remoteSupport(DatabaseInterface $db, string $now): void
{
    if ((int) $db->setQuery("SELECT COUNT(*) FROM #__menu WHERE alias='remote-support' AND client_id=0")->loadResult()) {
        return;
    }

    $id = article($db, 'Remote Support from Pamperins Tech', 'remote-support', file_get_contents('/project/content/remote-support.html'), $now);
    $after = (int) $db->setQuery("SELECT id FROM #__menu WHERE alias='services' AND client_id=0")->loadResult();

    $m = new Menu($db);
    $m->setLocation($after ?: 1, $after ? 'after' : 'last-child');
    $m->bind([
        'menutype' => 'mainmenu', 'title' => 'Remote Support', 'alias' => 'remote-support',
        'link' => 'index.php?option=com_content&view=article&id=' . $id, 'type' => 'component',
        'published' => 1, 'parent_id' => 1, 'level' => 1, 'component_id' => (int) ComponentHelper::getComponent('com_content')->id,
        'access' => 1, 'client_id' => 0, 'language' => '*', 'template_style_id' => 0, 'img' => '',
        'params' => json_encode(['menu_show' => 1, 'show_page_heading' => 0, 'show_category' => '0', 'show_author' => '0', 'show_publish_date' => '0', 'show_create_date' => '0', 'show_modify_date' => '0', 'show_hits' => '0', 'show_item_navigation' => '0', 'show_parent_category' => '0']),
    ]);
    ($m->check() && $m->store()) or die('Menu Remote Support failed: ' . $m->getError());
    $m->rebuildPath($m->id);
    echo "Added Remote Support page (article $id).\n";
}


/** Support ticket pages (needs the com_ptickets extension installed). */
function supportTickets(DatabaseInterface $db): void
{
    $component = ComponentHelper::getComponent('com_ptickets', true);

    if (!$component->id || (int) $db->setQuery("SELECT COUNT(*) FROM #__menu WHERE alias='request-support' AND client_id=0")->loadResult()) {
        return;
    }

    $after = (int) $db->setQuery("SELECT id FROM #__menu WHERE alias='remote-support' AND client_id=0")->loadResult();

    foreach ([['Request Support', 'request-support', 'request', 1], ['My Tickets', 'my-tickets', 'tickets', 0]] as [$title, $alias, $view, $show]) {
        $m = new Menu($db);
        $m->setLocation($after ?: 1, $after ? 'after' : 'last-child');
        $m->bind([
            'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias,
            'link' => 'index.php?option=com_ptickets&view=' . $view, 'type' => 'component',
            'published' => 1, 'parent_id' => 1, 'level' => 1, 'component_id' => (int) $component->id,
            'access' => 1, 'client_id' => 0, 'language' => '*', 'template_style_id' => 0, 'img' => '',
            'params' => json_encode(['menu_show' => $show]),
        ]);
        ($m->check() && $m->store()) or die("Menu $title failed: " . $m->getError());
        $m->rebuildPath($m->id);
        $after = (int) $m->id;
    }

    // Local testing: no activation e-mail needed for new accounts
    $db->setQuery("UPDATE #__extensions SET params=JSON_SET(params,'$.useractivation','0') WHERE element='com_users'")->execute();
    echo "Added support ticket menu items.\n";
}

if ($exists("SELECT COUNT(*) FROM #__menu WHERE alias='pricing' AND client_id=0")) {
    remoteSupport($db, $now);
    supportTickets($db);
    echo "Already seeded.\n";
    exit(0);
}

$servicesId = article($db, 'Services', 'services', <<<'HTML'
<p>Pamperins Tech is a locally owned IT company serving Green Bay, Appleton and the Fox Valley. Here is what we can help with:</p>
<h2>Remote Support</h2><p>Secure remote sessions for software problems, email, printers, slow computers and Linux/Windows/Mac questions.</p>
<h2>Networking</h2><p>Home and office Wi-Fi, mesh systems, cabling, switches and router setup.</p>
<h2>Cybersecurity</h2><p>Malware removal, backups, account security, and staff training.</p>
<h2>Web Development</h2><p>Small business websites, Joomla sites, hosting and maintenance.</p>
<h2>Network Security Device</h2><p>A managed firewall appliance that protects every device on your network.</p>
HTML, $now);

$pricingId = article($db, 'Pricing', 'pricing', <<<'HTML'
<p>Simple monthly plans for homes and small offices. One-time visits are always available too.</p>
<table class="pt-pricing">
<thead><tr><th>Plan</th><th>Basic</th><th>Business</th><th>Premium</th></tr></thead>
<tbody>
<tr><td>Monthly price</td><td><span class="price">$29<small>/mo</small></span></td><td><span class="price">$79<small>/mo</small></span></td><td><span class="price">$149<small>/mo</small></span></td></tr>
<tr><td>Devices covered</td><td>2</td><td>10</td><td>25</td></tr>
<tr><td>Remote support</td><td>Business hours</td><td>Priority, same day</td><td>Priority + after hours</td></tr>
<tr><td>On-site visits</td><td>Discounted</td><td>1 per month</td><td>2 per month</td></tr>
<tr><td>Network Security Device</td><td>—</td><td>Included</td><td>Included</td></tr>
<tr><td>Backup monitoring</td><td>—</td><td>✓</td><td>✓</td></tr>
</tbody>
</table>
<h2>One-time services</h2>
<table>
<thead><tr><th>Service</th><th>Price</th><th>Notes</th></tr></thead>
<tbody>
<tr><td>Remote session</td><td>$45</td><td>First 30 minutes</td></tr>
<tr><td>On-site visit</td><td>$90/hr</td><td>Green Bay &amp; Appleton area</td></tr>
<tr><td>Virus / malware cleanup</td><td>$99</td><td>Flat rate</td></tr>
</tbody>
</table>
HTML, $now);

// Contact
$contact = new ContactTable($db);
$contact->bind([
    'name' => 'Pamperins Tech Support', 'alias' => 'support', 'con_position' => 'Help Desk',
    'address' => '123 Example St', 'suburb' => 'Green Bay', 'state' => 'WI', 'postcode' => '54301', 'country' => 'USA',
    'telephone' => '(920) 555-0100', 'email_to' => 'help@example.com', 'misc' => '<p>Tell us what is going on and we will get back to you within one business day.</p>',
    'published' => 1, 'catid' => (int) $db->setQuery("SELECT id FROM #__categories WHERE extension='com_contact' ORDER BY id LIMIT 1")->loadResult(),
    'access' => 1, 'language' => '*', 'created' => $now, 'modified' => $now, 'publish_up' => $now,
    'params' => json_encode(['show_email_form' => 1, 'show_contact_list' => 0, 'show_links' => 0, 'show_tags' => 0, 'show_info' => 1]),
    'metadata' => '{"robots":"","rights":""}', 'metakey' => '', 'metadesc' => '',
]);
$contact->store() or die('Contact failed: ' . $contact->getError());
$contactId = (int) $contact->id;

// Menu items (main menu)
$cid = fn ($c) => (int) ComponentHelper::getComponent($c)->id;
$items = [
    ['Services', 'services', 'index.php?option=com_content&view=article&id=' . $servicesId, 'com_content', 1],
    ['Pricing', 'pricing', 'index.php?option=com_content&view=article&id=' . $pricingId, 'com_content', 1],
    ['Contact', 'contact', 'index.php?option=com_contact&view=contact&id=' . $contactId, 'com_contact', 1],
    ['Get Help', 'get-help', 'index.php?option=com_contact&view=contact&id=' . $contactId, 'com_contact', 0],
    ['Customer Login', 'login', 'index.php?option=com_users&view=login', 'com_users', 0],
];

foreach ($items as [$title, $alias, $link, $component, $show]) {
    $m = new Menu($db);
    $m->setLocation(1, 'last-child');
    $m->bind([
        'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'link' => $link, 'type' => 'component',
        'published' => 1, 'parent_id' => 1, 'level' => 1, 'component_id' => $cid($component), 'access' => 1,
        'client_id' => 0, 'language' => '*', 'template_style_id' => 0, 'img' => '',
        'params' => json_encode(['menu_show' => $show, 'show_page_heading' => 0, 'menu-anchor_css' => '', 'show_category' => '0', 'show_author' => '0', 'show_publish_date' => '0', 'show_create_date' => '0', 'show_modify_date' => '0', 'show_hits' => '0', 'show_item_navigation' => '0', 'show_parent_category' => '0']),
    ]);
    ($m->check() && $m->store()) or die("Menu $title failed: " . $m->getError());
    $m->rebuildPath($m->id);
}

remoteSupport($db, $now);
supportTickets($db);

// Main menu module -> header "menu" position, collapsible layout; hide sidebar login form
$db->setQuery("UPDATE #__modules SET position='menu', showtitle=0, params=JSON_SET(params,'$.layout','cassiopeia:collapse-metismenu') WHERE id=1")->execute();
$db->setQuery("UPDATE #__modules SET published=0 WHERE module='mod_login' AND client_id=0")->execute();

// Make Pamperins the default site template
$db->setQuery("UPDATE #__template_styles SET home='0' WHERE client_id=0")->execute();
$db->setQuery("UPDATE #__template_styles SET home='1' WHERE client_id=0 AND template='pamperins'")->execute();

// Allow customer registration so the registration view can be tested
$db->setQuery("UPDATE #__extensions SET params=JSON_SET(params,'$.allowUserRegistration','1') WHERE element='com_users'")->execute();

// Friendly URLs work out of the box with the Apache image if .htaccess exists
if (!is_file(JPATH_ROOT . '/.htaccess')) {
    copy(JPATH_ROOT . '/htaccess.txt', JPATH_ROOT . '/.htaccess');
}

echo "Seeded: services=$servicesId pricing=$pricingId contact=$contactId\n";
