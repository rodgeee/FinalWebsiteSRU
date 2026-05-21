<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122133658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products DROP stocks');
        $this->addSql('ALTER TABLE stocks ADD product_id INT NOT NULL, DROP products');
        $this->addSql('ALTER TABLE stocks ADD CONSTRAINT FK_56F798054584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_56F798054584665A ON stocks (product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products ADD stocks INT NOT NULL');
        $this->addSql('ALTER TABLE stocks DROP FOREIGN KEY FK_56F798054584665A');
        $this->addSql('DROP INDEX IDX_56F798054584665A ON stocks');
        $this->addSql('ALTER TABLE stocks ADD products VARCHAR(255) NOT NULL, DROP product_id');
    }
}
