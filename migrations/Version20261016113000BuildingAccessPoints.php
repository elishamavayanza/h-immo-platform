<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016113000BuildingAccessPoints extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les accès physiques de bâtiment, partageables entre plusieurs unités et désactivables sans libérer un logement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE access_point (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', parcel_id BIGINT UNSIGNED NOT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reference VARCHAR(50) NOT NULL, label VARCHAR(120) NOT NULL, type VARCHAR(30) NOT NULL, access_mode VARCHAR(20) NOT NULL, location_description VARCHAR(255) DEFAULT NULL, enabled TINYINT NOT NULL DEFAULT 1, UNIQUE INDEX UNIQ_5E308F77D17F50A6 (uuid), UNIQUE INDEX uniq_access_point_parcel_reference (parcel_id, reference), INDEX IDX_5E308F774D2A7E12 (building_id), INDEX IDX_5E308F77465E670C (parcel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_point ADD CONSTRAINT FK_5E308F772C6A6C8 FOREIGN KEY (parcel_id) REFERENCES parcel (id)');
        $this->addSql('ALTER TABLE access_point ADD CONSTRAINT FK_5E308F774D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('CREATE TABLE access_point_unit (access_point_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, INDEX IDX_7F0573B0AD3D0F93 (access_point_id), INDEX IDX_7F0573B0F8BD700D (unit_id), PRIMARY KEY(access_point_id, unit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_point_unit ADD CONSTRAINT FK_7F0573B0AD3D0F93 FOREIGN KEY (access_point_id) REFERENCES access_point (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_point_unit ADD CONSTRAINT FK_7F0573B0F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE access_point_unit');
        $this->addSql('DROP TABLE access_point');
    }
}
