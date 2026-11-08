<?php

/**
 * Pamperins Tech override of com_users password reset request (branded card).
 *
 * @copyright   (C) 2026 Pamperins Tech; based on Joomla core (C) Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Reset\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$heading = $this->params->get('show_page_heading') ? $this->params->get('page_heading') : Text::_('TPL_PAMPERINS_RESET_TITLE');
?>
<div class="com-users-reset reset pt-auth">
    <div class="pt-auth__card">
        <div class="pt-auth__head">
            <span class="pt-auth__icon pt-auth__icon--key" aria-hidden="true"></span>
            <h1 class="pt-auth__title"><?php echo $this->escape($heading); ?></h1>
            <p class="pt-auth__intro"><?php echo Text::_('TPL_PAMPERINS_RESET_INTRO'); ?></p>
        </div>

        <form action="<?php echo Route::_('index.php?task=reset.request'); ?>" method="post" id="user-registration" class="com-users-reset__form form-validate pt-auth__form">
            <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
                <fieldset>
                    <?php if (isset($fieldset->label)) : ?>
                        <legend class="visually-hidden"><?php echo Text::_($fieldset->label); ?></legend>
                    <?php endif; ?>
                    <?php echo $this->form->renderFieldset($fieldset->name); ?>
                </fieldset>
            <?php endforeach; ?>

            <div class="com-users-reset__submit control-group">
                <div class="controls">
                    <button type="submit" class="btn btn-primary w-100 validate">
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                </div>
            </div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <ul class="pt-auth__links">
            <li><a href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>"><?php echo Text::_('TPL_PAMPERINS_BACK_TO_LOGIN'); ?></a></li>
        </ul>
    </div>
</div>
