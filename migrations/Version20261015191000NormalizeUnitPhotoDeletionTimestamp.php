<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015191000NormalizeUnitPhotoDeletionTimestamp extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne la colonne deleted_at des photos sur le mapping datetime partagé du projet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit_photo MODIFY deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit_photo MODIFY deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
