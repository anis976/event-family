<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tokens confirmation suppression de compte (ef_users)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD account_deletion_token_hash VARCHAR(64) DEFAULT NULL, ADD account_deletion_token_expires_at DATETIME DEFAULT NULL, ADD account_deletion_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users DROP account_deletion_token_hash, DROP account_deletion_token_expires_at, DROP account_deletion_requested_at');
    }
}
