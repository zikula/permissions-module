<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.7.0 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Listener\Base;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Zikula\Core\Event\GenericEvent;

/**
 * Event handler base class for events of the Users module.
 */
class UsersListener implements EventSubscriberInterface
{
    /**
     * Makes our handlers known to the event system.
     */
    public static function getSubscribedEvents()
    {
        return array(
            'module.users.config.updated' => array('configUpdated', 5)
        );
    }
    
    /**
     * Listener for the `module.users.config.updated` event.
     *
     * Occurs after the Users module configuration has been
     * updated via the administration interface.
     *
     * @param GenericEvent $event The event instance.
     */
    public function configUpdated(GenericEvent $event)
    {
    }
}
