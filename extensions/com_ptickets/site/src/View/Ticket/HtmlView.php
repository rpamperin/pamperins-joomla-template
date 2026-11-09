<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\View\Ticket;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public $item;
    public $remoteUrl = '';

    public function display($tpl = null)
    {
        $this->item = $this->getModel()->getItem();

        if (!$this->item) {
            throw new \Exception(Text::_('COM_PTICKETS_TICKET_NOT_FOUND'), 404);
        }

        $this->remoteUrl = (string) ComponentHelper::getParams('com_ptickets')->get('remote_url', 'https://support.pamperins.com/get-help');
        $this->getDocument()->setTitle($this->item->ref . ' – ' . $this->item->subject);

        parent::display($tpl);
    }
}
