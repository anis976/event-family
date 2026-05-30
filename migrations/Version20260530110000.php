<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Message système par groupe (ef_groups.system_notice_*)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_groups ADD system_notice_content LONGTEXT DEFAULT NULL, ADD system_notice_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_groups DROP system_notice_content, DROP system_notice_updated_at');
    }
}
