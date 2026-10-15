<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Crée les interventions de maintenance rattachées au périmètre d'une ville. */
final class Version20261015150000MaintenanceWorkOrders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des interventions de maintenance par organisation, ville et bien.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE maintenance_work_order (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, parcel_id BIGINT UNSIGNED DEFAULT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, unit_id BIGINT UNSIGNED DEFAULT NULL, worker_assignment_id BIGINT UNSIGNED DEFAULT NULL, created_by BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, priority VARCHAR(12) NOT NULL, status VARCHAR(16) NOT NULL, reported_at DATE NOT NULL, due_date DATE DEFAULT NULL, completed_at DATE DEFAULT NULL, UNIQUE INDEX UNIQ_MAINTENANCE_UUID (uuid), INDEX idx_maintenance_city_status (city_id, status), INDEX idx_maintenance_org_created (organization_id, created_at), INDEX IDX_MAINTENANCE_PARCEL (parcel_id), INDEX IDX_MAINTENANCE_BUILDING (building_id), INDEX IDX_MAINTENANCE_UNIT (unit_id), INDEX IDX_MAINTENANCE_ASSIGNMENT (worker_assignment_id), INDEX IDX_MAINTENANCE_USER (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_CITY FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_PARCEL FOREIGN KEY (parcel_id) REFERENCES parcel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_BUILDING FOREIGN KEY (building_id) REFERENCES building (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_UNIT FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_ASSIGNMENT FOREIGN KEY (worker_assignment_id) REFERENCES worker_assignment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE maintenance_work_order ADD CONSTRAINT FK_MAINTENANCE_USER FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance_work_order');
    }
}
