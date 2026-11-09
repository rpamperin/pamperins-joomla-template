<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\View\Tickets;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public $items = [];
    public $pagination;

    public function display($tpl = null)
    {
        $this->items      = $this->getModel()->getItems();
        $this->pagination = $this->getModel()->getPagination();
        $this->getDocument()->setTitle(Text::_('COM_PTICKETS_MY_TICKETS'));

        parent::display($tpl);
    }
}
