<?php

/**
 * Pamperins Tech override of com_users profile: same as core, but with its own heading
 * instead of the one borrowed from the Home menu item when the page has no menu item of its own.
 *
 * @copyright   (C) 2026 Pamperins Tech; based on Joomla core (C) Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Profile\HtmlView $this */

$ptActive  = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
$ptOwnItem = $ptActive && ($ptActive->query['option'] ?? '') === 'com_users' && ($ptActive->query['view'] ?? '') === 'profile';
$heading   = $ptOwnItem && $this->params->get('page_heading') ? $this->params->get('page_heading') : Text::_('TPL_PAMPERINS_PROFILE_TITLE');

// Same for the browser tab title (respects Global Configuration "Site Name in Page Titles")
if (!$ptOwnItem) {
    $this->setDocumentTitle($heading);
}
$showHead  = !$ptOwnItem || $this->params->get('show_page_heading');
?>
<div class="com-users-profile profile">
    <?php if ($showHead) : ?>
        <div class="page-header">
            <h1>
                <?php echo $this->escape($heading); ?>
            </h1>
        </div>
    <?php endif; ?>

    <?php if ($this->getCurrentUser()->id == $this->data->id) : ?>
        <ul class="com-users-profile__edit btn-toolbar float-end">
            <li class="btn-group">
                <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_users&task=profile.edit&user_id=' . (int) $this->data->id); ?>">
                    <span class="icon-user-edit" aria-hidden="true"></span> <?php echo Text::_('COM_USERS_EDIT_PROFILE'); ?>
                </a>
            </li>
        </ul>
    <?php endif; ?>

    <?php echo $this->loadTemplate('core'); ?>
    <?php echo $this->loadTemplate('params'); ?>
    <?php echo $this->loadTemplate('custom'); ?>
</div>
