<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changement de mot de passe : token et hash en attente sur ef_users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD password_change_token_hash VARCHAR(64) DEFAULT NULL, ADD password_change_token_expires_at DATETIME DEFAULT NULL, ADD pending_password_hash VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP password_change_token_hash, DROP password_change_token_expires_at, DROP pending_password_hash');
    }
}
