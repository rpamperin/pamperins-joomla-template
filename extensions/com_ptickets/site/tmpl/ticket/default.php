<?php

/**
 * One of the customer's tickets: answers, conversation, reply box.
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

/** @var \Pamperins\Component\Ptickets\Site\View\Ticket\HtmlView $this */

$t = $this->item;
$this->getDocument()->getWebAssetManager()->useScript('form.validate');
?>
<div class="com-ptickets pt-ticket">
    <p class="pt-request__back"><a href="<?php echo Route::_('index.php?option=com_ptickets&view=tickets'); ?>">&larr; <?php echo Text::_('COM_PTICKETS_MY_TICKETS'); ?></a></p>

    <div class="pt-ticket__head">
        <p class="pt-eyebrow"><?php echo $this->escape($t->ref); ?> · <?php echo SupportTypes::title($t->type); ?></p>
        <h1><?php echo $this->escape($t->subject); ?></h1>
        <p class="pt-ticket__meta">
            <span class="badge <?php echo SupportTypes::statusBadge($t->status); ?>"><?php echo SupportTypes::statusLabel($t->status); ?></span>
            <?php echo Text::sprintf('COM_PTICKETS_OPENED_ON', HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC2'))); ?>
        </p>
    </div>

    <?php if ($t->type === 'remote' && $this->remoteUrl && !\in_array($t->status, ['resolved', 'closed'], true)) : ?>
        <div class="pt-callout pt-callout--secure pt-ticket__remote">
            <h3><?php echo Text::_('COM_PTICKETS_REMOTE_NEXT_TITLE'); ?></h3>
            <p><?php echo Text::_('COM_PTICKETS_REMOTE_NEXT_TEXT'); ?></p>
            <p><a class="btn pt-btn pt-btn--accent" href="<?php echo $this->escape($this->remoteUrl); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_PTICKETS_REMOTE_NEXT_BUTTON'); ?></a></p>
        </div>
    <?php endif; ?>

    <div class="pt-ticket__grid">
        <section class="pt-ticket__thread" aria-label="<?php echo Text::_('COM_PTICKETS_CONVERSATION'); ?>">
            <article class="pt-msg pt-msg--customer">
                <header><strong><?php echo Text::_('COM_PTICKETS_YOU'); ?></strong> <time><?php echo HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC2')); ?></time></header>
                <div class="pt-msg__body"><?php echo nl2br($this->escape($t->description)); ?></div>
            </article>

            <?php foreach ($t->replies as $r) : ?>
                <?php $mine = (int) $r->user_id === (int) $t->user_id; ?>
                <article class="pt-msg <?php echo $mine ? 'pt-msg--customer' : 'pt-msg--staff'; ?>">
                    <header><strong><?php echo $mine ? Text::_('COM_PTICKETS_YOU') : Text::_('COM_PTICKETS_STAFF_NAME'); ?></strong> <time><?php echo HTMLHelper::_('date', $r->created, Text::_('DATE_FORMAT_LC2')); ?></time></header>
                    <div class="pt-msg__body"><?php echo nl2br($this->escape($r->body)); ?></div>
                </article>
            <?php endforeach; ?>

            <?php if ($t->status !== 'closed') : ?>
                <form action="<?php echo Route::_('index.php?option=com_ptickets&view=ticket&id=' . (int) $t->id); ?>" method="post" class="form-validate pt-ticket__reply">
                    <label for="pt-reply-body"><?php echo Text::_('COM_PTICKETS_ADD_REPLY'); ?></label>
                    <textarea id="pt-reply-body" name="body" rows="4" class="form-control required" required></textarea>
                    <button type="submit" class="btn btn-primary validate"><?php echo Text::_('COM_PTICKETS_SEND_REPLY'); ?></button>
                    <input type="hidden" name="task" value="ticket.reply">
                    <input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            <?php else : ?>
                <p class="pt-note"><?php echo Text::_('COM_PTICKETS_TICKET_CLOSED_NO_REPLY'); ?></p>
            <?php endif; ?>
        </section>

        <aside class="pt-ticket__details">
            <h2><?php echo Text::_('COM_PTICKETS_YOUR_ANSWERS'); ?></h2>
            <dl>
                <?php foreach (SupportTypes::describeDetails($t->type, $t->details) as [$label, $value]) : ?>
                    <dt><?php echo $this->escape($label); ?></dt>
                    <dd><?php echo $this->escape($value); ?></dd>
                <?php endforeach; ?>
                <dt><?php echo Text::_('COM_PTICKETS_FIELD_PRIORITY'); ?></dt>
                <dd><?php echo Text::_('COM_PTICKETS_URGENCY_' . strtoupper($t->priority)); ?></dd>
                <?php if ($t->phone) : ?>
                    <dt><?php echo Text::_('COM_PTICKETS_FIELD_PHONE'); ?></dt>
                    <dd><?php echo $this->escape($t->phone); ?></dd>
                <?php endif; ?>
            </dl>
        </aside>
    </div>
</div>
