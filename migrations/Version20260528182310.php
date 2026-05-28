<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528182310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ef_users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, pseudo VARCHAR(64) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, last_login_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, is_banned TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_ef_users_email (email), UNIQUE INDEX uniq_ef_users_full_name (first_name, last_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_groups DROP FOREIGN KEY `FK_156E54F07E3C61F9`');
        $this->addSql('ALTER TABLE app_groups DROP FOREIGN KEY `FK_156E54F0F675F31B`');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY `FK_5387574AB8B83097`');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY `FK_5387574AF675F31B`');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY `FK_A36222A8291A82DC`');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY `FK_A36222A8F717C8DA`');
        $this->addSql('ALTER TABLE group_request DROP FOREIGN KEY `FK_BD97DB9358D797EA`');
        $this->addSql('ALTER TABLE group_request DROP FOREIGN KEY `FK_BD97DB93A76ED395`');
        $this->addSql('ALTER TABLE message_read DROP FOREIGN KEY `FK_31C2DABE537A1329`');
        $this->addSql('ALTER TABLE message_read DROP FOREIGN KEY `FK_31C2DABEA76ED395`');
        $this->addSql('ALTER TABLE message_thread_read DROP FOREIGN KEY `FK_7C990310537A1329`');
        $this->addSql('ALTER TABLE message_thread_read DROP FOREIGN KEY `FK_7C990310A76ED395`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `FK_DB021E961A940236`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `FK_DB021E96727ACA70`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `FK_DB021E96E92F8F78`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `FK_DB021E96F675F31B`');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY `FK_89E8B16E2CE9C1AD`');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY `FK_89E8B16E58D797EA`');
        $this->addSql('ALTER TABLE user_ban DROP FOREIGN KEY `FK_89E8B16EF675F31B`');
        $this->addSql('DROP TABLE app_groups');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('DROP TABLE group_request');
        $this->addSql('DROP TABLE message_read');
        $this->addSql('DROP TABLE message_thread_read');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_ban');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_groups (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, family_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT DEFAULT NULL, owner_id INT DEFAULT NULL, INDEX IDX_156E54F0F675F31B (author_id), INDEX IDX_156E54F07E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, event_type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, location VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, start_date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, visibility VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, photo VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, author_id INT DEFAULT NULL, event_group_id INT NOT NULL, INDEX IDX_5387574AF675F31B (author_id), INDEX IDX_5387574AB8B83097 (event_group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE group_member (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, user_name_id INT NOT NULL, group_name_id INT DEFAULT NULL, INDEX IDX_A36222A8291A82DC (user_name_id), INDEX IDX_A36222A8F717C8DA (group_name_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE group_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, user_id INT NOT NULL, related_group_id INT NOT NULL, INDEX IDX_BD97DB93A76ED395 (user_id), INDEX IDX_BD97DB9358D797EA (related_group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message_read (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME DEFAULT NULL, message_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_31C2DABE537A1329 (message_id), INDEX IDX_31C2DABEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message_thread_read (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME NOT NULL, message_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7C990310537A1329 (message_id), INDEX IDX_7C990310A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, author_id INT NOT NULL, messagegroup_id INT DEFAULT NULL, recipient_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX IDX_DB021E96F675F31B (author_id), INDEX IDX_DB021E961A940236 (messagegroup_id), INDEX IDX_DB021E96E92F8F78 (recipient_id), INDEX IDX_DB021E96727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, roles JSON NOT NULL, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, pseudo VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, firstname VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, lastname VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_verified TINYINT NOT NULL, last_login_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, avatar VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, is_banned TINYINT NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), UNIQUE INDEX UNIQ_IDENTIFIER_NAME (firstname, lastname), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_ban (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, banned_user_id INT NOT NULL, author_id INT DEFAULT NULL, related_group_id INT DEFAULT NULL, INDEX IDX_89E8B16E2CE9C1AD (banned_user_id), INDEX IDX_89E8B16EF675F31B (author_id), INDEX IDX_89E8B16E58D797EA (related_group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE app_groups ADD CONSTRAINT `FK_156E54F07E3C61F9` FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_groups ADD CONSTRAINT `FK_156E54F0F675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT `FK_5387574AB8B83097` FOREIGN KEY (event_group_id) REFERENCES app_groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT `FK_5387574AF675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT `FK_A36222A8291A82DC` FOREIGN KEY (user_name_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT `FK_A36222A8F717C8DA` FOREIGN KEY (group_name_id) REFERENCES app_groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE group_request ADD CONSTRAINT `FK_BD97DB9358D797EA` FOREIGN KEY (related_group_id) REFERENCES app_groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE group_request ADD CONSTRAINT `FK_BD97DB93A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_read ADD CONSTRAINT `FK_31C2DABE537A1329` FOREIGN KEY (message_id) REFERENCES messages (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_read ADD CONSTRAINT `FK_31C2DABEA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_thread_read ADD CONSTRAINT `FK_7C990310537A1329` FOREIGN KEY (message_id) REFERENCES messages (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_thread_read ADD CONSTRAINT `FK_7C990310A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `FK_DB021E961A940236` FOREIGN KEY (messagegroup_id) REFERENCES app_groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `FK_DB021E96727ACA70` FOREIGN KEY (parent_id) REFERENCES messages (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `FK_DB021E96E92F8F78` FOREIGN KEY (recipient_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `FK_DB021E96F675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT `FK_89E8B16E2CE9C1AD` FOREIGN KEY (banned_user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT `FK_89E8B16E58D797EA` FOREIGN KEY (related_group_id) REFERENCES app_groups (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_ban ADD CONSTRAINT `FK_89E8B16EF675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP TABLE ef_users');
    }
}
