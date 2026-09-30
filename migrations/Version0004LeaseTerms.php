<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute `lease.terms` et retire la contrainte d'unicité sur le bail actif.
 *
 * Cette migration annule partiellement Version0003ActiveLeasePerUnit :
 * la colonne générée `active_unit_id` et l'index unique
 * `uniq_active_lease_per_unit` sont supprimés, donc la garantie « un seul
 * bail actif par unité » n'est plus portée par la base (contrôle en PHP
 * uniquement). Le bail garde des conditions (`terms`) textuelles.
 */
final class Version0004LeaseTerms extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute lease.terms et retire la contrainte unique de bail actif par unité (annule Version0003)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_active_lease_per_unit ON lease');
        $this->addSql('ALTER TABLE lease ADD terms LONGTEXT DEFAULT NULL, DROP active_unit_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lease ADD active_unit_id BIGINT DEFAULT NULL, DROP terms');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_lease_per_unit ON lease (active_unit_id)');
    }
}
