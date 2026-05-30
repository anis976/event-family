<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nom de groupe unique (ef_groups.name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_ef_groups_name ON ef_groups (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ef_groups_name ON ef_groups');
    }
}
