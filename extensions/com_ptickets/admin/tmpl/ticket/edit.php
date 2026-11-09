<?php

/**
 * Staff: work one ticket.
 *
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

/** @var \Pamperins\Component\Ptickets\Administrator\View\Ticket\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
$t = $this->item;
?>
<form action="<?php echo Route::_('index.php?option=com_ptickets&layout=edit&id=' . (int) $t->id); ?>" method="post" name="adminForm" id="ticket-form" class="form-validate">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-1">
                        <?php echo $this->escape($t->ref); ?> · <?php echo SupportTypes::title($t->type); ?> ·
                        <?php echo HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC2')); ?>
                    </p>
                    <h2 class="h3"><?php echo $this->escape($t->subject); ?></h2>
                    <p class="mb-0">
                        <strong><?php echo $this->escape($t->customer_name); ?></strong> ·
                        <a href="mailto:<?php echo $this->escape($t->customer_email); ?>"><?php echo $this->escape($t->customer_email); ?></a>
                        <?php if ($t->phone) : ?> · <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+]/', '', $t->phone)); ?>"><?php echo $this->escape($t->phone); ?></a><?php endif; ?>
                        · <?php echo Text::_('COM_PTICKETS_FIELD_CONTACT_METHOD'); ?>: <?php echo Text::_('COM_PTICKETS_CONTACT_' . strtoupper($t->contact_method)); ?>
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_PTICKETS_CUSTOMER_ANSWERS'); ?></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <?php foreach ($t->detail_rows as [$label, $value]) : ?>
                            <dt class="col-sm-4"><?php echo $this->escape($label); ?></dt>
                            <dd class="col-sm-8"><?php echo $this->escape($value); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_PTICKETS_CONVERSATION'); ?></div>
                <div class="card-body">
                    <div class="border-start border-4 border-info ps-3 mb-3">
                        <div class="small text-muted"><?php echo $this->escape($t->customer_name); ?> · <?php echo HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC2')); ?></div>
                        <div><?php echo nl2br($this->escape($t->description)); ?></div>
                    </div>
                    <?php foreach ($t->replies as $r) : ?>
                        <?php $isCustomer = (int) $r->user_id === (int) $t->user_id; ?>
                        <div class="border-start border-4 ps-3 mb-3 <?php echo $r->internal ? 'border-warning bg-warning-subtle' : ($isCustomer ? 'border-info' : 'border-primary'); ?>">
                            <div class="small text-muted">
                                <?php echo $this->escape($r->author_name); ?> · <?php echo HTMLHelper::_('date', $r->created, Text::_('DATE_FORMAT_LC2')); ?>
                                <?php if ($r->internal) : ?><span class="badge bg-warning text-dark"><?php echo Text::_('COM_PTICKETS_INTERNAL_NOTE'); ?></span><?php endif; ?>
                            </div>
                            <div><?php echo nl2br($this->escape($r->body)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_PTICKETS_MANAGE'); ?></div>
                <div class="card-body"><?php echo $this->form->renderFieldset('manage'); ?></div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_PTICKETS_REPLY_OR_NOTE'); ?></div>
                <div class="card-body"><?php echo $this->form->renderFieldset('reply'); ?></div>
            </div>
            <?php if ($t->external_system) : ?>
                <p class="small text-muted"><?php echo Text::sprintf('COM_PTICKETS_EXTERNAL', $this->escape($t->external_system), $this->escape($t->external_id)); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
