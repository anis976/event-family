<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260529121053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ef_user_bans RENAME INDEX idx_ef_user_bans_author TO IDX_6CDE2382F675F31B');
        $this->addSql('ALTER TABLE ef_user_bans RENAME INDEX idx_ef_user_bans_group TO IDX_6CDE238258D797EA');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ef_user_bans RENAME INDEX idx_6cde2382f675f31b TO IDX_EF_USER_BANS_AUTHOR');
        $this->addSql('ALTER TABLE ef_user_bans RENAME INDEX idx_6cde238258d797ea TO IDX_EF_USER_BANS_GROUP');
    }
}
