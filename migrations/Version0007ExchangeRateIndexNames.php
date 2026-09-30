<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne les noms d'index de `exchange_rate` sur le mapping Doctrine.
 *
 * Remplace l'index composite `idx_exchange_rate_lookup` par les noms
 * d'index explicites déclarés dans l'entité, afin que le schéma reste
 * synchronisé avec Doctrine.
 */
final class Version0007ExchangeRateIndexNames extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les noms d’index de exchange_rate sur le mapping Doctrine';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_exchange_rate_lookup ON exchange_rate');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX uniq_1f8f3e6d17f50a6 TO UNIQ_E9521FABD17F50A6');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX idx_1f8f3e6dde12ab56 TO IDX_E9521FABDE12AB56');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_exchange_rate_lookup ON exchange_rate (base_currency, quote_currency, effective_from)');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX idx_e9521fabde12ab56 TO IDX_1F8F3E6DDE12AB56');
        $this->addSql('ALTER TABLE exchange_rate RENAME INDEX uniq_e9521fabd17f50a6 TO UNIQ_1F8F3E6D17F50A6');
    }
}
