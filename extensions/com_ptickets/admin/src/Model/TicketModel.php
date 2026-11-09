<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;
use Pamperins\Component\Ptickets\Administrator\Helper\TicketService;

\defined('_JEXEC') or die;

/** Staff view of one ticket: change status/priority/assignee, reply or add an internal note. */
class TicketModel extends AdminModel
{
    public $typeAlias = 'com_ptickets.ticket';

    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_ptickets.ticket', 'ticket', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_ptickets.edit.ticket.data', []);

        return $data ?: $this->getItem();
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && $item->id) {
            $user                 = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById((int) $item->user_id);
            $item->customer_name  = $user->name;
            $item->customer_email = $user->email;
            $item->detail_rows    = SupportTypes::describeDetails($item->type, $item->details);
            $item->replies        = TicketService::replies((int) $item->id, true);
        }

        return $item;
    }

    public function save($data)
    {
        $reply    = trim((string) ($data['reply'] ?? ''));
        $internal = !empty($data['reply_internal']);
        unset($data['reply'], $data['reply_internal']);

        // Only these fields are edited here; everything else stays as the customer sent it
        $allowed = array_intersect_key($data, array_flip(['id', 'status', 'priority', 'assigned_to']));
        $before  = TicketService::load((int) ($allowed['id'] ?? 0));

        if (!$before) {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_PTICKETS_TICKET_NOT_FOUND'));

            return false;
        }

        if (!parent::save($allowed)) {
            return false;
        }

        $after = TicketService::load((int) $before->id);

        if ($reply !== '') {
            TicketService::addReply($after, Factory::getApplication()->getIdentity(), $reply, $internal, true);
        }

        if ($after->status !== $before->status || $after->priority !== $before->priority || (int) $after->assigned_to !== (int) $before->assigned_to) {
            TicketService::dispatch('onPticketsAfterUpdate', ['ticket' => $after]);
        }

        return true;
    }
}
