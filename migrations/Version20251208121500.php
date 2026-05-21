<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251208121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_log table for staff audit trail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT NOT NULL,
            actor_id INT DEFAULT NULL,
            actor_name VARCHAR(255) DEFAULT NULL,
            actor_email VARCHAR(180) DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            changes JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_ACTIVITY_LOG_ACTOR (actor_id),
            INDEX IDX_ACTIVITY_LOG_ACTION (action),
            INDEX IDX_ACTIVITY_LOG_ENTITY (entity_type),
            CONSTRAINT FK_ACTIVITY_LOG_ACTOR FOREIGN KEY (actor_id) REFERENCES adminuser (id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_log');
    }
}


