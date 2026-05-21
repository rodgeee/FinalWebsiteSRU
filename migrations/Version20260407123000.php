<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to staff';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('staff')) {
            return;
        }

        $staff = $schema->getTable('staff');
        if (!$staff->hasColumn('is_verified') && !$staff->hasColumn('verification_token')) {
            $this->addSql('ALTER TABLE staff ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, ADD verification_token VARCHAR(64) DEFAULT NULL');
        } else {
            if (!$staff->hasColumn('is_verified')) {
                $this->addSql('ALTER TABLE staff ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
            }
            if (!$staff->hasColumn('verification_token')) {
                $this->addSql('ALTER TABLE staff ADD verification_token VARCHAR(64) DEFAULT NULL');
            }
        }

        if (!$staff->hasIndex('uniq_staff_verification_token')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_STAFF_VERIFICATION_TOKEN ON staff (verification_token)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_STAFF_VERIFICATION_TOKEN ON staff');
        $this->addSql('ALTER TABLE staff DROP is_verified, DROP verification_token');
    }
}

