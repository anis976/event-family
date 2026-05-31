<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events : photo couverture + photo lieu/détail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_events CHANGE photo photo_cover VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ef_events ADD photo_detail VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_events DROP photo_detail');
        $this->addSql('ALTER TABLE ef_events CHANGE photo_cover photo VARCHAR(255) DEFAULT NULL');
    }
}
