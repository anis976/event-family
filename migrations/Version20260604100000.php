<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute google_id et oauth_registration_complete sur ef_users (connexion Google OAuth).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_users ADD google_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_ef_users_google_id ON ef_users (google_id)');
        $this->addSql('ALTER TABLE ef_users ADD oauth_registration_complete TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ef_users_google_id ON ef_users');
        $this->addSql('ALTER TABLE ef_users DROP google_id');
        $this->addSql('ALTER TABLE ef_users DROP oauth_registration_complete');
    }
}
