<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Préférence e-mail pour les nouveaux messages privés (notify_private_message_email).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD notify_private_message_email TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP notify_private_message_email');
    }
}
