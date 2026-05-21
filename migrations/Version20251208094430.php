<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ensure adminuser.username column exists and is nullable.
 */
final class Version20251208094430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username column to adminuser if missing (nullable)';
    }

    public function up(Schema $schema): void
    {
        // Add column only if it does not exist (MySQL-compatible)
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
    }

    public function down(Schema $schema): void
    {
        // Drop column only if it exists (MySQL-compatible)
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
    }
}

