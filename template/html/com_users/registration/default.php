<?php

/**
 * Pamperins Tech override of com_users registration form (branded card).
 *
 * @copyright   (C) 2026 Pamperins Tech; based on Joomla core (C) Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Registration\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$heading = $this->params->get('show_page_heading') ? $this->params->get('page_heading') : Text::_('TPL_PAMPERINS_REGISTER_TITLE');
?>
<div class="com-users-registration registration pt-auth pt-auth--wide">
    <div class="pt-auth__card">
        <div class="pt-auth__head">
            <span class="pt-auth__icon pt-auth__icon--user" aria-hidden="true"></span>
            <h1 class="pt-auth__title"><?php echo $this->escape($heading); ?></h1>
            <p class="pt-auth__intro"><?php echo Text::_('TPL_PAMPERINS_REGISTER_INTRO'); ?></p>
        </div>

        <form id="member-registration" action="<?php echo Route::_('index.php?task=registration.register'); ?>" method="post" class="com-users-registration__form form-validate pt-auth__form" enctype="multipart/form-data">
            <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
                <?php if ($fieldset->name === 'captcha' && $this->captchaEnabled) : ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php $fields = $this->form->getFieldset($fieldset->name); ?>
                <?php if (count($fields)) : ?>
                    <fieldset>
                        <?php if (isset($fieldset->label)) : ?>
                            <legend><?php echo Text::_($fieldset->label); ?></legend>
                        <?php endif; ?>
                        <?php echo $this->form->renderFieldset($fieldset->name); ?>
                    </fieldset>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($this->captchaEnabled) : ?>
                <?php echo $this->form->renderFieldset('captcha'); ?>
            <?php endif; ?>

            <div class="com-users-registration__submit control-group">
                <div class="controls">
                    <button type="submit" class="com-users-registration__register btn btn-primary w-100 validate">
                        <?php echo Text::_('JREGISTER'); ?>
                    </button>
                    <input type="hidden" name="option" value="com_users">
                    <input type="hidden" name="task" value="registration.register">
                </div>
            </div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <ul class="pt-auth__links">
            <li>
                <?php echo Text::_('TPL_PAMPERINS_HAVE_ACCOUNT'); ?>
                <a href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>"><?php echo Text::_('JLOGIN'); ?></a>
            </li>
        </ul>
    </div>
</div>
