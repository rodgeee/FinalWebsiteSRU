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
        $this->addSql('ALTER TABLE staff ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, ADD verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_STAFF_VERIFICATION_TOKEN ON staff (verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_STAFF_VERIFICATION_TOKEN ON staff');
        $this->addSql('ALTER TABLE staff DROP is_verified, DROP verification_token');
    }
}

