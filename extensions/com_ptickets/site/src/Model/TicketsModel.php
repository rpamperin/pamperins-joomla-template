<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** The logged-in customer's own tickets. */
class TicketsModel extends ListModel
{
    protected function populateState($ordering = 't.created', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
        $this->setState('list.limit', 25);
    }

    protected function getListQuery()
    {
        $db     = $this->getDatabase();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        return $db->getQuery(true)
            ->select(['t.id', 't.ref', 't.type', 't.subject', 't.status', 't.priority', 't.created', 't.modified'])
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ptickets_replies', 'r') . ' WHERE r.ticket_id = t.id AND r.internal = 0) AS replies')
            ->from($db->quoteName('#__ptickets_tickets', 't'))
            ->where($db->quoteName('t.user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order('t.created DESC, t.id DESC');
    }
}
