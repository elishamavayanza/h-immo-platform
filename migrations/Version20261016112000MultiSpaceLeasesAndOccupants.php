<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016112000MultiSpaceLeasesAndOccupants extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permet à un bail de couvrir plusieurs espaces et conserve les périodes des cotitulaires et occupants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lease_unit (lease_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, INDEX IDX_E0E1374CD3CA542C (lease_id), INDEX IDX_E0E1374CF8BD700D (unit_id), PRIMARY KEY(lease_id, unit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lease_unit ADD CONSTRAINT FK_E0E1374CD3CA542C FOREIGN KEY (lease_id) REFERENCES lease (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lease_unit ADD CONSTRAINT FK_E0E1374CF8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE lease_occupant (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', organization_id BIGINT UNSIGNED NOT NULL, lease_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', role VARCHAR(20) NOT NULL, start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', end_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', UNIQUE INDEX UNIQ_A8A65F2BD17F50A6 (uuid), INDEX idx_lease_occupant_tenant (lease_id, tenant_id), INDEX IDX_A8A65F2B32C8A3DE (organization_id), INDEX IDX_A8A65F2BD3CA542C (lease_id), INDEX IDX_A8A65F2B9033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lease_occupant ADD CONSTRAINT FK_A8A65F2B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE lease_occupant ADD CONSTRAINT FK_A8A65F2BD3CA542C FOREIGN KEY (lease_id) REFERENCES lease (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lease_occupant ADD CONSTRAINT FK_A8A65F2B9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lease_occupant');
        $this->addSql('DROP TABLE lease_unit');
    }
}
