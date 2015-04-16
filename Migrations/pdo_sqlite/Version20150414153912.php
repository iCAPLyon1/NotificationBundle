<?php

namespace Icap\NotificationBundle\Migrations\pdo_sqlite;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2015/04/14 03:39:15
 */
class Version20150414153912 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE icap__notification_plugin_configuration (
                id INTEGER NOT NULL, 
                dropdown_items INTEGER NOT NULL, 
                max_per_page INTEGER NOT NULL, 
                purge_enabled BOOLEAN NOT NULL, 
                purge_after_days INTEGER NOT NULL, 
                last_purge_date DATETIME DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE icap__notification_plugin_configuration
        ");
    }
}