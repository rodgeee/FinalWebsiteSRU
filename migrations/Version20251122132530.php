<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122132530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Duplicate stocks create (no-op; table is created in Version20251122132300)';
    }

    public function up(Schema $schema): void
    {
        // Duplicate of Version20251122132300 — safe on DBs that already have stocks.
        if ($schema->hasTable('stocks')) {
            return;
        }

        $this->addSql('CREATE TABLE stocks (id INT AUTO_INCREMENT NOT NULL, products VARCHAR(255) NOT NULL, quantity INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Do not drop stocks here; Version20251122132300 owns the table lifecycle.
    }
}
