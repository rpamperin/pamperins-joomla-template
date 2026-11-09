<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\View\Request;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public $type = '';
    public $form;
    public $user;
    public $params;

    public function display($tpl = null)
    {
        $app          = Factory::getApplication();
        $this->user   = $app->getIdentity();
        $this->params = $app->getParams();
        $this->type   = (string) $this->getModel()->getState('request.type');

        if ($this->type && !$this->user->guest) {
            $this->form = $this->getModel()->getForm();
        }

        $this->getDocument()->setTitle($this->type ? SupportTypes::title($this->type) : Text::_('COM_PTICKETS_REQUEST_TITLE'));

        parent::display($tpl);
    }
}
