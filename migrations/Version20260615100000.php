<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cercle des responsables (groupe système) et partage d\'événements publics.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->introspectTable('ef_groups')->hasColumn('is_staff_circle')) {
            $this->connection->executeStatement('ALTER TABLE ef_groups ADD is_staff_circle TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$schemaManager->introspectTable('ef_events')->hasColumn('shared_in_staff_circle')) {
            $this->connection->executeStatement('ALTER TABLE ef_events ADD shared_in_staff_circle TINYINT(1) DEFAULT 0 NOT NULL');
        }

        $existing = $this->connection->fetchOne('SELECT id FROM ef_groups WHERE is_staff_circle = 1 LIMIT 1');
        if (false === $existing || null === $existing) {
            $description = 'Espace réservé aux chefs et modérateurs de chaque groupe familial. '
                .'Vous y êtes ajouté(e) automatiquement lorsque vous obtenez ce rôle. '
                .'Partagez ici vos événements publics pour informer les autres responsables de ce qui se passe dans votre groupe.';
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s');

            $this->connection->executeStatement(
                'INSERT INTO ef_groups (name, family_name, description, is_staff_circle, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)',
                ['Cercle des responsables', 'RapproFam', $description, $now, $now],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM ef_groups WHERE is_staff_circle = 1');
        $this->addSql('ALTER TABLE ef_events DROP shared_in_staff_circle');
        $this->addSql('ALTER TABLE ef_groups DROP is_staff_circle');
    }
}
