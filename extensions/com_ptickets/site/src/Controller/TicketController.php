<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Pamperins\Component\Ptickets\Administrator\Helper\TicketService;

\defined('_JEXEC') or die;

class TicketController extends BaseController
{
    /** Customer adds a reply to one of their own tickets. */
    public function reply()
    {
        $this->checkToken();

        $user   = $this->app->getIdentity();
        $id     = $this->input->post->getInt('id');
        $ticket = TicketService::load($id);
        $back   = Route::_('index.php?option=com_ptickets&view=ticket&id=' . $id, false);

        if ($user->guest || !$ticket || (int) $ticket->user_id !== (int) $user->id) {
            $this->setRedirect(Route::_('index.php?option=com_ptickets&view=tickets', false), Text::_('COM_PTICKETS_TICKET_NOT_FOUND'), 'error');

            return false;
        }

        if ($ticket->status === 'closed') {
            $this->setRedirect($back, Text::_('COM_PTICKETS_TICKET_CLOSED_NO_REPLY'), 'warning');

            return false;
        }

        $body = trim($this->input->post->getString('body', ''));

        if ($body === '') {
            $this->setRedirect($back, Text::_('COM_PTICKETS_REPLY_EMPTY'), 'warning');

            return false;
        }

        TicketService::addReply($ticket, $user, mb_substr($body, 0, 10000), false, false);
        $this->setRedirect($back, Text::_('COM_PTICKETS_REPLY_SENT'));

        return true;
    }
}
