<?php

/**
 * Customer's own tickets.
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

/** @var \Pamperins\Component\Ptickets\Site\View\Tickets\HtmlView $this */
?>
<div class="com-ptickets pt-tickets">
    <div class="pt-tickets__head">
        <h1><?php echo Text::_('COM_PTICKETS_MY_TICKETS'); ?></h1>
        <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_ptickets&view=request'); ?>"><?php echo Text::_('COM_PTICKETS_NEW_REQUEST'); ?></a>
    </div>

    <?php if (!$this->items) : ?>
        <div class="pt-callout"><h3><?php echo Text::_('COM_PTICKETS_NO_TICKETS'); ?></h3><p><?php echo Text::_('COM_PTICKETS_NO_TICKETS_DESC'); ?></p></div>
    <?php else : ?>
        <ul class="pt-ticketlist">
            <?php foreach ($this->items as $t) : ?>
                <li class="pt-ticketlist__item">
                    <a href="<?php echo Route::_('index.php?option=com_ptickets&view=ticket&id=' . (int) $t->id); ?>">
                        <span class="pt-ticketlist__top">
                            <span class="pt-ticketlist__ref"><?php echo $this->escape($t->ref); ?></span>
                            <span class="badge <?php echo SupportTypes::statusBadge($t->status); ?>"><?php echo SupportTypes::statusLabel($t->status); ?></span>
                        </span>
                        <span class="pt-ticketlist__subject"><?php echo $this->escape($t->subject); ?></span>
                        <span class="pt-ticketlist__meta">
                            <?php echo SupportTypes::title($t->type); ?> ·
                            <?php echo HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC4')); ?>
                            <?php if ($t->replies) : ?> · <?php echo Text::plural('COM_PTICKETS_N_REPLIES', (int) $t->replies); ?><?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php echo $this->pagination->getPagesLinks(); ?>
    <?php endif; ?>
</div>
