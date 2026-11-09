<?php

/**
 * Staff ticket queue.
 *
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

/** @var \Pamperins\Component\Ptickets\Administrator\View\Tickets\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$canEdit = $this->getCurrentUser()->authorise('core.edit', 'com_ptickets');
?>
<form action="<?php echo Route::_('index.php?option=com_ptickets&view=tickets'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('COM_PTICKETS_NO_TICKETS_ADMIN'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="ticketList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_PTICKETS_TICKETS_TITLE'); ?></caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                                <th scope="col" class="w-10"><?php echo Text::_('COM_PTICKETS_COL_REF'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_PTICKETS_FIELD_SUBJECT'); ?></th>
                                <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_PTICKETS_COL_CUSTOMER'); ?></th>
                                <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_('COM_PTICKETS_FIELD_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_PTICKETS_FIELD_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_PTICKETS_FIELD_PRIORITY_STAFF'); ?></th>
                                <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_('COM_PTICKETS_FIELD_ASSIGNED'); ?></th>
                                <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_PTICKETS_COL_CREATED'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $t) : ?>
                                <tr class="row<?php echo $i % 2; ?>">
                                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $t->id, false, 'cid', 'cb', $t->ref); ?></td>
                                    <td>
                                        <?php if ($t->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $t->editor, $t->checked_out_time, 'tickets.', true); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_ptickets&task=ticket.edit&id=' . (int) $t->id); ?>"><strong><?php echo $this->escape($t->ref); ?></strong></a>
                                        <?php else : ?>
                                            <strong><?php echo $this->escape($t->ref); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <th scope="row">
                                        <?php echo $this->escape($t->subject); ?>
                                        <?php if ($t->replies) : ?>
                                            <span class="badge bg-light text-dark" title="<?php echo Text::_('COM_PTICKETS_CONVERSATION'); ?>"><span class="icon-comments" aria-hidden="true"></span> <?php echo (int) $t->replies; ?></span>
                                        <?php endif; ?>
                                    </th>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $this->escape($t->customer_name); ?><br>
                                        <small class="text-muted"><?php echo $this->escape($t->customer_email); ?><?php echo $t->phone ? ' · ' . $this->escape($t->phone) : ''; ?></small>
                                    </td>
                                    <td class="d-none d-lg-table-cell"><?php echo SupportTypes::title($t->type); ?></td>
                                    <td><span class="badge <?php echo SupportTypes::statusBadge($t->status); ?>"><?php echo SupportTypes::statusLabel($t->status); ?></span></td>
                                    <td><span class="badge <?php echo SupportTypes::priorityBadge($t->priority); ?>"><?php echo SupportTypes::priorityLabel($t->priority); ?></span></td>
                                    <td class="d-none d-lg-table-cell"><?php echo $t->assigned_name ? $this->escape($t->assigned_name) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="d-none d-md-table-cell"><?php echo HTMLHelper::_('date', $t->created, Text::_('DATE_FORMAT_LC4') . ' H:i'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
