<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate staff rows with ROLE_ADMIN into adminuser, then archive those staff rows.';
    }

    public function up(Schema $schema): void
    {
        // Insert admin users for staff that have ROLE_ADMIN and don’t already exist in adminuser.
        $this->addSql(
            "INSERT INTO adminuser (Fullname, Email, Password, created_at, is_active)
             SELECT s.full_name, s.email, s.password, COALESCE(s.created_at, NOW()), COALESCE(s.is_active, 1)
             FROM staff s
             LEFT JOIN adminuser a ON a.Email = s.email
             WHERE JSON_CONTAINS(s.roles, '\"ROLE_ADMIN\"') AND a.id IS NULL"
        );

        // Archive those staff rows and strip admin roles by renaming email and resetting roles/status.
        $this->addSql(
            "UPDATE staff s
             SET s.email = CONCAT(s.email, '.staff-archived'),
                 s.roles = '[]',
                 s.status = 'archived',
                 s.is_active = 0
             WHERE JSON_CONTAINS(s.roles, '\"ROLE_ADMIN\"')"
        );
    }

    public function down(Schema $schema): void
    {
        // This down migration will delete any adminuser rows that were created by this migration
        // and restore staff rows by removing the '.staff-archived' suffix and re-adding ROLE_ADMIN.

        $this->addSql(
            "DELETE au FROM adminuser au
             WHERE NOT EXISTS (
               SELECT 1 FROM staff s WHERE s.email = CONCAT(au.Email, '.staff-archived')
             )"
        );

        $this->addSql(
            "UPDATE staff s
             SET s.email = REPLACE(s.email, '.staff-archived', ''),
                 s.roles = JSON_ARRAY('ROLE_ADMIN'),
                 s.status = 'active',
                 s.is_active = 1
             WHERE s.email LIKE '%.staff-archived'"
        );
    }
}

