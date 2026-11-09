<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\Model;

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;

\defined('_JEXEC') or die;

class RequestModel extends FormModel
{
    protected function populateState()
    {
        $type = \Joomla\CMS\Factory::getApplication()->getInput()->getCmd('type', '');
        $this->setState('request.type', SupportTypes::isValid($type) ? $type : '');

        parent::populateState();
    }

    public function getForm($data = [], $loadData = true)
    {
        $type = (string) $this->getState('request.type');

        return $this->loadForm('com_ptickets.request.' . ($type ?: 'none'), 'request', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /** Add the type-specific questions before data is bound. */
    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        $type = (string) $this->getState('request.type');

        if (SupportTypes::isValid($type)) {
            $form->loadFile(SupportTypes::formFile($type), false);
        }

        parent::preprocessForm($form, $data, $group);
    }

    protected function loadFormData()
    {
        $type = (string) $this->getState('request.type');

        return \Joomla\CMS\Factory::getApplication()->getUserState('com_ptickets.request.' . $type . '.data', []);
    }
}
