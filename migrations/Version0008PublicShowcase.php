<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vitrine publique des entreprises : présentation et annonces de disponibles.
 *
 * Trois colonnes sur `organization` décrivent ce que voit un visiteur non
 * authentifié (un `slug` d'URL lisible, un texte de présentation, un
 * interrupteur de mise en ligne), une sur `unit` porte la *décision* de
 * publier une unité, et `unit_photo` porte la galerie.
 *
 * Point de conception important : `unit.is_published` n'est **pas** l'état de
 * disponibilité. Il exprime une décision éditoriale de l'ADMIN_VILLE ; la
 * visibilité publique, elle, est recalculée à chaque lecture en excluant les
 * unités portant un bail `ACTIVE`. Une unité vacante reste donc
 * automatiquement visible après la fin d'un bail, sans republier quoi que ce
 * soit — d'où l'absence volontaire de tout déclencheur qui remettrait
 * `is_published` à false à l'activation d'un bail.
 */
final class Version0008PublicShowcase extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vitrine publique : slug/description/activation sur organization, is_published sur unit, table unit_photo.';
    }

    public function up(Schema $schema): void
    {
        // `slug` reste nullable : les Organizations existantes n'ont pas de
        // page publique, et forcer la colonne ici obligerait à les renommer.
        // L'unicité ne sert qu'aux pages effectivement publiées — MariaDB
        // autorise d'ailleurs plusieurs NULL sous une contrainte UNIQUE.
        //
        // Les booléens sont écrits en `TINYINT` et non `TINYINT(1)` : c'est
        // la forme que produit la plateforme Doctrine pour `Types::BOOLEAN`
        // sur cette base (cf. `user.is_active`), et s'écrire diffère ensuite
        // de ce qu'attend `doctrine:schema:validate`.
        $this->addSql('ALTER TABLE organization ADD slug VARCHAR(60) DEFAULT NULL, ADD public_description LONGTEXT DEFAULT NULL, ADD is_publicly_listed TINYINT NOT NULL DEFAULT 1, ADD UNIQUE INDEX uniq_organization_slug (slug)');
        $this->addSql('ALTER TABLE unit ADD is_published TINYINT NOT NULL DEFAULT 0');

        // `updated_at` est présent parce que `UnitPhoto` étend
        // `TimestampedEntity` : une photo peut être retirée puis réuploadée, ce
        // qui n'est pas le cas des entités strictement immuables.
        $this->addSql('CREATE TABLE unit_photo (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL, position SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_unit_photo_uuid (uuid), INDEX idx_unit_photo_unit_position (unit_id, position), PRIMARY KEY (id), CONSTRAINT FK_UNIT_PHOTO_UNIT FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unit_photo');
        $this->addSql('ALTER TABLE unit DROP is_published');
        $this->addSql('ALTER TABLE organization DROP is_publicly_listed, DROP public_description, DROP slug');
    }
}