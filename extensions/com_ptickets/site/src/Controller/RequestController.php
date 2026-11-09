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
use Pamperins\Component\Ptickets\Administrator\Helper\SupportTypes;
use Pamperins\Component\Ptickets\Administrator\Helper\TicketService;

\defined('_JEXEC') or die;

class RequestController extends BaseController
{
    /** Save a submitted request form as a new ticket. */
    public function submit()
    {
        $this->checkToken();

        $user = $this->app->getIdentity();
        $type = $this->input->post->getCmd('type', '');
        $back = Route::_('index.php?option=com_ptickets&view=request' . (SupportTypes::isValid($type) ? '&type=' . $type : ''), false);

        if ($user->guest) {
            $this->setRedirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($back), false), Text::_('COM_PTICKETS_LOGIN_REQUIRED'), 'notice');

            return false;
        }

        if (!SupportTypes::isValid($type)) {
            $this->setRedirect($back);

            return false;
        }

        /** @var \Pamperins\Component\Ptickets\Site\Model\RequestModel $model */
        $model = $this->getModel('Request', 'Site', ['ignore_request' => true]);
        $model->setState('request.type', $type);

        $data  = $this->input->post->get('jform', [], 'array');
        $form  = $model->getForm($data, false);
        $valid = $form ? $model->validate($form, $data) : false;
        $stateKey = 'com_ptickets.request.' . $type . '.data';

        if ($valid === false) {
            foreach (array_slice($model->getErrors(), 0, 5) as $error) {
                $this->app->enqueueMessage($error instanceof \Throwable ? $error->getMessage() : (string) $error, 'warning');
            }

            $this->app->setUserState($stateKey, $data);
            $this->setRedirect($back);

            return false;
        }

        $ticket = TicketService::create($user, [
            'type'           => $type,
            'subject'        => $valid['subject'] ?? '',
            'description'    => $valid['description'] ?? '',
            'phone'          => $valid['phone'] ?? '',
            'contact_method' => $valid['contact_method'] ?? 'email',
            'priority'       => $valid['priority'] ?? 'normal',
            'details'        => $valid['details'] ?? [],
        ]);

        $this->app->setUserState($stateKey, null);
        $this->setRedirect(
            Route::_('index.php?option=com_ptickets&view=ticket&id=' . (int) $ticket->id, false),
            Text::sprintf('COM_PTICKETS_SUBMIT_SUCCESS', $ticket->ref)
        );

        return true;
    }
}
