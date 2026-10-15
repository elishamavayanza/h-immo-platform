<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015170000AdminVillePersonnelPermission extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une autorisation de création du personnel par rattachement admin ville / ville.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_city ADD can_manage_personnel TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_city DROP can_manage_personnel');
    }
}
