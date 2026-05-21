<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Idempotent: ensures order_number exists (fixes DBs where 20260517200000 was skipped or failed).
 */
final class Version20260517210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure orders.order_number column exists and backfill SRU codes';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('orders');

        if (!$table->hasColumn('order_number')) {
            $this->addSql('ALTER TABLE orders ADD order_number VARCHAR(32) DEFAULT NULL');
        }

        $indexes = $table->getIndexes();
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index->getColumns() === ['order_number'] && $index->isUnique()) {
                $hasUnique = true;
                break;
            }
        }

        if (!$hasUnique) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDERS_ORDER_NUMBER ON orders (order_number)');
        }

        $this->addSql('UPDATE orders o
            INNER JOIN (
                SELECT
                    id,
                    CONCAT(
                        \'SRU-\',
                        DATE_FORMAT(date_created, \'%Y%m%d\'),
                        \'-\',
                        LPAD(ROW_NUMBER() OVER (PARTITION BY DATE(date_created) ORDER BY id), 4, \'0\')
                    ) AS generated_number
                FROM orders
                WHERE order_number IS NULL OR order_number = \'\'
            ) numbered ON o.id = numbered.id
            SET o.order_number = numbered.generated_number');
    }

    public function down(Schema $schema): void
    {
        // no-op: do not drop column on rollback of this safety migration
    }
}
