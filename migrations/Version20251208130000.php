<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251208130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create staff table for staff accounts.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('staff')) {
            return;
        }

        $this->addSql("CREATE TABLE staff (
            id INT AUTO_INCREMENT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            roles JSON NOT NULL,
            UNIQUE INDEX UNIQ_STAFF_EMAIL (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE staff');
    }
}


