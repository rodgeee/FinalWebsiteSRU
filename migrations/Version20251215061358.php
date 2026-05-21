<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251215061358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shoe_size column to customer table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('customer')) {
            // Create customer table if it doesn't exist
            $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_active TINYINT(1) DEFAULT 1 NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, shoe_size VARCHAR(10) DEFAULT NULL, UNIQUE INDEX UNIQ_81398E09E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            // Add shoe_size column if table exists but column doesn't
            $customer = $schema->getTable('customer');
            if (!$customer->hasColumn('shoe_size')) {
                $this->addSql('ALTER TABLE customer ADD shoe_size VARCHAR(10) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $customer = $schema->getTable('customer');
        if ($customer->hasColumn('shoe_size')) {
            $this->addSql('ALTER TABLE customer DROP shoe_size');
        }
    }
}
