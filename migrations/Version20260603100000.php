<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MP : masquage par utilisateur et clôture des réponses ; base pour purge 12 mois';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages ADD author_hidden_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ef_messages ADD recipient_hidden_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ef_messages ADD replies_closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_messages DROP author_hidden_at');
        $this->addSql('ALTER TABLE ef_messages DROP recipient_hidden_at');
        $this->addSql('ALTER TABLE ef_messages DROP replies_closed_at');
    }
}
