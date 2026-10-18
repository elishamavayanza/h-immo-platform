<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018130000OrganizationCapacityLimits extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les limites de capacité configurables par organisation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD max_cities INT UNSIGNED DEFAULT NULL, ADD max_parcels INT UNSIGNED DEFAULT NULL, ADD max_buildings INT UNSIGNED DEFAULT NULL, ADD max_units INT UNSIGNED DEFAULT NULL, ADD max_members INT UNSIGNED DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP max_cities, DROP max_parcels, DROP max_buildings, DROP max_units, DROP max_members');
    }
}
