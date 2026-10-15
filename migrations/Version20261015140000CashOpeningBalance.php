<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Ajoute un solde d'ouverture daté par ville et devise pour calculer la caisse. */
final class Version20261015140000CashOpeningBalance extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le point de départ audité des espèces, puis calcule le solde à partir des flux existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cash_opening_balance (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, currency VARCHAR(3) NOT NULL, opening_balance NUMERIC(12, 2) NOT NULL, opening_date DATE NOT NULL, UNIQUE INDEX UNIQ_8CF37043D17F50A6 (uuid), UNIQUE INDEX uniq_cash_opening_city_currency (city_id, currency), INDEX idx_cash_opening_org (organization_id), INDEX IDX_8CF370438BAC62AF (city_id), INDEX IDX_8CF3704316FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cash_opening_balance ADD CONSTRAINT FK_CASH_OPENING_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cash_opening_balance ADD CONSTRAINT FK_CASH_OPENING_CITY FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cash_opening_balance ADD CONSTRAINT FK_CASH_OPENING_USER FOREIGN KEY (updated_by) REFERENCES user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cash_opening_balance');
    }
}
