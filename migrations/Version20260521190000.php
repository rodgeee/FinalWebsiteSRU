<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external_actor_ref to activity_log so actor_id stays the admin FK.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('activity_log')) {
            return;
        }

        $activity = $schema->getTable('activity_log');
        if (!$activity->hasColumn('external_actor_ref')) {
            $this->addSql('ALTER TABLE activity_log ADD external_actor_ref VARCHAR(36) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('activity_log')) {
            return;
        }

        $activity = $schema->getTable('activity_log');
        if ($activity->hasColumn('external_actor_ref')) {
            $this->addSql('ALTER TABLE activity_log DROP external_actor_ref');
        }
    }
}
