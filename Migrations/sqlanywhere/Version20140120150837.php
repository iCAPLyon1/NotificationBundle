<?php

namespace Icap\NotificationBundle\Migrations\sqlanywhere;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/01/20 03:08:39
 */
class Version20140120150837 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE icap__notification_follower_resource (
                id INT IDENTITY NOT NULL, 
                hash VARCHAR(64) NOT NULL, 
                resource_class VARCHAR(255) NOT NULL, 
                resource_id INT NOT NULL, 
                follower_id INT NOT NULL, 
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE TABLE icap__notification (
                id INT IDENTITY NOT NULL, 
                creation_date DATETIME NOT NULL, 
                user_id INT DEFAULT NULL, 
                resource_id INT DEFAULT NULL, 
                icon_key VARCHAR(255) DEFAULT NULL, 
                action_key VARCHAR(255) NOT NULL, 
                target_url VARCHAR(255) NOT NULL, 
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE TABLE icap__notification_viewer (
                id INT IDENTITY NOT NULL, 
                notification_id INT NOT NULL, 
                viewer_id INT NOT NULL, 
                status BIT NULL DEFAULT NULL, 
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_DB60418BEF1A9D84 ON icap__notification_viewer (notification_id)
        ");
        $this->addSql("
            ALTER TABLE icap__notification_viewer 
            ADD CONSTRAINT FK_DB60418BEF1A9D84 FOREIGN KEY (notification_id) 
            REFERENCES icap__notification (id) 
            ON DELETE CASCADE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE icap__notification_viewer 
            DROP FOREIGN KEY FK_DB60418BEF1A9D84
        ");
        $this->addSql("
            DROP TABLE icap__notification_follower_resource
        ");
        $this->addSql("
            DROP TABLE icap__notification
        ");
        $this->addSql("
            DROP TABLE icap__notification_viewer
        ");
    }
}