<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** The staff ticket queue. */
class TicketsModel extends ListModel
{
    public function __construct($config = [], ?\Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 't.id', 'ref', 't.ref', 'subject', 't.subject', 'status', 't.status', 'type', 't.type',
                'priority', 't.priority', 'created', 't.created', 'modified', 't.modified', 'customer_name', 'assigned_to', 't.assigned_to',
            ];
        }

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 't.created', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = '')
    {
        foreach (['search', 'status', 'type', 'priority', 'assigned_to'] as $f) {
            $id .= ':' . $this->getState('filter.' . $f);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('t.*')
            ->select([
                $db->quoteName('u.name', 'customer_name'),
                $db->quoteName('u.email', 'customer_email'),
                $db->quoteName('a.name', 'assigned_name'),
                $db->quoteName('uc.name', 'editor'),
            ])
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ptickets_replies', 'r') . ' WHERE r.ticket_id = t.id) AS replies')
            ->from($db->quoteName('#__ptickets_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = t.user_id')
            ->join('LEFT', $db->quoteName('#__users', 'a'), 'a.id = t.assigned_to')
            ->join('LEFT', $db->quoteName('#__users', 'uc'), 'uc.id = t.checked_out');

        $status = (string) $this->getState('filter.status');

        if ($status === 'active' || $status === '') {
            // Default view: everything that still needs work
            $query->whereNotIn($db->quoteName('t.status'), ['resolved', 'closed'], ParameterType::STRING);
        } elseif ($status !== '*') {
            $query->where($db->quoteName('t.status') . ' = :status')->bind(':status', $status);
        }

        if ($type = (string) $this->getState('filter.type')) {
            $query->where($db->quoteName('t.type') . ' = :type')->bind(':type', $type);
        }

        if ($priority = (string) $this->getState('filter.priority')) {
            $query->where($db->quoteName('t.priority') . ' = :priority')->bind(':priority', $priority);
        }

        $assigned = $this->getState('filter.assigned_to');

        if ($assigned !== null && $assigned !== '') {
            $assigned = (int) $assigned;
            $query->where($db->quoteName('t.assigned_to') . ' = :assigned')->bind(':assigned', $assigned, ParameterType::INTEGER);
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            $like = '%' . str_replace(' ', '%', $search) . '%';
            $query->where('(' . implode(' OR ', [
                $db->quoteName('t.ref') . ' LIKE :s1',
                $db->quoteName('t.subject') . ' LIKE :s2',
                $db->quoteName('u.name') . ' LIKE :s3',
                $db->quoteName('u.email') . ' LIKE :s4',
                $db->quoteName('t.phone') . ' LIKE :s5',
            ]) . ')')
                ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like)->bind(':s5', $like);
        }

        $order = $this->state->get('list.ordering', 't.created');
        $dir   = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if ($order === 'priority' || $order === 't.priority') {
            $query->order('FIELD(t.priority, ' . $db->quote('urgent') . ',' . $db->quote('high') . ',' . $db->quote('normal') . ',' . $db->quote('low') . ') ' . $dir);
        } else {
            $query->order($db->escape($order) . ' ' . $dir);
        }

        return $query;
    }
}
