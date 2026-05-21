<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122132300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stocks table (skipped if it already exists)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('stocks')) {
            return;
        }

        $this->addSql('CREATE TABLE stocks (id INT AUTO_INCREMENT NOT NULL, products VARCHAR(255) NOT NULL, quantity INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('stocks')) {
            $this->addSql('DROP TABLE stocks');
        }
    }
}
