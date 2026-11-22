<?php

/**
 * @package     Pamperins.Plugin
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Pamperins\Plugin\Content\Ptpricing\Extension\Ptpricing;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Ptpricing($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('content', 'ptpricing'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
