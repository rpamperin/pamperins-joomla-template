<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\View\Ticket;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public $form;
    public $item;

    public function display($tpl = null)
    {
        SupportTypes::loadSiteLanguage();

        $this->form = $this->getModel()->getForm();
        $this->item = $this->getModel()->getItem();

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::sprintf('COM_PTICKETS_TICKET_TITLE', $this->item->ref), 'life-ring');
        ToolbarHelper::apply('ticket.apply', 'COM_PTICKETS_SAVE_REPLY');
        ToolbarHelper::save('ticket.save');
        ToolbarHelper::cancel('ticket.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
