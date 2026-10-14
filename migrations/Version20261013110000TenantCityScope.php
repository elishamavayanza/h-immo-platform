<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Rattache explicitement les locataires sans bail à une ville de l'organisation. */
final class Version20261013110000TenantCityScope extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le rattachement facultatif d’un locataire à une ville pour isoler les listes des administrateurs de ville.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD city_id BIGINT UNSIGNED DEFAULT NULL, ADD INDEX IDX_TENANT_CITY (city_id)');
        $this->addSql('ALTER TABLE tenant ADD CONSTRAINT FK_TENANT_CITY FOREIGN KEY (city_id) REFERENCES city (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP FOREIGN KEY FK_TENANT_CITY');
        $this->addSql('DROP INDEX IDX_TENANT_CITY ON tenant');
        $this->addSql('ALTER TABLE tenant DROP city_id');
    }
}
