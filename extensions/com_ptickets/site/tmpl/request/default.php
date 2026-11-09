<?php

/**
 * Support request: 1) account check  2) choose the kind of help  3) the matching form
 *
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

/** @var \Pamperins\Component\Ptickets\Site\View\Request\HtmlView $this */

$requestUrl = 'index.php?option=com_ptickets&view=request';
$return     = base64_encode(Uri::getInstance()->toString());
?>
<div class="com-ptickets pt-request">

<?php if ($this->user->guest) : ?>
    <?php // ---------- Step 1: account needed ---------- ?>
    <div class="pt-auth">
        <div class="pt-auth__card">
            <div class="pt-auth__head">
                <span class="pt-auth__icon pt-auth__icon--user" aria-hidden="true"></span>
                <h1 class="pt-auth__title"><?php echo Text::_('COM_PTICKETS_GUEST_TITLE'); ?></h1>
                <p class="pt-auth__intro"><?php echo Text::_('COM_PTICKETS_GUEST_INTRO'); ?></p>
            </div>
            <div class="pt-auth__form pt-request__guest">
                <?php if (ComponentHelper::getParams('com_users')->get('allowUserRegistration')) : ?>
                    <a class="btn btn-primary w-100" href="<?php echo Route::_('index.php?option=com_users&view=registration'); ?>"><?php echo Text::_('COM_PTICKETS_GUEST_REGISTER'); ?></a>
                <?php endif; ?>
                <a class="btn btn-secondary w-100" href="<?php echo Route::_('index.php?option=com_users&view=login&return=' . $return); ?>"><?php echo Text::_('COM_PTICKETS_GUEST_LOGIN'); ?></a>
            </div>
            <p class="pt-auth__links"><?php echo Text::_('COM_PTICKETS_GUEST_URGENT'); ?></p>
        </div>
    </div>

<?php elseif (!$this->type) : ?>
    <?php // ---------- Step 2: what kind of help? ---------- ?>
    <div class="pt-request__head">
        <h1><?php echo Text::_('COM_PTICKETS_CHOOSE_TITLE'); ?></h1>
        <p class="pt-lead"><?php echo Text::_('COM_PTICKETS_CHOOSE_INTRO'); ?></p>
        <p class="pt-request__mine"><a href="<?php echo Route::_('index.php?option=com_ptickets&view=tickets'); ?>"><?php echo Text::_('COM_PTICKETS_VIEW_MY_TICKETS'); ?></a></p>
    </div>

    <?php foreach (SupportTypes::grouped() as $group => $types) : ?>
        <h2 class="pt-request__group"><?php echo Text::_('COM_PTICKETS_GROUP_' . strtoupper($group)); ?></h2>
        <div class="pt-cards pt-typecards">
            <?php foreach ($types as $key) : ?>
                <a class="pt-card pt-typecard pt-card--t-<?php echo $key; ?>" href="<?php echo Route::_($requestUrl . '&type=' . $key); ?>">
                    <h3><?php echo SupportTypes::title($key); ?></h3>
                    <p><?php echo SupportTypes::description($key); ?></p>
                    <p><span class="pt-card__link"><?php echo Text::_('COM_PTICKETS_START'); ?></span></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

<?php else : ?>
    <?php // ---------- Step 3: the form for this kind of job ---------- ?>
    <?php
    $wa = $this->getDocument()->getWebAssetManager();
    $wa->useScript('keepalive')->useScript('form.validate');
    ?>
    <p class="pt-request__back"><a href="<?php echo Route::_($requestUrl); ?>">&larr; <?php echo Text::_('COM_PTICKETS_CHANGE_TYPE'); ?></a></p>

    <div class="pt-request__head pt-request__head--type pt-card--t-<?php echo $this->type; ?>">
        <h1><?php echo SupportTypes::title($this->type); ?></h1>
        <p class="pt-lead"><?php echo SupportTypes::description($this->type); ?></p>
    </div>

    <form action="<?php echo Route::_($requestUrl . '&type=' . $this->type); ?>" method="post" class="form-validate pt-request__form" id="pt-request-form">
        <?php foreach (['details', 'summary', 'contact'] as $fieldset) : ?>
            <?php $fs = $this->form->getFieldsets()[$fieldset] ?? null; ?>
            <?php if ($fs) : ?>
                <fieldset class="pt-request__fieldset pt-request__fieldset--<?php echo $fieldset; ?>">
                    <legend><?php echo Text::_($fs->label); ?></legend>
                    <?php echo $this->form->renderFieldset($fieldset); ?>
                </fieldset>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="pt-request__submit">
            <button type="submit" class="btn btn-primary validate"><?php echo Text::_('COM_PTICKETS_SUBMIT'); ?></button>
            <p class="pt-request__as"><?php echo Text::sprintf('COM_PTICKETS_SUBMITTING_AS', $this->escape($this->user->name), $this->escape($this->user->email)); ?></p>
        </div>

        <input type="hidden" name="option" value="com_ptickets">
        <input type="hidden" name="task" value="request.submit">
        <input type="hidden" name="type" value="<?php echo $this->escape($this->type); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>

</div>
