<?php

namespace Icap\NotificationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="Icap\NotificationBundle\Repository\NotificationRepository")
 * @ORM\Table(name="icap__notification")
 */
class Notification
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime", name="creation_date")
     * @Gedmo\Timestampable(on="create")
     */
    protected $creationDate;

    /**
     * @ORM\Column(type="integer", name="user_id", nullable=true)
     */
    protected $userId;

    /**
     * @ORM\Column(type="integer", name="resource_id", nullable=true)
     */
    protected $resourceId;

    /**
     * @ORM\Column(type="string", name="icon_key", nullable=true)
     */
    protected $iconKey;

    /**
     * @ORM\Column(type="string", name="action_key")
     */
    protected $actionKey;

    /**
     * @ORM\Column(type="string", name="target_url")
     */
    protected $targetUrl;

    protected $iconColor = null;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get creationDate
     *
     * @return string
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     * @return Notification
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Get userId
     *
     * @return integer
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set resourceId
     *
     * @param integer $resourceId
     * @return Notification
     */
    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    /**
     * Get resourceId
     *
     * @return integer
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Set iconKey
     *
     * @param string $iconKey
     * @return Notification
     */
    public function setIconKey($iconKey)
    {
        $this->iconKey = $iconKey;

        return $this;
    }

    /**
     * Get $iconKey
     *
     * @return string
     */
    public function getIconKey()
    {
        return $this->iconKey;
    }

    /**
     * Set actionKey
     *
     * @param string $actionKey
     * @return Notification
     */
    public function setActionKey($actionKey)
    {
        $this->actionKey = $actionKey;

        return $this;
    }

    /**
     * Get $actionKey
     *
     * @return string
     */
    public function getActionKey()
    {
        return $this->actionKey;
    }

    /**
     * Set targetUrl
     *
     * @param string $targetUrl
     * @return Notification
     */
    public function setTargetUrl($targetUrl)
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    /**
     * Get $targetUrl
     *
     * @return string
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }

    /**
     * Get $iconColor
     *
     * @return string
     */
    public function getIconColor()
    {
        return $this->iconColor;
    }

    /**
     * Set $iconColor
     *
     * @param string iconColor
     * @return notification
     */
    public function setIconColor($iconColor)
    {
        $this->iconColor = $iconColor;

        return $this;
    }

    /**
     * @return string letter
     */
    public function getIconLetter()
    {
        $letter = null;
        if (!empty($this->iconKey)) {
            $letter = $this->iconKey[0];
        }

        return $letter;
    }
}