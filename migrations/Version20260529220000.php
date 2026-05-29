<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire is_active de ef_messages (suppression définitive en cascade)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages DROP is_active');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
