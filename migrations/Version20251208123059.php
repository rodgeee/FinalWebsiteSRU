<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208123059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, actor_id INT DEFAULT NULL, actor_name VARCHAR(255) DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, changes JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FD06F64710DAF24A (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64710DAF24A FOREIGN KEY (actor_id) REFERENCES adminuser (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F64710DAF24A');
        $this->addSql('DROP TABLE activity_log');
    }
}
