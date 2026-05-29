<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528194346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables ef_groups et ef_group_members + contrainte 1 groupe créé max par owner.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ef_group_members (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, joined_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, user_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_722C35CFA76ED395 (user_id), INDEX IDX_722C35CFFE54D947 (group_id), UNIQUE INDEX uniq_ef_group_members_user_group (user_id, group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ef_groups (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, family_name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT DEFAULT NULL, owner_id INT DEFAULT NULL, INDEX IDX_724B78F4F675F31B (author_id), UNIQUE INDEX uniq_ef_groups_owner (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ef_group_members ADD CONSTRAINT FK_722C35CFA76ED395 FOREIGN KEY (user_id) REFERENCES ef_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_group_members ADD CONSTRAINT FK_722C35CFFE54D947 FOREIGN KEY (group_id) REFERENCES ef_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_groups ADD CONSTRAINT FK_724B78F4F675F31B FOREIGN KEY (author_id) REFERENCES ef_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ef_groups ADD CONSTRAINT FK_724B78F47E3C61F9 FOREIGN KEY (owner_id) REFERENCES ef_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ef_group_members DROP FOREIGN KEY FK_722C35CFA76ED395');
        $this->addSql('ALTER TABLE ef_group_members DROP FOREIGN KEY FK_722C35CFFE54D947');
        $this->addSql('ALTER TABLE ef_groups DROP FOREIGN KEY FK_724B78F4F675F31B');
        $this->addSql('ALTER TABLE ef_groups DROP FOREIGN KEY FK_724B78F47E3C61F9');
        $this->addSql('DROP TABLE ef_group_members');
        $this->addSql('DROP TABLE ef_groups');
    }
}
