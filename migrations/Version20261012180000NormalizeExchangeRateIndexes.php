<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Aligne les index de la table exchange_rate sur son mapping Doctrine. */
final class Version20261012180000NormalizeExchangeRateIndexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les index de recherche et de clés étrangères des taux de change sur le mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_exchange_rate_lookup ON exchange_rate (base_currency, quote_currency, effective_from)');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX idx_exchange_rate_org TO IDX_E9521FAB32C8A3DE');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX idx_exchange_rate_city TO IDX_E9521FAB8BAC62AF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX IDX_E9521FAB8BAC62AF TO idx_exchange_rate_city');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX IDX_E9521FAB32C8A3DE TO idx_exchange_rate_org');
        $this->addSql('DROP INDEX idx_exchange_rate_lookup ON exchange_rate');
    }
}
