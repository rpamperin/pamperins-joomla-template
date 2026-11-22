<?php

/**
 * Pamperins Tech override of com_users logout (logged-in account page): same as core, but with its own heading
 * instead of the one borrowed from the Home menu item when the page has no menu item of its own.
 *
 * @copyright   (C) 2026 Pamperins Tech; based on Joomla core (C) Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Login\HtmlView $this */

$ptActive  = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
$ptOwnItem = $ptActive && ($ptActive->query['option'] ?? '') === 'com_users' && ($ptActive->query['view'] ?? '') === 'login';
$heading   = $ptOwnItem && $this->params->get('page_heading') ? $this->params->get('page_heading') : Text::_('TPL_PAMPERINS_ACCOUNT_TITLE');

// Same for the browser tab title (respects Global Configuration "Site Name in Page Titles")
if (!$ptOwnItem) {
    $this->setDocumentTitle($heading);
}
$showHead  = !$ptOwnItem || $this->params->get('show_page_heading');
?>
<div class="com-users-logout logout">
    <?php if ($showHead) : ?>
    <div class="page-header">
        <h1>
            <?php echo $this->escape($heading); ?>
        </h1>
    </div>
    <?php endif; ?>

    <?php if (($this->params->get('logoutdescription_show') == 1 && str_replace(' ', '', $this->params->get('logout_description', '')) != '') || $this->params->get('logout_image') != '') : ?>
        <div class="com-users-logout__description logout-description">
    <?php endif; ?>

    <?php if ($this->params->get('logoutdescription_show') == 1) : ?>
        <?php echo $this->params->get('logout_description'); ?>
    <?php endif; ?>

    <?php if ($this->params->get('logout_image') != '') : ?>
        <?php echo HTMLHelper::_('image', $this->params->get('logout_image'), empty($this->params->get('logout_image_alt')) && empty($this->params->get('logout_image_alt_empty')) ? false : $this->params->get('logout_image_alt'), ['class' => 'com-users-logout__image thumbnail float-end logout-image']); ?>
    <?php endif; ?>

    <?php if (($this->params->get('logoutdescription_show') == 1 && str_replace(' ', '', $this->params->get('logout_description', '')) != '') || $this->params->get('logout_image') != '') : ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?task=user.logout'); ?>" method="post" class="com-users-logout__form form-horizontal well">
        <div class="com-users-logout__submit control-group">
            <div class="controls">
                <button type="submit" class="btn btn-primary">
                    <span class="icon-backward-2 icon-white" aria-hidden="true"></span>
                    <?php echo Text::_('JLOGOUT'); ?>
                </button>
            </div>
        </div>
        <?php if ($this->params->get('logout_redirect_url')) : ?>
            <input type="hidden" name="return" value="<?php echo base64_encode($this->params->get('logout_redirect_url', $this->form->getValue('return', null, ''))); ?>">
        <?php else : ?>
            <input type="hidden" name="return" value="<?php echo base64_encode($this->params->get('logout_redirect_menuitem', $this->form->getValue('return', null, ''))); ?>">
        <?php endif; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
