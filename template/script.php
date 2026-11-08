<?php

/**
 * Pamperins Tech template installer script.
 *
 * On first install only:
 *  - copies the placeholder logo to /images/logo.svg (if that file doesn't exist yet)
 *  - creates the two home page modules ("Service Cards" and "Why Pamperins Tech")
 *    in this template's own positions (pt-services / pt-why), assigned to the home page.
 *    These positions only exist in this template, so nothing changes on a site
 *    that keeps using Cassiopeia.
 * Updates never touch your logo or edited modules.
 *
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Table\Module as ModuleTable;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface {
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type !== 'uninstall' && !is_dir(JPATH_SITE . '/templates/cassiopeia')) {
                        Factory::getApplication()->enqueueMessage('The Pamperins template needs the Cassiopeia template (parent) to be installed.', 'error');

                        return false;
                    }

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'discover_install'], true)) {
                        return true;
                    }

                    $this->copyLogo();
                    $this->fixStyleTitle();
                    $this->createHomeModules();

                    return true;
                }

                private function copyLogo(): void
                {
                    $src  = JPATH_ROOT . '/media/templates/site/pamperins/images/logo.svg';
                    $dest = JPATH_ROOT . '/images/logo.svg';

                    if (is_file($src) && !is_file($dest)) {
                        try {
                            File::copy($src, $dest);
                        } catch (\Throwable $e) {
                            // Not fatal: the template falls back to its bundled logo.
                        }
                    }
                }

                /** Give the style a readable name (CLI installs leave the raw language key). */
                private function fixStyleTitle(): void
                {
                    $db    = Factory::getContainer()->get(DatabaseInterface::class);
                    $title = 'Pamperins - Default';
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__template_styles'))
                        ->set($db->quoteName('title') . ' = :title')
                        ->where($db->quoteName('template') . ' = ' . $db->quote('pamperins'))
                        ->where($db->quoteName('client_id') . ' = 0')
                        ->where($db->quoteName('title') . ' LIKE ' . $db->quote('JLIB_%'))
                        ->bind(':title', $title);
                    $db->setQuery($query)->execute();
                }

                private function createHomeModules(): void
                {
                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__modules'))
                        ->whereIn($db->quoteName('position'), ['pt-services', 'pt-why'], \Joomla\Database\ParameterType::STRING)
                        ->where($db->quoteName('client_id') . ' = 0');

                    if ((int) $db->setQuery($query)->loadResult() > 0) {
                        return;
                    }

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__menu'))
                        ->where($db->quoteName('home') . ' = 1')
                        ->where($db->quoteName('client_id') . ' = 0');
                    $homeIds = $db->setQuery($query)->loadColumn() ?: [0];

                    $modules = [
                        ['Home - Service Cards', 'pt-services', $this->servicesHtml(), 1],
                        ['Home - Why Pamperins Tech', 'pt-why', $this->whyHtml(), 1],
                    ];

                    foreach ($modules as [$title, $position, $html, $ordering]) {
                        $table = new ModuleTable($db);
                        $table->bind([
                            'title'     => $title,
                            'note'      => 'Created by the Pamperins template',
                            'content'   => $html,
                            'position'  => $position,
                            'module'    => 'mod_custom',
                            'published' => 1,
                            'access'    => 1,
                            'showtitle' => 0,
                            'ordering'  => $ordering,
                            'client_id' => 0,
                            'language'  => '*',
                            'params'    => json_encode([
                                'prepare_content' => '0',
                                'layout'          => '_:default',
                                'moduleclass_sfx' => '',
                                'cache'           => 1,
                                'cache_time'      => 900,
                                'module_tag'      => 'div',
                                'bootstrap_size'  => '0',
                                'header_tag'      => 'h2',
                                'header_class'    => '',
                                'style'           => '0',
                            ]),
                        ]);

                        if (!$table->store()) {
                            continue;
                        }

                        foreach ($homeIds as $menuId) {
                            $row = (object) ['moduleid' => (int) $table->id, 'menuid' => (int) $menuId];
                            $db->insertObject('#__modules_menu', $row);
                        }
                    }
                }

                private function servicesHtml(): string
                {
                    return <<<'HTML'
<div class="pt-section__head">
<p class="pt-eyebrow">What we do</p>
<h2>IT services for home &amp; small business</h2>
<p>One local team for the tech that keeps your day moving.</p>
</div>
<div class="pt-cards">
<div class="pt-card pt-card--remote">
<h3>Remote Support</h3>
<p>Secure screen-sharing help for slow PCs, email, printers, software and Linux, Windows or Mac issues — often fixed the same day.</p>
<p><a class="pt-card__link" href="get-help">Get remote help</a></p>
</div>
<div class="pt-card pt-card--network">
<h3>Networking</h3>
<p>Wi-Fi that reaches every room, wired runs, switches and routers set up right for homes and offices.</p>
<p><a class="pt-card__link" href="get-help">Plan my network</a></p>
</div>
<div class="pt-card pt-card--security">
<h3>Cybersecurity</h3>
<p>Virus and malware cleanup, backups, password and account hardening, and staff phishing awareness.</p>
<p><a class="pt-card__link" href="get-help">Secure my devices</a></p>
</div>
<div class="pt-card pt-card--web">
<h3>Web Development</h3>
<p>Fast, mobile-friendly websites, hosting and updates for local businesses — built to be easy to edit.</p>
<p><a class="pt-card__link" href="get-help">Start a website</a></p>
</div>
<div class="pt-card pt-card--device">
<h3>Network Security Device</h3>
<p>A managed firewall appliance that blocks threats, ads and trackers for your whole home or office network.</p>
<p><a class="pt-card__link" href="get-help">Ask about the device</a></p>
</div>
</div>
HTML;
                }

                private function whyHtml(): string
                {
                    return <<<'HTML'
<div class="pt-why">
<div class="pt-why__intro">
<p class="pt-eyebrow">Why Pamperins Tech</p>
<h2>Local people. Straight answers. No runaround.</h2>
<p>We live and work in the Fox Valley. When you call, you talk to the person who will actually fix it.</p>
<p><a class="btn pt-btn pt-btn--accent" href="#book">Book an Appointment</a></p>
</div>
<ul class="pt-why__list">
<li><strong>Locally owned</strong> Serving Green Bay, Appleton and the surrounding area.</li>
<li><strong>Fast response</strong> Remote sessions can start in minutes; on-site visits by appointment.</li>
<li><strong>Clear pricing</strong> Up-front quotes before any work starts.</li>
<li><strong>Every platform</strong> Windows, macOS, Linux, phones, printers and networks.</li>
</ul>
</div>
HTML;
                }
            }
        );
    }
};
