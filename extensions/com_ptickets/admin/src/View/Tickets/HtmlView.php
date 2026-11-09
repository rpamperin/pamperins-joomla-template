<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\View\Tickets;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public $items = [];
    public $pagination;
    public $state;
    public $filterForm;
    public $activeFilters = [];

    public function display($tpl = null)
    {
        SupportTypes::loadSiteLanguage();

        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $user = $this->getCurrentUser();
        ToolbarHelper::title(Text::_('COM_PTICKETS_TICKETS_TITLE'), 'life-ring');

        if ($user->authorise('core.edit', 'com_ptickets')) {
            ToolbarHelper::editList('ticket.edit');
        }

        if ($user->authorise('core.delete', 'com_ptickets')) {
            ToolbarHelper::deleteList('COM_PTICKETS_DELETE_CONFIRM', 'tickets.delete');
        }

        if ($user->authorise('core.admin', 'com_ptickets') || $user->authorise('core.options', 'com_ptickets')) {
            ToolbarHelper::preferences('com_ptickets');
        }

        parent::display($tpl);
    }
}
