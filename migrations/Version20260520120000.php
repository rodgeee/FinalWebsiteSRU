<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link Care Lab service jobs to customers (mobile app bookings).';
    }

    public function up(Schema $schema): void
    {
        $services = $schema->getTable('services');
        if (!$services->hasColumn('customer_id')) {
            $this->addSql('ALTER TABLE services ADD customer_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE services ADD CONSTRAINT FK_SERVICES_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_SERVICES_CUSTOMER ON services (customer_id)');
        }
        if (!$services->hasColumn('customer_email')) {
            $this->addSql('ALTER TABLE services ADD customer_email VARCHAR(180) DEFAULT NULL');
        }
        if (!$services->hasColumn('source')) {
            $this->addSql("ALTER TABLE services ADD source VARCHAR(32) NOT NULL DEFAULT 'admin'");
        }
    }

    public function down(Schema $schema): void
    {
        $services = $schema->getTable('services');
        if ($services->hasColumn('customer_id')) {
            $this->addSql('ALTER TABLE services DROP FOREIGN KEY FK_SERVICES_CUSTOMER');
            $this->addSql('DROP INDEX IDX_SERVICES_CUSTOMER ON services');
            $this->addSql('ALTER TABLE services DROP customer_id');
        }
        if ($services->hasColumn('customer_email')) {
            $this->addSql('ALTER TABLE services DROP customer_email');
        }
        if ($services->hasColumn('source')) {
            $this->addSql('ALTER TABLE services DROP source');
        }
    }
}
