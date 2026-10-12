<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Définit la devise de référence des synthèses financières de chaque organisation. */
final class Version20261012190000OrganizationReportingCurrency extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une devise de référence USD/CDF aux organisations pour leurs rapports financiers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization ADD currency VARCHAR(3) NOT NULL DEFAULT 'CDF'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP currency');
    }
}
