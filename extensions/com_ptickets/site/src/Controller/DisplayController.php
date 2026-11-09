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
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

class DisplayController extends BaseController
{
    protected $default_view = 'request';

    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->getCmd('view', $this->default_view);

        // "My tickets" pages need a login; the request page explains itself to guests
        if (\in_array($view, ['tickets', 'ticket'], true) && $this->app->getIdentity()->guest) {
            $return = base64_encode(Uri::getInstance()->toString());
            $this->setRedirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false), Text::_('COM_PTICKETS_LOGIN_REQUIRED'), 'notice');

            return $this;
        }

        return parent::display(false, $urlparams);
    }
}
