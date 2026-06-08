<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronise is_banned avec les suspensions site actives (ef_user_bans sans groupe).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE ef_users u
            SET is_banned = 1
            WHERE is_banned = 0
              AND EXISTS (
                SELECT 1
                FROM ef_user_bans b
                WHERE b.banned_user_id = u.id
                  AND b.related_group_id IS NULL
                  AND (b.ends_at IS NULL OR b.ends_at > NOW())
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Non réversible sans historique : les suspensions site restent dans ef_user_bans.
    }
}
