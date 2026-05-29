<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demandes d\'adhésion aux groupes (ef_group_requests)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ef_group_requests (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, related_group_id INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, INDEX idx_ef_group_requests_user_group (user_id, related_group_id), INDEX idx_ef_group_requests_group_status (related_group_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ef_group_requests ADD CONSTRAINT FK_EF_GROUP_REQUESTS_USER FOREIGN KEY (user_id) REFERENCES ef_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ef_group_requests ADD CONSTRAINT FK_EF_GROUP_REQUESTS_GROUP FOREIGN KEY (related_group_id) REFERENCES ef_groups (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ef_group_requests DROP FOREIGN KEY FK_EF_GROUP_REQUESTS_USER');
        $this->addSql('ALTER TABLE ef_group_requests DROP FOREIGN KEY FK_EF_GROUP_REQUESTS_GROUP');
        $this->addSql('DROP TABLE ef_group_requests');
    }
}
