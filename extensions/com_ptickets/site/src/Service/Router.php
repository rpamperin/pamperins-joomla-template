<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

\defined('_JEXEC') or die;

/** SEF URLs: /<request menu item>, /<tickets menu item>/<ticket id> */
class Router extends RouterView
{
    public function __construct(SiteApplication $app, AbstractMenu $menu, $categoryFactory = null, $db = null)
    {
        $this->registerView(new RouterViewConfiguration('request'));

        $tickets = new RouterViewConfiguration('tickets');
        $this->registerView($tickets);

        $ticket = new RouterViewConfiguration('ticket');
        $ticket->setKey('id')->setParent($tickets);
        $this->registerView($ticket);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    public function getTicketSegment($id, $query)
    {
        return [(int) $id => (string) (int) $id];
    }

    public function getTicketId($segment, $query)
    {
        return ctype_digit((string) $segment) ? (int) $segment : false;
    }
}
