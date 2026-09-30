<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table des taux de change historiques.
 *
 * Permet de figer le taux utilisé pour chaque opération financière.
 * Une entrée = un taux valable sur une période [effectiveFrom, effectiveTo[.
 * Un changement de taux crée une nouvelle ligne, sans modifier les anciennes.
 */
final class Version0005ExchangeRate extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table exchange_rate pour l\'historique des taux de change';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exchange_rate (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, base_currency VARCHAR(255) NOT NULL, quote_currency VARCHAR(255) NOT NULL, rate NUMERIC(18, 8) NOT NULL, effective_from DATETIME NOT NULL, effective_to DATETIME DEFAULT NULL, created_by BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_1F8F3E6D17F50A6 (uuid), UNIQUE INDEX uniq_exchange_rate_pair_from (base_currency, quote_currency, effective_from), INDEX idx_exchange_rate_lookup (base_currency, quote_currency, effective_from), INDEX IDX_1F8F3E6DDE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE exchange_rate ADD CONSTRAINT FK_1F8F3E6DDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exchange_rate DROP FOREIGN KEY FK_1F8F3E6DDE12AB56');
        $this->addSql('DROP TABLE exchange_rate');
    }
}