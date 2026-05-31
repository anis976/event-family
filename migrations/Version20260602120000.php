<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events : colonne photo optionnelle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_events ADD photo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_events DROP photo');
    }
}
