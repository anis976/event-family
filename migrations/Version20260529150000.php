<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tokens réinitialisation mot de passe oublié (ef_users)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL, ADD password_reset_token_expires_at DATETIME DEFAULT NULL, ADD password_reset_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP password_reset_token_hash, DROP password_reset_token_expires_at, DROP password_reset_requested_at');
    }
}
