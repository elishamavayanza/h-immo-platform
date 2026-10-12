<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de la colonne `photos` (JSON) à la table `building`
 * pour stocker les noms des fichiers photos du bâtiment.
 */
final class Version20261010120000BuildingPhotosJson extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photos JSON column to building table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE building ADD photos JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE building DROP photos');
    }
}