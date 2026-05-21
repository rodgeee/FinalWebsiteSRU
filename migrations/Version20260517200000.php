<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique order_number (SRU-YYYYMMDD-####) to orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD order_number VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDERS_ORDER_NUMBER ON orders (order_number)');
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
            ) numbered ON o.id = numbered.id
            SET o.order_number = numbered.generated_number
            WHERE o.order_number IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ORDERS_ORDER_NUMBER ON orders');
        $this->addSql('ALTER TABLE orders DROP order_number');
    }
}
