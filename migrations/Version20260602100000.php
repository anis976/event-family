<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Events : table ef_events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ef_events (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, kind VARCHAR(50) NOT NULL, location VARCHAR(255) DEFAULT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', visibility VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', author_id INT DEFAULT NULL, group_id INT NOT NULL, INDEX idx_ef_events_start_date (start_date), INDEX idx_ef_events_visibility (visibility), INDEX IDX_EF_EVENTS_AUTHOR (author_id), INDEX IDX_EF_EVENTS_GROUP (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ef_events ADD CONSTRAINT FK_EF_EVENTS_AUTHOR FOREIGN KEY (author_id) REFERENCES ef_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ef_events ADD CONSTRAINT FK_EF_EVENTS_GROUP FOREIGN KEY (group_id) REFERENCES ef_groups (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_events DROP FOREIGN KEY FK_EF_EVENTS_AUTHOR');
        $this->addSql('ALTER TABLE ef_events DROP FOREIGN KEY FK_EF_EVENTS_GROUP');
        $this->addSql('DROP TABLE ef_events');
    }
}
