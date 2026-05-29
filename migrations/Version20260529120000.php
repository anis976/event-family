<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index unique sur ef_users.pseudo (hors NULL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_ef_users_pseudo ON ef_users (pseudo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ef_users_pseudo ON ef_users');
    }
}
