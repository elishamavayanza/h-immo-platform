<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120000OrganizationShowcaseEntitlement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’autorisation plateforme d’utiliser la vitrine pour chaque organisation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD showcase_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP showcase_enabled');
    }
}
