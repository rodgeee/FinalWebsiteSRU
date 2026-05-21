<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen orders.remarks to TEXT for web checkout payload';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders CHANGE remarks remarks LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders CHANGE remarks remarks VARCHAR(500) DEFAULT NULL');
    }
}
