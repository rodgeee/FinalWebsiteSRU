<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_address table for saved shipping addresses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_address (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL,
            label VARCHAR(50) NOT NULL,
            address_line1 VARCHAR(255) NOT NULL,
            address_line2 VARCHAR(255) DEFAULT NULL,
            city VARCHAR(120) NOT NULL,
            province VARCHAR(120) DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            country VARCHAR(120) NOT NULL,
            contact_email VARCHAR(180) DEFAULT NULL,
            contact_phone VARCHAR(40) DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CUSTOMER_ADDRESS_CUSTOMER (customer_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_CUSTOMER_ADDRESS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_CUSTOMER_ADDRESS_CUSTOMER');
        $this->addSql('DROP TABLE customer_address');
    }
}
