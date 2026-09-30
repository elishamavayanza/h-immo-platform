<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs de taux de change sur les entités financières.
 *
 * Permet de figer le taux utilisé pour chaque opération (paiement, dépense,
 * loyer, bail, affectation) et de conserver le montant original dans sa
 * devise d'origine pour la traçabilité historique.
 */
final class Version0006ExchangeRateFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute champs exchange_rate, original_amount, original_currency sur payment, expense, rent, lease, worker_assignment';
    }

    public function up(Schema $schema): void
    {
        // payment
        $this->addSql('ALTER TABLE `payment` ADD COLUMN `exchange_rate` NUMERIC(18, 8) DEFAULT NULL, ADD COLUMN `original_amount` NUMERIC(12, 2) DEFAULT NULL, ADD COLUMN `original_currency` VARCHAR(255) DEFAULT NULL');

        // expense
        $this->addSql('ALTER TABLE `expense` ADD COLUMN `exchange_rate` NUMERIC(18, 8) DEFAULT NULL, ADD COLUMN `original_amount` NUMERIC(12, 2) DEFAULT NULL, ADD COLUMN `original_currency` VARCHAR(255) DEFAULT NULL');

        // rent
        $this->addSql('ALTER TABLE `rent` ADD COLUMN `exchange_rate` NUMERIC(18, 8) DEFAULT NULL, ADD COLUMN `original_amount` NUMERIC(12, 2) DEFAULT NULL, ADD COLUMN `original_currency` VARCHAR(255) DEFAULT NULL');

        // lease
        $this->addSql('ALTER TABLE `lease` ADD COLUMN `exchange_rate` NUMERIC(18, 8) DEFAULT NULL, ADD COLUMN `reference_currency` VARCHAR(255) DEFAULT NULL');

        // worker_assignment
        $this->addSql('ALTER TABLE `worker_assignment` ADD COLUMN `exchange_rate` NUMERIC(18, 8) DEFAULT NULL, ADD COLUMN `reference_currency` VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // worker_assignment
        $this->addSql('ALTER TABLE `worker_assignment` DROP COLUMN `exchange_rate`, DROP COLUMN `reference_currency`');

        // lease
        $this->addSql('ALTER TABLE `lease` DROP COLUMN `exchange_rate`, DROP COLUMN `reference_currency`');

        // rent
        $this->addSql('ALTER TABLE `rent` DROP COLUMN `exchange_rate`, DROP COLUMN `original_amount`, DROP COLUMN `original_currency`');

        // expense
        $this->addSql('ALTER TABLE `expense` DROP COLUMN `exchange_rate`, DROP COLUMN `original_amount`, DROP COLUMN `original_currency`');

        // payment
        $this->addSql('ALTER TABLE `payment` DROP COLUMN `exchange_rate`, DROP COLUMN `original_amount`, DROP COLUMN `original_currency`');
    }
}