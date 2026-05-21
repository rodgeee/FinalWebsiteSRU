<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to customer';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('customer')) {
            return;
        }

        $customer = $schema->getTable('customer');
        if (!$customer->hasColumn('is_verified') && !$customer->hasColumn('verification_token')) {
            $this->addSql('ALTER TABLE customer ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, ADD verification_token VARCHAR(64) DEFAULT NULL');
        } else {
            if (!$customer->hasColumn('is_verified')) {
                $this->addSql('ALTER TABLE customer ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
            }
            if (!$customer->hasColumn('verification_token')) {
                $this->addSql('ALTER TABLE customer ADD verification_token VARCHAR(64) DEFAULT NULL');
            }
        }

        if (!$customer->hasIndex('uniq_81398e09a98f4820')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_81398E09A98F4820 ON customer (verification_token)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_81398E09A98F4820 ON customer');
        $this->addSql('ALTER TABLE customer DROP is_verified, DROP verification_token');
    }
}

