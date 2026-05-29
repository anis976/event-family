<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie privée et de groupe (ef_messages, ef_message_reads)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ef_messages (id INT AUTO_INCREMENT NOT NULL, author_id INT NOT NULL, recipient_id INT DEFAULT NULL, related_group_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_ef_messages_private (author_id, recipient_id), INDEX idx_ef_messages_group (related_group_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ef_message_reads (id INT AUTO_INCREMENT NOT NULL, message_id INT NOT NULL, user_id INT NOT NULL, read_at DATETIME NOT NULL, UNIQUE INDEX uniq_ef_message_reads_message_user (message_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ef_messages ADD CONSTRAINT FK_EF_MESSAGES_AUTHOR FOREIGN KEY (author_id) REFERENCES ef_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_messages ADD CONSTRAINT FK_EF_MESSAGES_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES ef_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_messages ADD CONSTRAINT FK_EF_MESSAGES_GROUP FOREIGN KEY (related_group_id) REFERENCES ef_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_messages ADD CONSTRAINT FK_EF_MESSAGES_PARENT FOREIGN KEY (parent_id) REFERENCES ef_messages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_message_reads ADD CONSTRAINT FK_EF_MESSAGE_READS_MESSAGE FOREIGN KEY (message_id) REFERENCES ef_messages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_message_reads ADD CONSTRAINT FK_EF_MESSAGE_READS_USER FOREIGN KEY (user_id) REFERENCES ef_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_message_reads DROP FOREIGN KEY FK_EF_MESSAGE_READS_MESSAGE');
        $this->addSql('ALTER TABLE ef_message_reads DROP FOREIGN KEY FK_EF_MESSAGE_READS_USER');
        $this->addSql('ALTER TABLE ef_messages DROP FOREIGN KEY FK_EF_MESSAGES_AUTHOR');
        $this->addSql('ALTER TABLE ef_messages DROP FOREIGN KEY FK_EF_MESSAGES_RECIPIENT');
        $this->addSql('ALTER TABLE ef_messages DROP FOREIGN KEY FK_EF_MESSAGES_GROUP');
        $this->addSql('ALTER TABLE ef_messages DROP FOREIGN KEY FK_EF_MESSAGES_PARENT');
        $this->addSql('DROP TABLE ef_message_reads');
        $this->addSql('DROP TABLE ef_messages');
    }
}
