<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016110000NestedPropertySpaces extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les libellés de niveaux et la hiérarchie des espaces locatifs sans déplacer les données existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit ADD parent_unit_id BIGINT UNSIGNED DEFAULT NULL, ADD floor_label VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_unit_parent_unit ON unit (parent_unit_id)');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT fk_unit_parent_unit FOREIGN KEY (parent_unit_id) REFERENCES unit (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY fk_unit_parent_unit');
        $this->addSql('DROP INDEX idx_unit_parent_unit ON unit');
        $this->addSql('ALTER TABLE unit DROP parent_unit_id, DROP floor_label');
    }
}
