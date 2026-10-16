<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016100000ResidentialMovements extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un historique append-only des mouvements résidentiels des locataires.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE residential_movement (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', organization_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, lease_id BIGINT UNSIGNED DEFAULT NULL, previous_unit_id BIGINT UNSIGNED DEFAULT NULL, next_unit_id BIGINT UNSIGNED DEFAULT NULL, recorded_by_id BIGINT UNSIGNED NOT NULL, type VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, planned_date DATE DEFAULT NULL, effective_date DATE DEFAULT NULL, effective_date_estimated TINYINT(1) DEFAULT 0 NOT NULL, reason VARCHAR(100) DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_RESIDENTIAL_MOVEMENT_UUID (uuid), INDEX idx_res_move_tenant_date (tenant_id, effective_date), INDEX idx_res_move_org_status (organization_id, status), INDEX idx_res_move_lease (lease_id), INDEX idx_res_move_previous_unit (previous_unit_id), INDEX idx_res_move_next_unit (next_unit_id), INDEX idx_res_move_recorded_by (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_ORG FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_LEASE FOREIGN KEY (lease_id) REFERENCES lease (id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_PREVIOUS_UNIT FOREIGN KEY (previous_unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_NEXT_UNIT FOREIGN KEY (next_unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RESIDENTIAL_MOVEMENT_RECORDED_BY FOREIGN KEY (recorded_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE residential_movement');
    }
}
