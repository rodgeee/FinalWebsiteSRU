<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop username and profile_picture columns from adminuser (if they exist).
 */
final class Version20251208110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove username and profile_picture columns from adminuser';
    }

    public function up(Schema $schema): void
    {
        // Drop username if present
        $this->addSql("
            SET @col := (
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'adminuser'
                  AND column_name = 'username'
            )
        ");
        $this->addSql("
            SET @sql := IF(
                @col > 0,
                'ALTER TABLE adminuser DROP COLUMN username',
                'SELECT 1'
            )
        ");
        $this->addSql("PREPARE stmt FROM @sql");
        $this->addSql("EXECUTE stmt");
        $this->addSql("DEALLOCATE PREPARE stmt");

        // Drop profile_picture if present
        $this->addSql("
            SET @col := (
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'adminuser'
                  AND column_name = 'profile_picture'
            )
        ");
        $this->addSql("
            SET @sql := IF(
                @col > 0,
                'ALTER TABLE adminuser DROP COLUMN profile_picture',
                'SELECT 1'
            )
        ");
        $this->addSql("PREPARE stmt2 FROM @sql");
        $this->addSql("EXECUTE stmt2");
        $this->addSql("DEALLOCATE PREPARE stmt2");
    }

    public function down(Schema $schema): void
    {
        // Re-add columns as nullable if rollback
        $this->addSql("
            SET @col := (
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'adminuser'
                  AND column_name = 'username'
            )
        ");
        $this->addSql("
            SET @sql := IF(
                @col = 0,
                'ALTER TABLE adminuser ADD COLUMN username VARCHAR(255) DEFAULT NULL',
                'SELECT 1'
            )
        ");
        $this->addSql("PREPARE stmt FROM @sql");
        $this->addSql("EXECUTE stmt");
        $this->addSql("DEALLOCATE PREPARE stmt");

        $this->addSql("
            SET @col := (
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'adminuser'
                  AND column_name = 'profile_picture'
            )
        ");
        $this->addSql("
            SET @sql := IF(
                @col = 0,
                'ALTER TABLE adminuser ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL',
                'SELECT 1'
            )
        ");
        $this->addSql("PREPARE stmt2 FROM @sql");
        $this->addSql("EXECUTE stmt2");
        $this->addSql("DEALLOCATE PREPARE stmt2");
    }
}

