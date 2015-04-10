<?php

namespace Icap\NotificationBundle\Manager;

use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Doctrine\ORM\NoResultException;
use Icap\NotificationBundle\Entity\FollowerResource;
use Icap\NotificationBundle\Entity\NotifiableInterface;
use Icap\NotificationBundle\Entity\Notification;
use Icap\NotificationBundle\Entity\NotificationViewer;
use Doctrine\ORM\EntityManager;
use Icap\NotificationBundle\Event\Notification\NotificationCreateDelegateViewEvent;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Icap\NotificationBundle\Entity\ColorChooser;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * Class NotificationManager
 * @package Icap\NotificationBundle\Manager
 *
 * @DI\Service("icap.notification.manager")
 */
class NotificationManager
{
    protected $em;
    protected $security;
    protected $eventDispatcher;
    protected $platformName;
    protected $notificationParametersManager;

    /**
     * @return \Icap\NotificationBundle\Repository\NotificationRepository
     */
    protected function getNotificationRepository()
    {
        return $this->getEntityManager()->getRepository('IcapNotificationBundle:Notification');
    }

    /**
     * @return \Icap\NotificationBundle\Repository\NotificationViewerRepository
     */
    protected function getNotificationViewerRepository()
    {
        return $this->getEntityManager()->getRepository('IcapNotificationBundle:NotificationViewer');
    }

    /**
     * @return \Icap\NotificationBundle\Repository\FollowerResourceRepository
     */
    protected function getFollowerResourceRepository()
    {
        return $this->getEntityManager()->getRepository('IcapNotificationBundle:FollowerResource');
    }

    protected function getUsersToNotifyForNotifiable(NotifiableInterface $notifiable)
    {
        $userIds = array();
        if ($notifiable->getSendToFollowers() && $notifiable->getResource() !== null) {
            $userIds = $this->getFollowersByResourceIdAndClass(
                $notifiable->getResource()->getId(),
                $notifiable->getResource()->getClass()
            );
        }

        $includeUserIds = $notifiable->getIncludeUserIds();
        if (!empty($includeUserIds)) {
            $userIds = array_merge($userIds, $includeUserIds);
        }

        $userIds = array_unique($userIds);
        $excludeUserIds = $notifiable->getExcludeUserIds();
        $removeUserIds = array();

        if (!empty($excludeUserIds)) {
            $userIds = array_diff($userIds, $excludeUserIds);
        }

        $doer = $notifiable->getDoer();
        if (!empty($doer) && is_a($doer, 'Claroline\CoreBundle\Entity\User')) {
            array_push($removeUserIds, $doer->getId());
        }

        $userIds = array_diff($userIds, $removeUserIds);

        return $userIds;
    }

    /**
     * Constructor
     * @DI\InjectParams({
     *      "em"                = @DI\Inject("doctrine.orm.entity_manager"),
     *      "securityContext"   = @DI\Inject("security.context"),
     *      "eventDispatcher"   = @DI\Inject("event_dispatcher"),
     *      "configHandler"     = @DI\Inject("claroline.config.platform_config_handler"),
     *      "notificationParametersManager" = @DI\Inject("icap.notification.manager.notification_user_parameters")
     * })
     */
    public function __construct(
        EntityManager $em,
        SecurityContextInterface $securityContext,
        EventDispatcherInterface $eventDispatcher,
        PlatformConfigurationHandler $configHandler,
        NotificationUserParametersManager $notificationParametersManager
    ) {
        $this->em = $em;
        $this->security = $securityContext;
        $this->eventDispatcher = $eventDispatcher;
        $this->platformName = $configHandler->getParameter("name");
        if ($this->platformName === null || empty($this->platformName)) {
            $this->platformName = "Claroline";
        }
        $this->notificationParametersManager = $notificationParametersManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return mixed
     */
    public function getPlatformName()
    {
       return $this->platformName;
    }

    /**
     * Get Hash for a given object which must implement notifiable interface
     *
     * @param int $resourceId
     * @param string $resourceClass
     *
     * @return string The generated hash
     */
    public function getHash($resourceId, $resourceClass)
    {
        $raw = sprintf(
            '%s_%s',
            $resourceClass,
            $resourceId
        );

        return md5($raw);
    }

    /**
     * @param int $resourceId
     * @param string $resourceClass
     *
     * @return mixed
     */
    public function getFollowersByResourceIdAndClass($resourceId, $resourceClass)
    {
        $followerResults = $this->getFollowerResourceRepository()->
            findFollowersByResourceIdAndClass($resourceId, $resourceClass);
        $followerIds = array();
        foreach ($followerResults as $followerResult) {
            array_push($followerIds, $followerResult['id']);
        }

        return $followerIds;
    }

    /**
     * Create new Tag given its name
     *
     * @param string $actionKey
     * @param string $iconKey
     * @param integer|null $resourceId
     * @param array $details
     * @param object|null $doer
     *
     * @internal param \Icap\NotificationBundle\Entity\NotifiableInterface $notifiable
     *
     * @return Notification
     */
    public function createNotification($actionKey, $iconKey, $resourceId = null, $details = array(), $doer = null)
    {
        $notification = new Notification();
        $notification->setActionKey($actionKey);
        $notification->setIconKey($iconKey);
        $notification->setResourceId($resourceId);

        $doerId = null;

        if ($doer === null) {
            $securityToken = $this->security->getToken();

            if (null !== $securityToken) {
                $doer = $securityToken->getUser();
            }
        }

        if (is_a($doer, 'Claroline\CoreBundle\Entity\User')) {
            $doerId = $doer->getId();
        }

        if (!isset($details['doer']) && !empty($doerId)) {
            $details['doer'] = array(
                'id'        => $doerId,
                'firstName' => $doer->getFirstName(),
                'lastName'  => $doer->getLastName(),
                'avatar'    => $doer->getPicture(),
                'publicUrl' => $doer->getPublicUrl()
            );
        }
        $notification->setDetails($details);
        $notification->setUserId($doerId);

        $this->getEntityManager()->persist($notification);
        $this->getEntityManager()->flush();

        return $notification;
    }

    /**
     * Creates a notification viewer for every user in the list of people to be notified
     *
     * @param Notification $notification
     * @param $userIds
     * @internal param \Icap\NotificationBundle\Entity\NotifiableInterface $notifiable
     *
     * @return \Icap\NotificationBundle\Entity\Notification
     */
    public function notifyUsers(Notification $notification, $userIds)
    {
        if (count($userIds) > 0) {
            foreach ($userIds as $userId) {
                if ($userId !== null) {
                    $notificationViewer = new NotificationViewer();
                    $notificationViewer->setNotification($notification);
                    $notificationViewer->setViewerId($userId);
                    $notificationViewer->setStatus(false);

                    $this->getEntityManager()->persist($notificationViewer);
                }
            }
        }
        $this->getEntityManager()->flush();

        return $notification;
    }

    /**
     * Creates a notification and notifies the concerned users
     *
     * @param  NotifiableInterface $notifiable
     * @return Notification
     */
    public function createNotificationAndNotify(NotifiableInterface $notifiable)
    {
        $userIds = $this->getUsersToNotifyForNotifiable($notifiable);
        $notification = null;
        if (count($userIds) > 0) {
            $resourceId = null;
            if ($notifiable->getResource() !== null) {
                $resourceId = $notifiable->getResource()->getId();
            }

            $notification = $this->createNotification(
                $notifiable->getActionKey(),
                $notifiable->getIconKey(),
                $resourceId,
                $notifiable->getNotificationDetails(),
                $notifiable->getDoer()
            );
            $this->notifyUsers($notification, $userIds);
        }

        return $notification;
    }

    /**
     * Retrieves the notifications list
     *
     * @param  int  $userId
     * @param  int  $page
     * @param  int  $maxResult
     * @param  bool $isRss
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return mixed
     */
    public function getUserNotificationsList($userId, $page = 1, $maxResult = -1, $isRss = false)
    {
        $notificationUserParameters = $this
            ->notificationParametersManager
            ->getParametersByUserId($userId);
        $visibleTypes = $notificationUserParameters->getDisplayEnabledTypes();
        if ($isRss) {
            $visibleTypes = $notificationUserParameters->getRssEnabledTypes();
        }

        $query = $this
            ->getNotificationViewerRepository()
            ->findUserNotificationsQuery($userId, $visibleTypes);
        $adapter = new DoctrineORMAdapter($query, false);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($maxResult);

        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        $views = $this->renderNotifications($pager->getCurrentPageResults());

        return array(
            'pager'             => $pager,
            'notificationViews' => $views
        );
    }

    public function getUserNotificationsListRss($rssId, $maxResult)
    {
        $notificationUserParameters = $this
            ->notificationParametersManager
            ->getParametersByRssId($rssId);

        if($notificationUserParameters === null) {
            throw new NoResultException();
        }

        return $this->getUserNotificationsList(
            $notificationUserParameters->getUserId(),
            1,
            $maxResult,
            true
        );
    }

    protected function renderNotifications($notificationsViews)
    {
        $views = array();
        $colorChooser = new ColorChooser();
        $unviewedNotificationIds = array();
        foreach ($notificationsViews as $notificationView) {
            $notification = $notificationView->getNotification();
            $iconKey = $notification->getIconKey();
            if (!empty($iconKey)) {
                $notificationColor = $colorChooser->getColorForName($iconKey);
                $notification->setIconColor($notificationColor);
            }
            $eventName = 'create_notification_item_' . $notification->getActionKey();
            $event = new NotificationCreateDelegateViewEvent($notificationView, $this->platformName);

            /** @var EventDispatcher $eventDispatcher */
            if ($this->eventDispatcher->hasListeners($eventName)) {
                $event = $this->eventDispatcher->dispatch($eventName, $event);
                $views[$notificationView->getId() . ''] = $event->getResponseContent();
            }
            if ($notificationView->getStatus() == false) {
                array_push(
                    $unviewedNotificationIds,
                    $notificationView->getId()
                );
            }
        }
        $this->markNotificationsAsViewed($unviewedNotificationIds);

        return $views;
    }

    /**
     * @param int $userId
     * @param int $resourceId
     * @param string $resourceClass
     *
     * @return
     */
    public function getFollowerResource($userId, $resourceId, $resourceClass)
    {
        $followerResource = $this->getFollowerResourceRepository()->findOneBy(
            array(
                'followerId' => $userId,
                'hash'       => $this->getHash($resourceId, $resourceClass)
            )
        );

        return $followerResource;
    }

    public function getTaggedUsersFromText($text)
    {

    }

    /**
     * @param $userId
     * @param $resourceId
     * @param $resourceClass
     * @return FollowerResource
     */
    public function followResource($userId, $resourceId, $resourceClass)
    {
        $followerResource = new FollowerResource();
        $followerResource->setFollowerId($userId);
        $followerResource->setResourceId($resourceId);
        $followerResource->setHash($this->getHash($resourceId, $resourceClass));
        $followerResource->setResourceClass($resourceClass);

        $this->getEntityManager()->persist($followerResource);
        $this->getEntityManager()->flush();

        return $followerResource;
    }

    /**
     * @param $userId
     * @param $resourceId
     * @param $resourceClass
     * @return mixed
     */
    public function unfollowResource($userId, $resourceId, $resourceClass)
    {
        $followerResource = $this->getFollowerResource($userId, $resourceId, $resourceClass);

        if (!empty($followerResource)) {
            $this->getEntityManager()->remove($followerResource);
            $this->getEntityManager()->flush();
        }

        return $followerResource;
    }

    /**
     * @param $notificationViewIds
     */
    public function markNotificationsAsViewed($notificationViewIds)
    {
        if (!empty($notificationViewIds)) {
            $this->getNotificationViewerRepository()->markAsViewed($notificationViewIds);
        }
    }

    /**
     * @param  null $viewerId
     * @return int
     */
    public function countUnviewedNotifications($viewerId = null)
    {
        if (empty($viewerId)) {
            $viewerId = $this->security->getToken()->getUser()->getId();
        }
        $notificationParameters = $this->notificationParametersManager->getParametersByUserId($viewerId);

        return intval($this->getNotificationViewerRepository()->countUnviewedNotifications($viewerId, $notificationParameters)["total"]);
    }
}
