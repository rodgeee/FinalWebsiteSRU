<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to staff and backfill from is_active.';
    }

    public function up(Schema $schema): void
    {
        $staff = $schema->getTable('staff');
        if (!$staff->hasColumn('status')) {
            $this->addSql("ALTER TABLE staff ADD status VARCHAR(20) NOT NULL DEFAULT 'active'");
            $this->addSql("UPDATE staff SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'disabled' END");
        }
    }

    public function down(Schema $schema): void
    {
        $staff = $schema->getTable('staff');
        if ($staff->hasColumn('status')) {
            $this->addSql('ALTER TABLE staff DROP status');
        }
    }
}

