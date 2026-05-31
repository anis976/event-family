<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avatar utilisateur : original, visibilité, données de recadrage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD avatar_original VARCHAR(255) DEFAULT NULL, ADD avatar_visibility VARCHAR(20) DEFAULT NULL, ADD avatar_crop_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP avatar_original, DROP avatar_visibility, DROP avatar_crop_data');
    }
}
