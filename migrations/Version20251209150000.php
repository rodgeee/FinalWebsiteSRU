<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track account status and creation timestamps for admin and staff users.';
    }

    public function up(Schema $schema): void
    {
        $staff = $schema->getTable('staff');
        if (!$staff->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE staff ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NULL');
        }
        // Backfill any null dates (covers existing rows or earlier partial attempts)
        $this->addSql("UPDATE staff SET created_at = NOW() WHERE created_at IS NULL");
        if (!$staff->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE staff ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        }

        $admin = $schema->getTable('adminuser');
        if (!$admin->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE adminuser ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NULL');
        }
        $this->addSql("UPDATE adminuser SET created_at = NOW() WHERE created_at IS NULL");
        if (!$admin->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE adminuser ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $staff = $schema->getTable('staff');
        if ($staff->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE staff DROP created_at');
        }
        if ($staff->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE staff DROP is_active');
        }

        $admin = $schema->getTable('adminuser');
        if ($admin->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE adminuser DROP created_at');
        }
        if ($admin->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE adminuser DROP is_active');
        }
    }
}

