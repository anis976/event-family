<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table ef_user_bans (bannissement par groupe pour la messagerie).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ef_user_bans (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, banned_user_id INT NOT NULL, author_id INT DEFAULT NULL, related_group_id INT DEFAULT NULL, INDEX idx_ef_user_bans_user_group (banned_user_id, related_group_id), INDEX IDX_EF_USER_BANS_AUTHOR (author_id), INDEX IDX_EF_USER_BANS_GROUP (related_group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ef_user_bans ADD CONSTRAINT FK_EF_USER_BANS_BANNED_USER FOREIGN KEY (banned_user_id) REFERENCES ef_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_user_bans ADD CONSTRAINT FK_EF_USER_BANS_AUTHOR FOREIGN KEY (author_id) REFERENCES ef_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ef_user_bans ADD CONSTRAINT FK_EF_USER_BANS_GROUP FOREIGN KEY (related_group_id) REFERENCES ef_groups (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_user_bans DROP FOREIGN KEY FK_EF_USER_BANS_BANNED_USER');
        $this->addSql('ALTER TABLE ef_user_bans DROP FOREIGN KEY FK_EF_USER_BANS_AUTHOR');
        $this->addSql('ALTER TABLE ef_user_bans DROP FOREIGN KEY FK_EF_USER_BANS_GROUP');
        $this->addSql('DROP TABLE ef_user_bans');
    }
}
