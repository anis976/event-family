<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Annonces staff RapproFam dans les messages de groupe (ef_messages.is_staff_announcement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages ADD is_staff_announcement TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages DROP is_staff_announcement');
    }
}
