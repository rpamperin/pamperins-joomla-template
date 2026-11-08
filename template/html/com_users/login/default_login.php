<?php

/**
 * Pamperins Tech override of com_users login form.
 * Same fields, tokens and plugin buttons (passkeys/MFA) as core — only the markup
 * around them changes so it renders as a branded card.
 *
 * @copyright   (C) 2026 Pamperins Tech; based on Joomla core (C) Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Login\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$usersConfig = ComponentHelper::getParams('com_users');
$tplParams   = Factory::getApplication()->getTemplate(true)->params;
$phone       = trim((string) $tplParams->get('phone', ''));
$heading     = $this->params->get('show_page_heading') ? $this->params->get('page_heading') : Text::_('TPL_PAMPERINS_LOGIN_TITLE');
?>
<div class="com-users-login login pt-auth">
    <div class="pt-auth__card">
        <div class="pt-auth__head">
            <span class="pt-auth__icon pt-auth__icon--lock" aria-hidden="true"></span>
            <h1 class="pt-auth__title"><?php echo $this->escape($heading); ?></h1>
            <?php if ($this->params->get('logindescription_show') == 1 && trim($this->params->get('login_description', ''))) : ?>
                <div class="pt-auth__intro com-users-login__description"><?php echo $this->params->get('login_description'); ?></div>
            <?php else : ?>
                <p class="pt-auth__intro"><?php echo Text::_('TPL_PAMPERINS_LOGIN_INTRO'); ?></p>
            <?php endif; ?>
        </div>

        <form action="<?php echo Route::_('index.php?task=user.login'); ?>" method="post" id="com-users-login__form" class="com-users-login__form form-validate pt-auth__form">
            <fieldset>
                <legend class="visually-hidden"><?php echo $this->escape($heading); ?></legend>
                <?php echo $this->form->renderFieldset('credentials', ['class' => 'com-users-login__input']); ?>

                <?php if (PluginHelper::isEnabled('system', 'remember')) : ?>
                    <div class="com-users-login__remember">
                        <div class="form-check">
                            <input class="form-check-input" id="remember" type="checkbox" name="remember" value="yes">
                            <label class="form-check-label" for="remember">
                                <?php echo Text::_('COM_USERS_LOGIN_REMEMBER_ME'); ?>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="com-users-login__submit control-group">
                    <div class="controls">
                        <button type="submit" class="btn btn-primary w-100">
                            <?php echo Text::_('JLOGIN'); ?>
                        </button>
                    </div>
                </div>

                <?php foreach ($this->extraButtons as $button) :
                    $dataAttributeKeys = array_filter(array_keys($button), function ($key) {
                        return substr($key, 0, 5) == 'data-';
                    });
                    ?>
                    <div class="com-users-login__submit control-group">
                        <div class="controls">
                            <button type="button"
                                    class="btn btn-secondary w-100 <?php echo $button['class'] ?? '' ?>"
                                    <?php foreach ($dataAttributeKeys as $key) : ?>
                                        <?php echo $key ?>="<?php echo $button[$key] ?>"
                                    <?php endforeach; ?>
                                    <?php if ($button['onclick']) : ?>
                                    onclick="<?php echo $button['onclick'] ?>"
                                    <?php endif; ?>
                                    title="<?php echo Text::_($button['label']) ?>"
                                    id="<?php echo $button['id'] ?>"
                            >
                                <?php if (!empty($button['icon'])) : ?>
                                    <span class="<?php echo $button['icon'] ?>"></span>
                                <?php elseif (!empty($button['image'])) : ?>
                                    <?php echo HTMLHelper::_('image', $button['image'], Text::_($button['tooltip'] ?? ''), ['class' => 'icon'], true) ?>
                                <?php elseif (!empty($button['svg'])) : ?>
                                    <?php echo $button['svg']; ?>
                                <?php endif; ?>
                                <?php echo Text::_($button['label']) ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php $return = $this->form->getValue('return', '', $this->params->get('login_redirect_url', $this->params->get('login_redirect_menuitem', ''))); ?>
                <input type="hidden" name="return" value="<?php echo base64_encode($return); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </fieldset>
        </form>

        <ul class="com-users-login__options pt-auth__links">
            <li>
                <a class="com-users-login__reset" href="<?php echo Route::_('index.php?option=com_users&view=reset'); ?>">
                    <?php echo Text::_('COM_USERS_LOGIN_RESET'); ?>
                </a>
            </li>
            <li>
                <a class="com-users-login__remind" href="<?php echo Route::_('index.php?option=com_users&view=remind'); ?>">
                    <?php echo Text::_('COM_USERS_LOGIN_REMIND'); ?>
                </a>
            </li>
            <?php if ($usersConfig->get('allowUserRegistration')) :
                $regLinkMenuId = $this->params->get('customRegLinkMenu');
                $regLink       = 'index.php?option=com_users&view=registration';

                if ($regLinkMenuId) {
                    $item = Factory::getApplication()->getMenu()->getItem($regLinkMenuId);

                    if ($item) {
                        $regLink = 'index.php?Itemid=' . $regLinkMenuId;
                    }
                }
                ?>
                <li>
                    <a class="com-users-login__register" href="<?php echo Route::_($regLink); ?>">
                        <?php echo Text::_('COM_USERS_LOGIN_REGISTER'); ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if ($phone) : ?>
        <p class="pt-auth__help">
            <?php echo Text::_('TPL_PAMPERINS_NEED_HELP'); ?>
            <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo Text::_('TPL_PAMPERINS_CALL_US'); ?> <?php echo $this->escape($phone); ?></a>
        </p>
    <?php endif; ?>
</div>
