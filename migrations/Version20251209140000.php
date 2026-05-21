<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ownership columns and richer activity log metadata for staff auditing.';
    }

    public function up(Schema $schema): void
    {
        // Activity log metadata
        $activity = $schema->getTable('activity_log');
        if (!$activity->hasColumn('actor_id')) {
            $this->addSql('ALTER TABLE activity_log ADD actor_id VARCHAR(36) DEFAULT NULL');
        }
        if (!$activity->hasColumn('actor_role')) {
            $this->addSql('ALTER TABLE activity_log ADD actor_role VARCHAR(50) DEFAULT NULL');
        }
        if (!$activity->hasColumn('target_data')) {
            $this->addSql('ALTER TABLE activity_log ADD target_data VARCHAR(255) DEFAULT NULL');
        }

        // Orders ownership
        $orders = $schema->getTable('orders');
        if (!$orders->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE orders ADD owner_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES staff (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_E52FFDE7E3C61F9 ON orders (owner_id)');
        }

        // Stocks ownership
        $stocks = $schema->getTable('stocks');
        if (!$stocks->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE stocks ADD owner_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE stocks ADD CONSTRAINT FK_96D3F4A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES staff (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_96D3F4A7E3C61F9 ON stocks (owner_id)');
        }

        // Services ownership
        $services = $schema->getTable('services');
        if (!$services->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE services ADD owner_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE services ADD CONSTRAINT FK_7332E169E3C61F9 FOREIGN KEY (owner_id) REFERENCES staff (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_7332E169E3C61F9 ON services (owner_id)');
        }

        // Products ownership
        $products = $schema->getTable('products');
        if (!$products->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE products ADD owner_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5AE3C61F9 FOREIGN KEY (owner_id) REFERENCES staff (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_B3BA5A5AE3C61F9 ON products (owner_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $activity = $schema->getTable('activity_log');
        if ($activity->hasColumn('actor_id')) {
            $this->addSql('ALTER TABLE activity_log DROP actor_id');
        }
        if ($activity->hasColumn('actor_role')) {
            $this->addSql('ALTER TABLE activity_log DROP actor_role');
        }
        if ($activity->hasColumn('target_data')) {
            $this->addSql('ALTER TABLE activity_log DROP target_data');
        }

        $orders = $schema->getTable('orders');
        if ($orders->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDE7E3C61F9');
            $this->addSql('DROP INDEX IDX_E52FFDE7E3C61F9 ON orders');
            $this->addSql('ALTER TABLE orders DROP owner_id');
        }

        $stocks = $schema->getTable('stocks');
        if ($stocks->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE stocks DROP FOREIGN KEY FK_96D3F4A7E3C61F9');
            $this->addSql('DROP INDEX IDX_96D3F4A7E3C61F9 ON stocks');
            $this->addSql('ALTER TABLE stocks DROP owner_id');
        }

        $services = $schema->getTable('services');
        if ($services->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE services DROP FOREIGN KEY FK_7332E169E3C61F9');
            $this->addSql('DROP INDEX IDX_7332E169E3C61F9 ON services');
            $this->addSql('ALTER TABLE services DROP owner_id');
        }

        $products = $schema->getTable('products');
        if ($products->hasColumn('owner_id')) {
            $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5AE3C61F9');
            $this->addSql('DROP INDEX IDX_B3BA5A5AE3C61F9 ON products');
            $this->addSql('ALTER TABLE products DROP owner_id');
        }
    }
}


