<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

class TicketTable extends Table
{
    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__ptickets_tickets', 'id', $db, $dispatcher);
    }

    public function check()
    {
        $now = Factory::getDate()->toSql();

        if (empty($this->created)) {
            $this->created = $now;
        }

        $this->modified = $now;

        if (\in_array($this->status, ['resolved', 'closed'], true) && empty($this->closed)) {
            $this->closed = $now;
        } elseif (!\in_array($this->status, ['resolved', 'closed'], true)) {
            $this->closed = null;
        }

        return parent::check();
    }

    public function store($updateNulls = true)
    {
        return parent::store($updateNulls);
    }

    /** Deleting a ticket also deletes its conversation. */
    public function delete($pk = null)
    {
        $id = (int) ($pk ?? $this->id);

        if (!parent::delete($pk)) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ptickets_replies'))
            ->where($db->quoteName('ticket_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        return true;
    }
}
