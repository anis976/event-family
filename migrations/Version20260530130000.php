<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages privés plateforme (ef_messages.is_platform_notice, author_id nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages ADD is_platform_notice TINYINT(1) DEFAULT 0 NOT NULL, ADD platform_notice_variant VARCHAR(20) DEFAULT NULL, CHANGE author_id author_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages DROP is_platform_notice, DROP platform_notice_variant, CHANGE author_id author_id INT NOT NULL');
    }
}
