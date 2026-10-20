<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020100000RentalRuleModel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare les versions de règles locatives, les surcharges de bail et les instantanés traçables des échéances.';
    }

    public function up(Schema $schema): void
    {
        // Toute anomalie est détectée avant le premier DDL MariaDB, qui effectue des validations implicites.
        $invalidRents = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM rent r LEFT JOIN lease l ON l.id = r.lease_id WHERE l.id IS NULL OR l.organization_id IS NULL'
        );
        if ($invalidRents > 0) {
            throw new \RuntimeException(sprintf(
                'Migration annulée : %d échéance(s) ne peuvent pas être rattachées sans ambiguïté à l’organisation de leur bail.',
                $invalidRents,
            ));
        }

        $this->addSql("ALTER TABLE lease
            ADD due_mode_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD due_mode_override_value VARCHAR(16) DEFAULT NULL,
            ADD due_day_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD due_day_override_value SMALLINT UNSIGNED DEFAULT NULL,
            ADD first_partial_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD first_partial_override_value VARCHAR(32) DEFAULT NULL,
            ADD last_partial_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD last_partial_override_value VARCHAR(32) DEFAULT NULL,
            ADD proration_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD proration_override_value VARCHAR(32) DEFAULT NULL,
            ADD rounding_override_mode VARCHAR(12) NOT NULL DEFAULT 'inherit',
            ADD rounding_override_value VARCHAR(32) DEFAULT NULL,
            ADD UNIQUE INDEX uniq_lease_id_org (id, organization_id)");

        $this->addSql("ALTER TABLE lease
            ADD CONSTRAINT chk_lease_due_mode_override CHECK ((due_mode_override_mode = 'inherit' AND due_mode_override_value IS NULL) OR (due_mode_override_mode = 'override' AND due_mode_override_value IN ('in_advance', 'in_arrears'))),
            ADD CONSTRAINT chk_lease_due_day_override CHECK ((due_day_override_mode = 'inherit' AND due_day_override_value IS NULL) OR (due_day_override_mode = 'override' AND due_day_override_value BETWEEN 1 AND 31)),
            ADD CONSTRAINT chk_lease_first_partial_override CHECK ((first_partial_override_mode = 'inherit' AND first_partial_override_value IS NULL) OR (first_partial_override_mode = 'override' AND first_partial_override_value IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end'))),
            ADD CONSTRAINT chk_lease_last_partial_override CHECK ((last_partial_override_mode = 'inherit' AND last_partial_override_value IS NULL) OR (last_partial_override_mode = 'override' AND last_partial_override_value IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end'))),
            ADD CONSTRAINT chk_lease_proration_override CHECK ((proration_override_mode = 'inherit' AND proration_override_value IS NULL) OR (proration_override_mode = 'override' AND proration_override_value = 'actual_calendar_days')),
            ADD CONSTRAINT chk_lease_rounding_override CHECK ((rounding_override_mode = 'inherit' AND rounding_override_value IS NULL) OR (rounding_override_mode = 'override' AND rounding_override_value = 'usd_half_up_cent'))");

        $this->addSql("CREATE TABLE organization_rental_rule_version (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            effective_from DATE NOT NULL,
            due_mode VARCHAR(16) NOT NULL DEFAULT 'in_advance',
            due_day SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            first_partial_policy VARCHAR(32) NOT NULL DEFAULT 'clamp_regular_due_day',
            last_partial_policy VARCHAR(32) NOT NULL DEFAULT 'clamp_regular_due_day',
            proration_method VARCHAR(32) NOT NULL DEFAULT 'actual_calendar_days',
            rounding_rule VARCHAR(32) NOT NULL DEFAULT 'usd_half_up_cent',
            published_by BIGINT UNSIGNED NOT NULL,
            publication_reason VARCHAR(500) NOT NULL,
            UNIQUE INDEX UNIQ_RENTAL_RULE_UUID (uuid),
            UNIQUE INDEX uniq_rental_rule_org_version (organization_id, version_number),
            UNIQUE INDEX uniq_rental_rule_org_effective (organization_id, effective_from),
            UNIQUE INDEX uniq_rental_rule_id_org (id, organization_id),
            INDEX idx_rental_rule_org_effective (organization_id, effective_from),
            INDEX IDX_RENTAL_RULE_PUBLISHER (published_by),
            PRIMARY KEY(id),
            CONSTRAINT chk_rental_rule_version_positive CHECK (version_number > 0),
            CONSTRAINT chk_rental_rule_due_mode CHECK (due_mode IN ('in_advance', 'in_arrears')),
            CONSTRAINT chk_rental_rule_due_day CHECK (due_day BETWEEN 1 AND 31),
            CONSTRAINT chk_rental_rule_first_partial CHECK (first_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end')),
            CONSTRAINT chk_rental_rule_last_partial CHECK (last_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end')),
            CONSTRAINT chk_rental_rule_proration CHECK (proration_method = 'actual_calendar_days'),
            CONSTRAINT chk_rental_rule_rounding CHECK (rounding_rule = 'usd_half_up_cent'),
            CONSTRAINT chk_rental_rule_effective_month CHECK (DAYOFMONTH(effective_from) = 1)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE organization_rental_rule_version ADD CONSTRAINT FK_RENTAL_RULE_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE organization_rental_rule_version ADD CONSTRAINT FK_RENTAL_RULE_PUBLISHER FOREIGN KEY (published_by) REFERENCES user (id) ON DELETE RESTRICT');
        $this->addSql("CREATE TRIGGER trg_rental_rule_immutable_update BEFORE UPDATE ON organization_rental_rule_version FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une version de règles publiée est immuable'");
        $this->addSql("CREATE TRIGGER trg_rental_rule_immutable_delete BEFORE DELETE ON organization_rental_rule_version FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une version de règles publiée ne peut pas être supprimée'");

        // Les anciennes échéances restent sans version/snapshot; seule leur organisation est recopiée depuis le bail source.
        $this->addSql('ALTER TABLE rent ADD organization_id BIGINT UNSIGNED DEFAULT NULL, ADD rule_version_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('UPDATE rent r INNER JOIN lease l ON l.id = r.lease_id SET r.organization_id = l.organization_id');
        $this->addSql('ALTER TABLE rent MODIFY organization_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE rent ADD UNIQUE INDEX uniq_rent_id_org (id, organization_id), ADD UNIQUE INDEX uniq_rent_id_org_rule_version (id, organization_id, rule_version_id), ADD INDEX idx_rent_organization (organization_id), ADD INDEX IDX_RENT_RULE_VERSION (rule_version_id), ADD INDEX idx_rent_lease_org (lease_id, organization_id)');
        // Les FK simples correspondent aux associations Doctrine; les FK composites ajoutent la garantie tenant que Doctrine ne mappe pas nativement ici.
        $this->addSql('ALTER TABLE rent ADD CONSTRAINT FK_RENT_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_LEASE_ORG FOREIGN KEY (lease_id, organization_id) REFERENCES lease (id, organization_id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_VERSION FOREIGN KEY (rule_version_id) REFERENCES organization_rental_rule_version (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_VERSION_ORG FOREIGN KEY (rule_version_id, organization_id) REFERENCES organization_rental_rule_version (id, organization_id) ON DELETE RESTRICT');

        $this->addSql("CREATE TABLE rent_rule_snapshot (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            rent_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            rule_version_id BIGINT UNSIGNED DEFAULT NULL,
            calculator_version VARCHAR(40) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            due_mode_source VARCHAR(24) NOT NULL,
            due_mode VARCHAR(16) NOT NULL,
            due_day_source VARCHAR(24) NOT NULL,
            due_day SMALLINT UNSIGNED NOT NULL,
            first_partial_source VARCHAR(24) NOT NULL,
            first_partial_policy VARCHAR(32) NOT NULL,
            last_partial_source VARCHAR(24) NOT NULL,
            last_partial_policy VARCHAR(32) NOT NULL,
            proration_source VARCHAR(24) NOT NULL,
            proration_method VARCHAR(32) NOT NULL,
            rounding_source VARCHAR(24) NOT NULL,
            rounding_rule VARCHAR(32) NOT NULL,
            base_monthly_rent NUMERIC(12, 2) NOT NULL,
            covered_start DATE NOT NULL,
            covered_end DATE NOT NULL,
            covered_days SMALLINT UNSIGNED NOT NULL,
            days_in_month SMALLINT UNSIGNED NOT NULL,
            UNIQUE INDEX UNIQ_RENT_RULE_SNAPSHOT_UUID (uuid),
            UNIQUE INDEX uniq_rent_rule_snapshot_rent (rent_id),
            INDEX idx_rent_rule_snapshot_org (organization_id),
            INDEX IDX_RENT_RULE_SNAPSHOT_VERSION (rule_version_id),
            PRIMARY KEY(id),
            CONSTRAINT chk_rent_snapshot_currency CHECK (currency IN ('USD', 'CDF')),
            CONSTRAINT chk_rent_snapshot_due_mode CHECK (due_mode IN ('in_advance', 'in_arrears')),
            CONSTRAINT chk_rent_snapshot_due_day CHECK (due_day BETWEEN 1 AND 31),
            CONSTRAINT chk_rent_snapshot_sources CHECK (due_mode_source IN ('lease_override', 'organization_version', 'soft_immo_default') AND due_day_source IN ('lease_override', 'organization_version', 'soft_immo_default') AND first_partial_source IN ('lease_override', 'organization_version', 'soft_immo_default') AND last_partial_source IN ('lease_override', 'organization_version', 'soft_immo_default') AND proration_source IN ('lease_override', 'organization_version', 'soft_immo_default') AND rounding_source IN ('lease_override', 'organization_version', 'soft_immo_default')),
            CONSTRAINT chk_rent_snapshot_partial_policy CHECK (first_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end') AND last_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end')),
            CONSTRAINT chk_rent_snapshot_proration CHECK (proration_method = 'actual_calendar_days'),
            CONSTRAINT chk_rent_snapshot_rounding CHECK (rounding_rule = 'usd_half_up_cent'),
            CONSTRAINT chk_rent_snapshot_money CHECK (base_monthly_rent >= 0),
            CONSTRAINT chk_rent_snapshot_coverage CHECK (covered_start <= covered_end AND covered_days BETWEEN 1 AND 31 AND days_in_month BETWEEN 28 AND 31 AND covered_days <= days_in_month),
            CONSTRAINT chk_rent_snapshot_version_source CHECK (((due_mode_source = 'organization_version' OR due_day_source = 'organization_version' OR first_partial_source = 'organization_version' OR last_partial_source = 'organization_version' OR proration_source = 'organization_version' OR rounding_source = 'organization_version') AND rule_version_id IS NOT NULL) OR ((due_mode_source <> 'organization_version' AND due_day_source <> 'organization_version' AND first_partial_source <> 'organization_version' AND last_partial_source <> 'organization_version' AND proration_source <> 'organization_version' AND rounding_source <> 'organization_version') AND rule_version_id IS NULL))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE rent_rule_snapshot ADD CONSTRAINT FK_RENT_RULE_SNAPSHOT_RENT_ID FOREIGN KEY (rent_id) REFERENCES rent (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_SNAPSHOT_RENT FOREIGN KEY (rent_id, organization_id) REFERENCES rent (id, organization_id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_SNAPSHOT_VERSION FOREIGN KEY (rule_version_id) REFERENCES organization_rental_rule_version (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_SNAPSHOT_RENT_VERSION FOREIGN KEY (rent_id, organization_id, rule_version_id) REFERENCES rent (id, organization_id, rule_version_id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RENT_RULE_SNAPSHOT_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rent_rule_snapshot');
        $this->addSql('ALTER TABLE rent DROP FOREIGN KEY FK_RENT_RULE_VERSION_ORG, DROP FOREIGN KEY FK_RENT_RULE_VERSION, DROP FOREIGN KEY FK_RENT_LEASE_ORG, DROP FOREIGN KEY FK_RENT_ORG');
        $this->addSql('ALTER TABLE rent DROP INDEX uniq_rent_id_org_rule_version, DROP INDEX uniq_rent_id_org, DROP INDEX idx_rent_organization, DROP INDEX IDX_RENT_RULE_VERSION, DROP INDEX idx_rent_lease_org');
        $this->addSql('ALTER TABLE rent DROP rule_version_id, DROP organization_id');
        $this->addSql('DROP TRIGGER trg_rental_rule_immutable_update');
        $this->addSql('DROP TRIGGER trg_rental_rule_immutable_delete');
        $this->addSql('DROP TABLE organization_rental_rule_version');
        $this->addSql('ALTER TABLE lease DROP CHECK chk_lease_due_mode_override, DROP CHECK chk_lease_due_day_override, DROP CHECK chk_lease_first_partial_override, DROP CHECK chk_lease_last_partial_override, DROP CHECK chk_lease_proration_override, DROP CHECK chk_lease_rounding_override');
        $this->addSql('ALTER TABLE lease DROP INDEX uniq_lease_id_org');
        $this->addSql('ALTER TABLE lease DROP due_mode_override_mode, DROP due_mode_override_value, DROP due_day_override_mode, DROP due_day_override_value, DROP first_partial_override_mode, DROP first_partial_override_value, DROP last_partial_override_mode, DROP last_partial_override_value, DROP proration_override_mode, DROP proration_override_value, DROP rounding_override_mode, DROP rounding_override_value');
    }
}
