<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute une contrainte d'unicité sur les baux actifs par unité.
 *
 * MariaDB 10.5+ supporte les colonnes générées (generated columns) et
 * les index uniques dessus. On ajoute une colonne générée `active_unit_id`
 * qui vaut `unit_id` quand le statut est 'active', NULL sinon.
 * L'index unique sur cette colonne garantit qu'une unité ne peut avoir
 * qu'un seul bail ACTIVE.
 */
final class Version0003ActiveLeasePerUnit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute contrainte unique bail actif par unité (colonne générée + index unique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `lease` ADD COLUMN `active_unit_id` BIGINT GENERATED ALWAYS AS (CASE WHEN `status` = \'active\' THEN `unit_id` ELSE NULL END) STORED');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_lease_per_unit ON `lease` (`active_unit_id`)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX `uniq_active_lease_per_unit` ON `lease`');
        $this->addSql('ALTER TABLE `lease` DROP COLUMN `active_unit_id`');
    }
}