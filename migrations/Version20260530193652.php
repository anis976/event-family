<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530193652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ef_message_reads RENAME INDEX fk_ef_message_reads_user TO IDX_866A2066A76ED395');
        $this->addSql('ALTER TABLE ef_messages RENAME INDEX fk_ef_messages_recipient TO IDX_8536B0F9E92F8F78');
        $this->addSql('ALTER TABLE ef_messages RENAME INDEX fk_ef_messages_parent TO IDX_8536B0F9727ACA70');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ef_message_reads RENAME INDEX idx_866a2066a76ed395 TO FK_EF_MESSAGE_READS_USER');
        $this->addSql('ALTER TABLE ef_messages RENAME INDEX idx_8536b0f9e92f8f78 TO FK_EF_MESSAGES_RECIPIENT');
        $this->addSql('ALTER TABLE ef_messages RENAME INDEX idx_8536b0f9727aca70 TO FK_EF_MESSAGES_PARENT');
    }
}
