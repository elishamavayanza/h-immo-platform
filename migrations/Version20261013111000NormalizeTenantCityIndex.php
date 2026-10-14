<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Aligne le nom de l’index ville des locataires sur le nom Doctrine généré. */
final class Version20261013111000NormalizeTenantCityIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme l’index de rattachement ville des locataires selon la convention Doctrine du projet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant RENAME INDEX idx_tenant_city TO IDX_4E59C4628BAC62AF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant RENAME INDEX IDX_4E59C4628BAC62AF TO idx_tenant_city');
    }
}
