<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Pamperins\Component\Ptickets\Administrator\Helper\TicketService;

\defined('_JEXEC') or die;

/** One ticket, only if it belongs to the logged-in customer. */
class TicketModel extends BaseDatabaseModel
{
    public function getItem(): ?object
    {
        $user   = Factory::getApplication()->getIdentity();
        $ticket = TicketService::load(Factory::getApplication()->getInput()->getInt('id'));

        if (!$ticket || $user->guest || (int) $ticket->user_id !== (int) $user->id) {
            return null;
        }

        $ticket->replies = TicketService::replies((int) $ticket->id, false);

        return $ticket;
    }
}
