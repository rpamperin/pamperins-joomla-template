<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

class TicketController extends FormController
{
    protected $text_prefix = 'COM_PTICKETS_TICKET';

    /** Tickets come from customers; staff can't create blank ones here. */
    protected function allowAdd($data = [])
    {
        return false;
    }
}
