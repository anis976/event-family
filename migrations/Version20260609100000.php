<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photos dans les messages de groupe (ef_message_photos).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ef_message_photos (id INT AUTO_INCREMENT NOT NULL, message_id INT NOT NULL, filename VARCHAR(64) NOT NULL, position SMALLINT UNSIGNED NOT NULL, INDEX idx_ef_message_photos_message (message_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ef_message_photos ADD CONSTRAINT FK_EF_MESSAGE_PHOTOS_MESSAGE FOREIGN KEY (message_id) REFERENCES ef_messages (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_message_photos DROP FOREIGN KEY FK_EF_MESSAGE_PHOTOS_MESSAGE');
        $this->addSql('DROP TABLE ef_message_photos');
    }
}
