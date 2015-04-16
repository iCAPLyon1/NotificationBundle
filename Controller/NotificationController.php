<?php

namespace Icap\NotificationBundle\Controller;

use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    /**
     * @Route(
     *    "/list/{page}",
     *    requirements = {
     *        "page" = "\d+"
     *    },
     *    defaults = {
     *        "page" = 1
     *    },
     *    name="icap_notification_view"
     * )
     * @Template()
     * @ParamConverter("user", options={"authenticatedUser" = true})
     */
    public function listAction(Request $request, $user, $page)
    {
        $notificationManager = $this->getNotificationManager();
        $systemName = $notificationManager->getPlatformName();
        if ($request->isXMLHttpRequest()) {
            $result = $notificationManager->getDropdownNotifications($user->getId());
            $result['systemName'] = $systemName;
            $unviewedNotifications = $notificationManager->countUnviewedNotifications(
                $user->getId()
            );
            $result['unviewedNotifications'] = $unviewedNotifications;

            return $this->render(
                'IcapNotificationBundle:Templates:notificationDropdownList.html.twig',
                $result
            );
        } else {
            $result = $notificationManager->getPaginatedNotifications($user->getId(), $page);
            $result['systemName'] = $systemName;

            return $result;
        }
    }

    /**
     * @Route(
     *      "/rss/{rssId}",
     *      defaults={"_format":"xml"},
     *      name="icap_notification_rss"
     * )
     * @Template()
     * @param $rssId
     * @return mixed
     */
    public function rssAction($rssId)
    {
        $notificationManager = $this->getNotificationManager();
        try {
            $result = $notificationManager->getUserNotificationsListRss($rssId);
            $result["systemName"] = $notificationManager->getPlatformName();
        } catch (NoResultException $nre) {
            $result = array("error" => "no_rss_defined");
        } catch (NotFoundHttpException $nfe) {
            $result = array("error" => "zero_notifications");
        }

        return $result;
    }

    /**
     * @return \Icap\NotificationBundle\Manager\NotificationManager
     */
    private function getNotificationManager()
    {
        return $this->get("icap.notification.manager");
    }
}