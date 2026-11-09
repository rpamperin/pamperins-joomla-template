<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.pamperins
 *
 * Child of Cassiopeia. Reuses Cassiopeia's CSS/JS assets (so core updates still
 * apply) and adds the Pamperins Tech header, home hero/sections and footer.
 * All visual styling lives in media/templates/site/pamperins/css/user.css.
 *
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var Joomla\CMS\Document\HtmlDocument $this */

$app    = Factory::getApplication();
$input  = $app->getInput();
$wa     = $this->getWebAssetManager();
$params = $this->params;

$option    = $input->getCmd('option', '');
$view      = $input->getCmd('view', '');
$layout    = $input->getCmd('layout', '');
$task      = $input->getCmd('task', '');
$itemid    = $input->getCmd('Itemid', '');
$sitename  = htmlspecialchars($app->get('sitename'), ENT_QUOTES, 'UTF-8');
$menu      = $app->getMenu()->getActive();
$pageclass = $menu !== null ? $menu->getParams()->get('pageclass_sfx', '') : '';
$default   = $app->getMenu()->getDefault($this->language);
$isHome    = $menu !== null && $default !== null && (int) $menu->id === (int) $default->id;

/**
 * Turn a URL from the template settings into a usable href.
 * - "index.php?..."  -> routed through Joomla (SEF aware)
 * - "https://...", "mailto:", "tel:", "/path", "#anchor" -> used as is
 * - "get-help"        -> relative to the site root
 */
$pt_href = function (string $url): string {
    $url = trim($url);

    if ($url === '') {
        return '#';
    }

    if (stripos($url, 'index.php') === 0) {
        return Route::_($url);
    }

    if (preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $url)) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars(rtrim(Uri::root(true), '/') . '/' . $url, ENT_QUOTES, 'UTF-8');
};

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Favicon (ships with this template; falls back to Cassiopeia's via the media lookup)
$this->addHeadLink(HTMLHelper::_('image', 'favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);

// Reuse Cassiopeia's stylesheet + scripts, then our user.css (loaded last)
$wa->usePreset('template.cassiopeia.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr'))
    ->useStyle('template.active.language')
    ->registerAndUseStyle('theme.colors_standard', 'global/colors_standard.css')
    ->useStyle('template.user')
    ->useScript('template.user');

$wa->registerStyle('template.active', '', [], [], ['template.cassiopeia.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr')]);

// Preload the self-hosted Inter font so text doesn't flash
$this->getPreloadManager()->preload(Uri::root(true) . '/media/templates/site/pamperins/fonts/inter-latin-wght-normal.woff2', ['as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous']);

// Logo: one of the bundled Pamperins logos, or a custom file.
// Styles saved before the picker existed keep a custom logo if one was chosen.
$logoPreset = $params->get('logoPreset');

if ($logoPreset === null) {
    $logoFile   = (string) $params->get('logoFile', '');
    $logoPreset = ($logoFile !== '' && $logoFile !== 'images/logo.svg') ? 'custom' : '2-pt-monogram';
}

if ($logoPreset !== 'custom' && preg_match('/^[0-9a-z-]+$/', $logoPreset)) {
    $logo = HTMLHelper::_('image', 'logos/' . $logoPreset . '.svg', $sitename, ['class' => 'pt-logo pt-logo--' . $logoPreset, 'loading' => 'eager', 'decoding' => 'async'], true, 0);
} elseif ($params->get('logoFile')) {
    $logo = HTMLHelper::_('image', Uri::root(false) . htmlspecialchars($params->get('logoFile'), ENT_QUOTES), $sitename, ['class' => 'pt-logo', 'loading' => 'eager', 'decoding' => 'async'], false, 0);
} else {
    $logo = HTMLHelper::_('image', 'logo.svg', $sitename, ['class' => 'pt-logo', 'loading' => 'eager', 'decoding' => 'async'], true, 0);
}

$hasClass = '';

if ($this->countModules('sidebar-left', true)) {
    $hasClass .= ' has-sidebar-left';
}

if ($this->countModules('sidebar-right', true)) {
    $hasClass .= ' has-sidebar-right';
}

$this->setMetaData('viewport', 'width=device-width, initial-scale=1');
$this->setMetaData('theme-color', '#0b1d3a');

$stickyHeader = $params->get('stickyHeader', 1) ? ' position-sticky sticky-top' : '';

$wa->getAsset('style', 'fontawesome')->setAttribute('rel', 'lazy-stylesheet');

// Footer data
$phone    = trim((string) $params->get('phone', ''));
$phoneTel = preg_replace('/[^0-9+]/', '', $phone);
$email    = trim((string) $params->get('email', ''));
$area     = trim((string) $params->get('serviceArea', ''));
$hours    = trim((string) $params->get('hours', ''));
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body id="top" class="site pamperins <?php echo $option
    . ' wrapper-static'
    . ' view-' . $view
    . ($layout ? ' layout-' . $layout : ' no-layout')
    . ($task ? ' task-' . $task : ' no-task')
    . ($itemid ? ' itemid-' . $itemid : '')
    . ($pageclass ? ' ' . $pageclass : '')
    . ($isHome ? ' pt-home' : '')
    . $hasClass
    . ($this->direction == 'rtl' ? ' rtl' : '');
?>">
    <a class="pt-skip visually-hidden-focusable" href="#pt-main"><?php echo Text::_('TPL_PAMPERINS_SKIP_TO_CONTENT'); ?></a>

    <header class="header container-header full-width pt-header<?php echo $stickyHeader; ?>">
        <?php if ($this->countModules('topbar')) : ?>
            <div class="container-topbar">
                <jdoc:include type="modules" name="topbar" style="none" />
            </div>
        <?php endif; ?>

        <div class="pt-header__inner">
            <?php if ($params->get('brand', 1)) : ?>
                <a class="pt-brand" href="<?php echo $this->baseurl; ?>/">
                    <?php echo $logo; ?>
                </a>
            <?php endif; ?>

            <?php if ($this->countModules('menu', true)) : ?>
                <div class="pt-header__menu">
                    <jdoc:include type="modules" name="menu" style="none" />
                </div>
            <?php endif; ?>

            <div class="pt-header__actions">
                <a class="btn pt-btn pt-btn--accent" href="<?php echo $pt_href($params->get('helpUrl', 'request-support')); ?>">
                    <?php echo $e($params->get('helpLabel', 'Get Help Now')); ?>
                </a>
                <a class="btn pt-btn pt-btn--ghost" href="<?php echo $pt_href($params->get('loginUrl', 'index.php?option=com_users&view=login')); ?>">
                    <?php echo $e($params->get('loginLabel', 'Customer Login')); ?>
                </a>
            </div>
        </div>
    </header>

    <?php if ($isHome && $params->get('heroShow', 1)) : ?>
        <section class="pt-hero" aria-labelledby="pt-hero-title">
            <div class="pt-hero__inner">
                <?php if ($params->get('heroEyebrow')) : ?>
                    <p class="pt-eyebrow"><?php echo $e($params->get('heroEyebrow')); ?></p>
                <?php endif; ?>
                <h1 id="pt-hero-title" class="pt-hero__title"><?php echo $e($params->get('heroTitle')); ?></h1>
                <?php if ($params->get('heroText')) : ?>
                    <p class="pt-hero__text"><?php echo $e($params->get('heroText')); ?></p>
                <?php endif; ?>
                <div class="pt-hero__actions">
                    <a class="btn pt-btn pt-btn--accent pt-btn--lg" href="<?php echo $pt_href($params->get('bookingUrl', '#book')); ?>">
                        <?php echo $e($params->get('bookingLabel', 'Book an Appointment')); ?>
                    </a>
                    <?php if ($params->get('remoteUrl')) : ?>
                        <a class="btn pt-btn pt-btn--ghost pt-btn--lg" href="<?php echo $pt_href($params->get('remoteUrl')); ?>">
                            <?php echo $e($params->get('remoteLabel', 'Get Remote Support')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('pt-services', true)) : ?>
        <section class="pt-section pt-section--services">
            <div class="pt-section__inner">
                <jdoc:include type="modules" name="pt-services" style="none" />
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('pt-why', true)) : ?>
        <section class="pt-section pt-section--dark pt-section--why">
            <div class="pt-section__inner">
                <jdoc:include type="modules" name="pt-why" style="none" />
            </div>
        </section>
    <?php endif; ?>

    <div class="site-grid">
        <?php if ($this->countModules('banner', true)) : ?>
            <div class="container-banner full-width">
                <jdoc:include type="modules" name="banner" style="none" />
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('top-a', true)) : ?>
            <div class="grid-child container-top-a">
                <jdoc:include type="modules" name="top-a" style="card" />
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('top-b', true)) : ?>
            <div class="grid-child container-top-b">
                <jdoc:include type="modules" name="top-b" style="card" />
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('sidebar-left', true)) : ?>
            <div class="grid-child container-sidebar-left">
                <jdoc:include type="modules" name="sidebar-left" style="card" />
            </div>
        <?php endif; ?>

        <div class="grid-child container-component" id="pt-main">
            <jdoc:include type="modules" name="breadcrumbs" style="none" />
            <jdoc:include type="modules" name="main-top" style="card" />
            <jdoc:include type="message" />
            <main>
                <jdoc:include type="component" />
            </main>
            <jdoc:include type="modules" name="main-bottom" style="card" />
        </div>

        <?php if ($this->countModules('sidebar-right', true)) : ?>
            <div class="grid-child container-sidebar-right">
                <jdoc:include type="modules" name="sidebar-right" style="card" />
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('bottom-a', true)) : ?>
            <div class="grid-child container-bottom-a">
                <jdoc:include type="modules" name="bottom-a" style="card" />
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('bottom-b', true)) : ?>
            <div class="grid-child container-bottom-b">
                <jdoc:include type="modules" name="bottom-b" style="card" />
            </div>
        <?php endif; ?>
    </div>

    <footer class="container-footer footer full-width pt-footer">
        <div class="pt-footer__inner">
            <div class="pt-footer__col pt-footer__brand">
                <a class="pt-brand" href="<?php echo $this->baseurl; ?>/"><?php echo $logo; ?></a>
                <p><?php echo Text::_('TPL_PAMPERINS_FOOTER_TAGLINE'); ?></p>
            </div>

            <div class="pt-footer__col">
                <h2 class="pt-footer__heading"><?php echo Text::_('TPL_PAMPERINS_FOOTER_CONTACT'); ?></h2>
                <ul class="pt-footer__list">
                    <?php if ($phone) : ?>
                        <li class="pt-ico pt-ico--phone"><a href="tel:<?php echo $e($phoneTel); ?>"><?php echo $e($phone); ?></a></li>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <li class="pt-ico pt-ico--mail"><a href="mailto:<?php echo $e($email); ?>"><?php echo $e($email); ?></a></li>
                    <?php endif; ?>
                    <?php if ($params->get('remoteUrl')) : ?>
                        <li class="pt-ico pt-ico--remote"><a href="<?php echo $pt_href($params->get('remoteUrl')); ?>"><?php echo $e($params->get('remoteLabel', 'Get Remote Support')); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="pt-footer__col">
                <h2 class="pt-footer__heading"><?php echo Text::_('TPL_PAMPERINS_FOOTER_AREA'); ?></h2>
                <p class="pt-ico pt-ico--pin"><?php echo nl2br($e($area)); ?></p>
            </div>

            <div class="pt-footer__col">
                <h2 class="pt-footer__heading"><?php echo Text::_('TPL_PAMPERINS_FOOTER_HOURS'); ?></h2>
                <p class="pt-ico pt-ico--clock"><?php echo nl2br($e($hours)); ?></p>
            </div>
        </div>

        <?php if ($this->countModules('footer', true)) : ?>
            <div class="pt-footer__modules">
                <jdoc:include type="modules" name="footer" style="none" />
            </div>
        <?php endif; ?>

        <div class="pt-footer__bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $sitename; ?>. <?php echo Text::_('TPL_PAMPERINS_FOOTER_RIGHTS'); ?></p>
        </div>
    </footer>

    <?php if ($params->get('backTop', 1) == 1) : ?>
        <a href="#top" id="back-top" class="back-to-top-link" aria-label="<?php echo Text::_('TPL_CASSIOPEIA_BACKTOTOP'); ?>">
            <span class="icon-arrow-up icon-fw" aria-hidden="true"></span>
        </a>
    <?php endif; ?>

    <jdoc:include type="modules" name="debug" style="none" />
</body>

</html>
