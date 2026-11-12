<?php

/**
 * First-time content setup for a FRESH Joomla site with the Pamperins template and
 * Support Tickets installed. Creates the pages, menus and settings so the site works
 * straight away; everything can be edited in the admin afterwards.
 *
 * Safe to run again: anything that already exists (same alias) is left alone.
 * Don't run it on a site that already has real content you set up by hand.
 *
 *   sudo -u www-data php setup-site.php --joomla=/var/www/new.pamperins.com \
 *        [--email=help@pamperins.com] [--phone="(920) 555-0100"] [--content=<folder with remote-support.html>]
 *
 *   --local   test-site shortcuts (no account activation e-mail). Never use on a real site.
 *
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

$opt = [];

foreach (\array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
    if (preg_match('/^--(joomla|email|phone|content)=(.*)$/s', $arg, $m)) {
        $opt[$m[1]] = $m[2];
    } elseif ($arg === '--local') {
        $opt['local'] = true;
    } else {
        fwrite(STDERR, "Unknown option: $arg\n");
        exit(1);
    }
}

if (empty($opt['joomla']) || !is_file(rtrim($opt['joomla'], '/') . '/configuration.php')) {
    fwrite(STDERR, "Usage: php setup-site.php --joomla=/path/to/joomla [--email=..] [--phone=..] [--content=..] [--local]\n");
    exit(1);
}

const _JEXEC = 1;
define('JPATH_BASE', rtrim($opt['joomla'], '/'));
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

$db      = $container->get(DatabaseInterface::class);
$now     = Factory::getDate()->toSql();
$local   = isset($opt['local']);
$config  = Factory::getApplication()->getConfig();
$email   = trim((string) ($opt['email'] ?? '')) ?: (string) $config->get('mailfrom');
$phone   = trim((string) ($opt['phone'] ?? '')) ?: '(920) 555-0100';
$content = rtrim((string) ($opt['content'] ?? (__DIR__ . '/../content')), '/');
$q       = fn (string $sql) => $db->setQuery($sql)->loadResult();
$say     = fn (string $s) => print("  $s\n");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid e-mail address: $email\n");
    exit(1);
}

foreach (['pamperins' => "SELECT COUNT(*) FROM #__template_styles WHERE template='pamperins' AND client_id=0",
          'com_ptickets' => "SELECT COUNT(*) FROM #__extensions WHERE element='com_ptickets'"] as $name => $sql) {
    if (!(int) $q($sql)) {
        fwrite(STDERR, "Install $name first (pamperins-template.zip / pamperins-support-tickets.zip).\n");
        exit(1);
    }
}

$authorId    = (int) $q("SELECT u.id FROM #__users u JOIN #__user_usergroup_map m ON m.user_id=u.id WHERE m.group_id=8 ORDER BY u.id LIMIT 1");
$hideInfo    = ['show_category' => '0', 'show_author' => '0', 'show_publish_date' => '0', 'show_create_date' => '0',
                'show_modify_date' => '0', 'show_hits' => '0', 'show_item_navigation' => '0', 'show_parent_category' => '0'];
$menuIdOf    = fn (string $alias) => (int) $q("SELECT id FROM #__menu WHERE alias=" . $db->quote($alias) . " AND client_id=0 AND menutype='mainmenu'");
$componentId = fn (string $c) => (int) ComponentHelper::getComponent($c)->id;

echo "Setting up " . JPATH_BASE . ($local ? " (LOCAL TEST MODE)" : '') . "\n";

function pt_article(DatabaseInterface $db, string $title, string $alias, string $html, string $now, int $author, array $attribs): int
{
    $existing = (int) $db->setQuery('SELECT id FROM #__content WHERE alias = ' . $db->quote($alias))->loadResult();

    if ($existing) {
        return $existing;
    }

    $t = new Content($db);
    $t->bind([
        'title' => $title, 'alias' => $alias, 'introtext' => $html, 'fulltext' => '',
        'state' => 1, 'catid' => 2, 'access' => 1, 'language' => '*', 'created' => $now,
        'created_by' => $author, 'modified' => $now, 'publish_up' => $now,
        'attribs' => json_encode($attribs), 'metadata' => '{"robots":""}', 'images' => '{}', 'urls' => '{}', 'metakey' => '', 'metadesc' => '',
    ]);
    $t->store() or die('Article failed: ' . $t->getError() . "\n");
    print("  + article: $title\n");

    return (int) $t->id;
}

function pt_menu(DatabaseInterface $db, string $title, string $alias, string $link, int $componentId, bool $show, array $params, int $after = 0): int
{
    $existing = (int) $db->setQuery("SELECT id FROM #__menu WHERE alias=" . $db->quote($alias) . " AND client_id=0 AND menutype='mainmenu'")->loadResult();

    if ($existing) {
        return $existing;
    }

    $m = new Menu($db);
    $m->setLocation($after ?: 1, $after ? 'after' : 'last-child');
    $m->bind([
        'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'link' => $link, 'type' => 'component',
        'published' => 1, 'parent_id' => 1, 'level' => 1, 'component_id' => $componentId, 'access' => 1,
        'client_id' => 0, 'language' => '*', 'template_style_id' => 0, 'img' => '',
        'params' => json_encode(['menu_show' => $show ? 1 : 0, 'show_page_heading' => 0] + $params),
    ]);
    ($m->check() && $m->store()) or die("Menu $title failed: " . $m->getError() . "\n");
    $m->rebuildPath($m->id);
    print("  + menu item: $title" . ($show ? '' : ' (hidden)') . "\n");

    return (int) $m->id;
}

// ---------- Pages ----------
$servicesId = pt_article($db, 'Services', 'services', <<<'HTML'
<p>Pamperins Tech is a locally owned IT company serving Green Bay, Appleton and the Fox Valley. Here is what we can help with:</p>
<h2>Remote Support</h2><p>Secure remote sessions for software problems, email, printers, slow computers and Linux/Windows/Mac questions.</p>
<h2>Networking</h2><p>Home and office Wi-Fi, mesh systems, cabling, switches and router setup.</p>
<h2>Cybersecurity</h2><p>Malware removal, backups, account security, and staff training.</p>
<h2>Web Development</h2><p>Small business websites, Joomla sites, hosting and maintenance.</p>
<h2>Software Development, AI &amp; Automation</h2><p>Custom software, practical AI tools, and home or industrial automation.</p>
<h2>Network Security Device</h2><p>A managed firewall appliance that protects every device on your network.</p>
HTML, $now, $authorId, $hideInfo);

$pricingId = pt_article($db, 'Pricing', 'pricing', <<<'HTML'
<p>Simple monthly plans for homes and small offices. One-time visits are always available too. <em>(Example prices — edit this page.)</em></p>
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
HTML, $now, $authorId, $hideInfo);

$remoteHtml = is_file("$content/remote-support.html") ? file_get_contents("$content/remote-support.html") : '<p>Remote support page — paste content/remote-support.html here.</p>';
$telDigits  = preg_replace('/[^0-9]/', '', $phone);
$remoteHtml = str_replace(['tel:+19205550100', '(920) 555-0100', 'help@pamperins.com'], ['tel:+1' . substr($telDigits, -10), $phone, $email], $remoteHtml);
$remoteId   = pt_article($db, 'Remote Support from Pamperins Tech', 'remote-support', $remoteHtml, $now, $authorId, $hideInfo);

// ---------- Contact ----------
$contactId = (int) $q("SELECT id FROM #__contact_details WHERE alias='support'");

if (!$contactId) {
    $contact = new ContactTable($db);
    $contact->bind([
        'name' => 'Pamperins Tech Support', 'alias' => 'support', 'con_position' => 'Help Desk',
        'suburb' => 'Green Bay & Appleton', 'state' => 'WI', 'country' => 'USA', 'address' => '', 'postcode' => '',
        'telephone' => $phone, 'email_to' => $email, 'misc' => '<p>Tell us what is going on and we will get back to you within one business day.</p>',
        'published' => 1, 'catid' => (int) $q("SELECT id FROM #__categories WHERE extension='com_contact' ORDER BY id LIMIT 1"),
        'access' => 1, 'language' => '*', 'created' => $now, 'created_by' => $authorId, 'modified' => $now, 'publish_up' => $now,
        'params' => json_encode(['show_email_form' => 1, 'show_contact_list' => 0, 'show_links' => 0, 'show_tags' => 0, 'show_info' => 1]),
        'metadata' => '{"robots":"","rights":""}', 'metakey' => '', 'metadesc' => '',
    ]);
    $contact->store() or die('Contact failed: ' . $contact->getError() . "\n");
    $contactId = (int) $contact->id;
    $say("+ contact: Pamperins Tech Support ($email)");
}

// ---------- Main menu (Home already exists in a fresh Joomla) ----------
$article = $componentId('com_content');
$after   = pt_menu($db, 'Services', 'services', 'index.php?option=com_content&view=article&id=' . $servicesId, $article, true, $hideInfo);
$after   = pt_menu($db, 'Remote Support', 'remote-support', 'index.php?option=com_content&view=article&id=' . $remoteId, $article, true, $hideInfo, $after);
$after   = pt_menu($db, 'Pricing', 'pricing', 'index.php?option=com_content&view=article&id=' . $pricingId, $article, true, $hideInfo, $after);
$after   = pt_menu($db, 'Contact', 'contact', 'index.php?option=com_contact&view=contact&id=' . $contactId, $componentId('com_contact'), true, [], $after);
$after   = pt_menu($db, 'Request Support', 'request-support', 'index.php?option=com_ptickets&view=request', $componentId('com_ptickets'), false, [], $after);
$after   = pt_menu($db, 'My Tickets', 'my-tickets', 'index.php?option=com_ptickets&view=tickets', $componentId('com_ptickets'), false, [], $after);
pt_menu($db, 'Customer Login', 'login', 'index.php?option=com_users&view=login', $componentId('com_users'), false, [], $after);

// ---------- Modules ----------
$db->setQuery("UPDATE #__modules SET position='menu', showtitle=0, params=JSON_SET(params,'$.layout','cassiopeia:collapse-metismenu')
    WHERE module='mod_menu' AND client_id=0 AND JSON_UNQUOTE(JSON_EXTRACT(params,'$.menutype'))='mainmenu'")->execute();
$db->setQuery("UPDATE #__modules SET published=0 WHERE module IN ('mod_login','mod_breadcrumbs') AND client_id=0 AND position IN ('sidebar-right','sidebar-left')")->execute();
$say('main menu -> header (collapsible on phones); sidebar login box hidden');

// ---------- Template: default + contact details ----------
$params = json_decode((string) $q("SELECT params FROM #__template_styles WHERE template='pamperins' AND client_id=0 ORDER BY id LIMIT 1"), true) ?: [];
$params = array_merge($params, ['phone' => $phone, 'email' => $email, 'helpUrl' => 'request-support', 'remoteUrl' => 'remote-support']);
$styleQ = $db->getQuery(true)->update('#__template_styles')->set('params = ' . $db->quote(json_encode($params)))
    ->where("template='pamperins'")->where('client_id=0');
$db->setQuery($styleQ)->execute();
$db->setQuery("UPDATE #__template_styles SET home='0' WHERE client_id=0")->execute();
$db->setQuery("UPDATE #__template_styles SET home='1' WHERE client_id=0 AND template='pamperins'")->execute();
$say("template: Pamperins is the default; footer phone $phone, email $email");

// ---------- Accounts + tickets ----------
$activation = $local ? '0' : '1';   // 1 = customer confirms their e-mail address
$db->setQuery("UPDATE #__extensions SET params=JSON_SET(params,'$.allowUserRegistration','1','$.useractivation','$activation') WHERE element='com_users' AND type='component'")->execute();
$tParams = json_decode((string) $q("SELECT params FROM #__extensions WHERE element='com_ptickets'"), true) ?: [];
$tParams += ['notify_email' => $email, 'ref_prefix' => 'PT-', 'remote_url' => 'https://support.pamperins.com/get-help', 'customer_email' => '1'];
$db->setQuery($db->getQuery(true)->update('#__extensions')->set('params = ' . $db->quote(json_encode($tParams)))->where("element='com_ptickets'"))->execute();
$say('customer sign-up on' . ($local ? ' (no activation e-mail: LOCAL)' : ' (customers confirm their e-mail)') . "; ticket alerts -> {$tParams['notify_email']}");

// ---------- Friendly URLs ----------
if (!is_file(JPATH_ROOT . '/.htaccess') && is_file(JPATH_ROOT . '/htaccess.txt')) {
    copy(JPATH_ROOT . '/htaccess.txt', JPATH_ROOT . '/.htaccess');
    $say('.htaccess created');
}

echo "Done. Next: check the site, then edit Pricing, Services and the template's footer hours in the admin.\n";
