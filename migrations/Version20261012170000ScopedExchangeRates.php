<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les portées organisation/ville aux taux et le droit délégué aux ADMIN_VILLE.
 */
final class Version20261012170000ScopedExchangeRates extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les taux de change historiques par organisation/ville et le droit de gestion délégué aux administrateurs ville';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exchange_rate DROP INDEX uniq_exchange_rate_pair_from');
        $this->addSql("ALTER TABLE exchange_rate ADD scope_key VARCHAR(60) NOT NULL DEFAULT 'platform', ADD organization_id BIGINT UNSIGNED DEFAULT NULL, ADD city_id BIGINT UNSIGNED DEFAULT NULL");
        $this->addSql('ALTER TABLE exchange_rate ADD CONSTRAINT FK_EXCHANGE_RATE_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE exchange_rate ADD CONSTRAINT FK_EXCHANGE_RATE_CITY FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_rate_scope_from ON exchange_rate (scope_key, base_currency, quote_currency, effective_from)');
        $this->addSql('CREATE INDEX idx_exchange_rate_org ON exchange_rate (organization_id)');
        $this->addSql('CREATE INDEX idx_exchange_rate_city ON exchange_rate (city_id)');
        $this->addSql('ALTER TABLE organization_user ADD can_manage_city_exchange_rate TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_user DROP COLUMN can_manage_city_exchange_rate');
        // Les lignes de portée nouvelle ne peuvent pas être représentées par
        // l’ancien modèle global; leur retrait est limité au rollback explicite.
        $this->addSql("DELETE FROM exchange_rate WHERE organization_id IS NOT NULL OR city_id IS NOT NULL");
        $this->addSql('DROP INDEX idx_exchange_rate_city ON exchange_rate');
        $this->addSql('DROP INDEX idx_exchange_rate_org ON exchange_rate');
        $this->addSql('DROP INDEX uniq_exchange_rate_scope_from ON exchange_rate');
        $this->addSql('ALTER TABLE exchange_rate DROP FOREIGN KEY FK_EXCHANGE_RATE_CITY');
        $this->addSql('ALTER TABLE exchange_rate DROP FOREIGN KEY FK_EXCHANGE_RATE_ORGANIZATION');
        $this->addSql('ALTER TABLE exchange_rate DROP COLUMN city_id, DROP COLUMN organization_id, DROP COLUMN scope_key');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_rate_pair_from ON exchange_rate (base_currency, quote_currency, effective_from)');
    }
}
