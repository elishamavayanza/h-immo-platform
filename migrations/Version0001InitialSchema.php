<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial complet, généré à partir du mapping Doctrine.
 *
 * Cette migration remplace les trois migrations supprimées lors du
 * squash : à elle seule, elle recrée les 17 tables métier. Elle est
 * volontairement antérieure à Version0002RevokedToken, qui ajoute
 * `revoked_token`, pour que Doctrine les rejoue dans le bon ordre sur
 * une base vierge.
 *
 * Numérotation séquentielle (0001, 0002, …) et non horodatée : l'ordre
 * d'application est ainsi lisible dans le nom du fichier, sans dépendre
 * d'un tri alphabétique sur un timestamp.
 */
final class Version0001InitialSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial complet (17 tables métier), régénéré depuis le mapping Doctrine pour MariaDB.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, action VARCHAR(60) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id BIGINT NOT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, organization_id BIGINT UNSIGNED DEFAULT NULL, user_id BIGINT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_F6E1C0F5D17F50A6 (uuid), INDEX IDX_F6E1C0F532C8A3DE (organization_id), INDEX IDX_F6E1C0F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE building (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, reference VARCHAR(50) NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(255) NOT NULL, number_of_floors SMALLINT DEFAULT NULL, description LONGTEXT DEFAULT NULL, parcel_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_E16F61D4D17F50A6 (uuid), UNIQUE INDEX uniq_building_parcel_reference (parcel_id, reference), INDEX IDX_E16F61D4465E670C (parcel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE city (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(30) NOT NULL, province VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, status VARCHAR(255) NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_2D5B0234D17F50A6 (uuid), UNIQUE INDEX uniq_city_org_code (organization_id, code), INDEX IDX_2D5B023432C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE expense (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category VARCHAR(255) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, expense_date DATE NOT NULL, period_start DATE DEFAULT NULL, period_end DATE DEFAULT NULL, method VARCHAR(255) DEFAULT NULL, supplier VARCHAR(200) DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, receipt_number VARCHAR(50) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, parcel_id BIGINT UNSIGNED DEFAULT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, unit_id BIGINT UNSIGNED DEFAULT NULL, worker_id BIGINT UNSIGNED DEFAULT NULL, created_by BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_2D3A8DA6D17F50A6 (uuid), INDEX idx_expense_city_date (city_id, expense_date), INDEX idx_expense_category (category), INDEX idx_expense_worker (worker_id), INDEX IDX_2D3A8DA632C8A3DE (organization_id), INDEX IDX_2D3A8DA68BAC62AF (city_id), INDEX IDX_2D3A8DA6465E670C (parcel_id), INDEX IDX_2D3A8DA64D2A7E12 (building_id), INDEX IDX_2D3A8DA6F8BD700D (unit_id), INDEX IDX_2D3A8DA6DE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lease (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, reference VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, monthly_rent NUMERIC(12, 2) NOT NULL, deposit_amount NUMERIC(12, 2) DEFAULT NULL, currency VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, termination_date DATE DEFAULT NULL, termination_reason VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_E6C77495D17F50A6 (uuid), UNIQUE INDEX uniq_lease_org_reference (organization_id, reference), INDEX IDX_E6C7749532C8A3DE (organization_id), INDEX IDX_E6C774959033212A (tenant_id), INDEX IDX_E6C77495F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, name VARCHAR(150) NOT NULL, code VARCHAR(30) NOT NULL, logo VARCHAR(255) DEFAULT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) NOT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, status VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_C1EE637CD17F50A6 (uuid), UNIQUE INDEX uniq_organization_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_user (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, role VARCHAR(255) NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_B49AE8D4D17F50A6 (uuid), UNIQUE INDEX uniq_org_user (organization_id, user_id), INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE parcel (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, reference VARCHAR(50) NOT NULL, title_number VARCHAR(100) DEFAULT NULL, name VARCHAR(150) NOT NULL, address VARCHAR(255) NOT NULL, quarter VARCHAR(100) DEFAULT NULL, area NUMERIC(10, 2) NOT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, description LONGTEXT DEFAULT NULL, city_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_C99B5D60D17F50A6 (uuid), UNIQUE INDEX uniq_parcel_city_reference (city_id, reference), INDEX IDX_C99B5D608BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE password_reset_token (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_6B7BA4B6D17F50A6 (uuid), UNIQUE INDEX UNIQ_6B7BA4B6B3BC57DA (token_hash), INDEX idx_prt_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, payment_date DATE NOT NULL, method VARCHAR(255) NOT NULL, reference VARCHAR(100) DEFAULT NULL, receipt_number VARCHAR(50) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, rent_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_6D28840DD17F50A6 (uuid), INDEX IDX_6D28840DE5FD6250 (rent_id), INDEX IDX_6D28840DDE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rent (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, period DATE NOT NULL, due_date DATE NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, lease_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_2784DCCD17F50A6 (uuid), UNIQUE INDEX uniq_rent_lease_period (lease_id, period), INDEX IDX_2784DCCD3CA542C (lease_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tenant (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, type VARCHAR(255) NOT NULL, full_name VARCHAR(200) DEFAULT NULL, company_name VARCHAR(150) DEFAULT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(180) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_4E59C462D17F50A6 (uuid), INDEX IDX_4E59C46232C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE unit (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, reference VARCHAR(50) NOT NULL, type VARCHAR(255) NOT NULL, floor SMALLINT NOT NULL, surface NUMERIC(8, 2) NOT NULL, bedrooms SMALLINT DEFAULT NULL, rooms SMALLINT DEFAULT NULL, bathrooms SMALLINT DEFAULT NULL, monthly_rent NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, building_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_DCBB0C53D17F50A6 (uuid), UNIQUE INDEX uniq_unit_building_reference (building_id, reference), INDEX IDX_DCBB0C534D2A7E12 (building_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, full_name VARCHAR(200) NOT NULL, phone VARCHAR(30) DEFAULT NULL, profile_photo VARCHAR(255) DEFAULT NULL, platform_role VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649D17F50A6 (uuid), UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_city (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, user_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_57DA4EFDD17F50A6 (uuid), UNIQUE INDEX uniq_user_city (user_id, city_id), INDEX IDX_57DA4EFDA76ED395 (user_id), INDEX IDX_57DA4EFD8BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, full_name VARCHAR(200) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(180) DEFAULT NULL, national_id VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_9FB2BF62D17F50A6 (uuid), UNIQUE INDEX uniq_worker_org_national_id (organization_id, national_id), INDEX IDX_9FB2BF6232C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE worker_assignment (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, role VARCHAR(255) NOT NULL, monthly_salary NUMERIC(12, 2) NOT NULL, currency VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, worker_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL, parcel_id BIGINT UNSIGNED DEFAULT NULL, building_id BIGINT UNSIGNED DEFAULT NULL, unit_id BIGINT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_B4F905C9D17F50A6 (uuid), INDEX idx_assignment_worker (worker_id), INDEX idx_assignment_city (city_id), INDEX IDX_B4F905C9465E670C (parcel_id), INDEX IDX_B4F905C94D2A7E12 (building_id), INDEX IDX_B4F905C9F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE building ADD CONSTRAINT FK_E16F61D4465E670C FOREIGN KEY (parcel_id) REFERENCES parcel (id)');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B023432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA68BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6465E670C FOREIGN KEY (parcel_id) REFERENCES parcel (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA64D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA66B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lease ADD CONSTRAINT FK_E6C7749532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE lease ADD CONSTRAINT FK_E6C774959033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE lease ADD CONSTRAINT FK_E6C77495F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE parcel ADD CONSTRAINT FK_C99B5D608BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DE5FD6250 FOREIGN KEY (rent_id) REFERENCES rent (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rent ADD CONSTRAINT FK_2784DCCD3CA542C FOREIGN KEY (lease_id) REFERENCES lease (id)');
        $this->addSql('ALTER TABLE tenant ADD CONSTRAINT FK_4E59C46232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C534D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE user_city ADD CONSTRAINT FK_57DA4EFDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_city ADD CONSTRAINT FK_57DA4EFD8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
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
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F532C8A3DE');
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE building DROP FOREIGN KEY FK_E16F61D4465E670C');
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B023432C8A3DE');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA632C8A3DE');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA68BAC62AF');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6465E670C');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA64D2A7E12');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6F8BD700D');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA66B20BA36');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6DE12AB56');
        $this->addSql('ALTER TABLE lease DROP FOREIGN KEY FK_E6C7749532C8A3DE');
        $this->addSql('ALTER TABLE lease DROP FOREIGN KEY FK_E6C774959033212A');
        $this->addSql('ALTER TABLE lease DROP FOREIGN KEY FK_E6C77495F8BD700D');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D4A76ED395');
        $this->addSql('ALTER TABLE parcel DROP FOREIGN KEY FK_C99B5D608BAC62AF');
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DE5FD6250');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DDE12AB56');
        $this->addSql('ALTER TABLE rent DROP FOREIGN KEY FK_2784DCCD3CA542C');
        $this->addSql('ALTER TABLE tenant DROP FOREIGN KEY FK_4E59C46232C8A3DE');
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY FK_DCBB0C534D2A7E12');
        $this->addSql('ALTER TABLE user_city DROP FOREIGN KEY FK_57DA4EFDA76ED395');
        $this->addSql('ALTER TABLE user_city DROP FOREIGN KEY FK_57DA4EFD8BAC62AF');
        $this->addSql('ALTER TABLE worker DROP FOREIGN KEY FK_9FB2BF6232C8A3DE');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C96B20BA36');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C98BAC62AF');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C9465E670C');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C94D2A7E12');
        $this->addSql('ALTER TABLE worker_assignment DROP FOREIGN KEY FK_B4F905C9F8BD700D');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE building');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE lease');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('DROP TABLE parcel');
        $this->addSql('DROP TABLE password_reset_token');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE rent');
        $this->addSql('DROP TABLE tenant');
        $this->addSql('DROP TABLE unit');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_city');
        $this->addSql('DROP TABLE worker');
        $this->addSql('DROP TABLE worker_assignment');
    }
}
