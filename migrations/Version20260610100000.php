<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tokens vérification e-mail inscription (ef_users.verification_token_*).';
    }

    public function up(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('ef_users');

        if (!$table->hasColumn('verification_token_hash')) {
            $this->addSql('ALTER TABLE ef_users ADD verification_token_hash VARCHAR(64) DEFAULT NULL');
        }

        if (!$table->hasColumn('verification_token_expires_at')) {
            $this->addSql('ALTER TABLE ef_users ADD verification_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        if (!$table->hasIndex('uniq_ef_users_verification_token')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_ef_users_verification_token ON ef_users (verification_token_hash)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ef_users_verification_token ON ef_users');
        $this->addSql('ALTER TABLE ef_users DROP verification_token_hash');
        $this->addSql('ALTER TABLE ef_users DROP verification_token_expires_at');
    }
}
