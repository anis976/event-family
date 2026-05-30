<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suivi avertissements inactivité compte (ef_users.inactive_warning_*)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD inactive_warning_count INT DEFAULT 0 NOT NULL, ADD last_inactive_warning_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP inactive_warning_count, DROP last_inactive_warning_at');
    }
}
