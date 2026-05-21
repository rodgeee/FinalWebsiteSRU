<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251215061727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phone_number column to customer table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('customer')) {
            $customer = $schema->getTable('customer');
            if (!$customer->hasColumn('phone_number')) {
                $this->addSql('ALTER TABLE customer ADD phone_number VARCHAR(20) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('customer')) {
            $customer = $schema->getTable('customer');
            if ($customer->hasColumn('phone_number')) {
                $this->addSql('ALTER TABLE customer DROP phone_number');
            }
        }
    }
}
