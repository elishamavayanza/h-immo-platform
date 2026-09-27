<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927101823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gestion du personnel et des dépenses : worker, worker_assignment, expense.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category VARCHAR(255) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, expense_date DATE NOT NULL, period_start DATE DEFAULT NULL, period_end DATE DEFAULT NULL, method VARCHAR(255) DEFAULT NULL, supplier VARCHAR(200) DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, receipt_number VARCHAR(50) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, parcel_id BIGINT UNSIGNED DEFAULT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, unit_id BIGINT UNSIGNED DEFAULT NULL, worker_id BIGINT UNSIGNED DEFAULT NULL, created_by BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_2D3A8DA6D17F50A6 (uuid), INDEX idx_expense_city_date (city_id, expense_date), INDEX idx_expense_category (category), INDEX idx_expense_worker (worker_id), INDEX IDX_2D3A8DA632C8A3DE (organization_id), INDEX IDX_2D3A8DA68BAC62AF (city_id), INDEX IDX_2D3A8DA6465E670C (parcel_id), INDEX IDX_2D3A8DA64D2A7E12 (building_id), INDEX IDX_2D3A8DA6F8BD700D (unit_id), INDEX IDX_2D3A8DA6DE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, full_name VARCHAR(200) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(180) DEFAULT NULL, national_id VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_9FB2BF62D17F50A6 (uuid), UNIQUE INDEX uniq_worker_org_national_id (organization_id, national_id), INDEX IDX_9FB2BF6232C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker_assignment (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, role VARCHAR(255) NOT NULL, monthly_salary NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, worker_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, parcel_id BIGINT UNSIGNED DEFAULT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, unit_id BIGINT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_B4F905C9D17F50A6 (uuid), INDEX idx_assignment_worker (worker_id), INDEX idx_assignment_city (city_id), INDEX IDX_B4F905C9465E670C (parcel_id), INDEX IDX_B4F905C94D2A7E12 (building_id), INDEX IDX_B4F905C9F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA68BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6465E670C FOREIGN KEY (parcel_id) REFERENCES parcel (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA64D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA66B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE worker ADD CONSTRAINT FK_9FB2BF6232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE worker_assignment ADD CONSTRAINT FK_B4F905C96B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id)');
        $this->addSql('ALTER TABLE worker_assignment ADD CONSTRAINT FK_B4F905C98BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE worker_assignment ADD CONSTRAINT FK_B4F905C9465E670C FOREIGN KEY (parcel_id) REFERENCES parcel (id)');
        $this->addSql('ALTER TABLE worker_assignment ADD CONSTRAINT FK_B4F905C94D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE worker_assignment ADD CONSTRAINT FK_B4F905C9F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA632C8A3DE');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA68BAC62AF');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6465E670C');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA64D2A7E12');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6F8BD700D');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA66B20BA36');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6DE12AB56');
        $this->addSql('ALTER TABLE worker DROP FOREIGN KEY FK_9FB2BF6232C8A3DE');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C96B20BA36');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C98BAC62AF');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C9465E670C');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C94D2A7E12');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C9F8BD700D');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE worker');
        $this->addSql('DROP TABLE worker_assignment');
    }
}
