<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020110000RentalRuleGovernance extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les brouillons et délégations explicites pour gouverner la publication des règles locatives.'; }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['organization_rental_rule_draft'])) {
            $this->addSql("CREATE TABLE organization_rental_rule_draft (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL, created_by_id BIGINT UNSIGNED NOT NULL,
            published_version_id BIGINT UNSIGNED DEFAULT NULL, effective_from DATE NOT NULL,
            due_mode VARCHAR(16) NOT NULL DEFAULT 'in_advance', due_day SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            first_partial_policy VARCHAR(32) NOT NULL DEFAULT 'clamp_regular_due_day',
            last_partial_policy VARCHAR(32) NOT NULL DEFAULT 'clamp_regular_due_day',
            proration_method VARCHAR(32) NOT NULL DEFAULT 'actual_calendar_days',
            rounding_rule VARCHAR(32) NOT NULL DEFAULT 'usd_half_up_cent', change_reason VARCHAR(500) NOT NULL,
            UNIQUE INDEX UNIQ_RENTAL_RULE_DRAFT_UUID (uuid), INDEX idx_rental_rule_draft_org (organization_id, created_at),
            INDEX IDX_RENTAL_RULE_DRAFT_CREATOR (created_by_id), INDEX IDX_RENTAL_RULE_DRAFT_PUBLISHED (published_version_id), PRIMARY KEY(id),
            CONSTRAINT chk_rule_draft_due_day CHECK (due_day BETWEEN 1 AND 31),
            CONSTRAINT chk_rule_draft_effective_month CHECK (DAYOFMONTH(effective_from) = 1),
            CONSTRAINT chk_rule_draft_due_mode CHECK (due_mode IN ('in_advance', 'in_arrears')),
            CONSTRAINT chk_rule_draft_first_partial CHECK (first_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end')),
            CONSTRAINT chk_rule_draft_last_partial CHECK (last_partial_policy IN ('clamp_regular_due_day', 'coverage_start', 'coverage_end')),
            CONSTRAINT chk_rule_draft_proration CHECK (proration_method = 'actual_calendar_days'),
            CONSTRAINT chk_rule_draft_rounding CHECK (rounding_rule = 'usd_half_up_cent'),
            CONSTRAINT FK_RULE_DRAFT_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DRAFT_CREATOR FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DRAFT_VERSION FOREIGN KEY (published_version_id) REFERENCES organization_rental_rule_version (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DRAFT_VERSION_ORG FOREIGN KEY (published_version_id, organization_id) REFERENCES organization_rental_rule_version (id, organization_id) ON DELETE RESTRICT
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->triggerExists('trg_rule_draft_immutable_after_publish')) {
            $this->addSql("CREATE TRIGGER trg_rule_draft_immutable_after_publish BEFORE UPDATE ON organization_rental_rule_draft FOR EACH ROW BEGIN IF OLD.published_version_id IS NOT NULL OR (NEW.published_version_id IS NOT NULL AND (OLD.organization_id <> NEW.organization_id OR OLD.created_by_id <> NEW.created_by_id OR OLD.effective_from <> NEW.effective_from OR OLD.due_mode <> NEW.due_mode OR OLD.due_day <> NEW.due_day OR OLD.first_partial_policy <> NEW.first_partial_policy OR OLD.last_partial_policy <> NEW.last_partial_policy OR OLD.proration_method <> NEW.proration_method OR OLD.rounding_rule <> NEW.rounding_rule OR OLD.change_reason <> NEW.change_reason)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un brouillon publié est immuable'; END IF; END");
        }
        if (!$this->triggerExists('trg_rule_draft_no_delete')) {
            $this->addSql("CREATE TRIGGER trg_rule_draft_no_delete BEFORE DELETE ON organization_rental_rule_draft FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un brouillon de règles est conservé pour la traçabilité'");
        }

        if (!$this->connection->createSchemaManager()->tablesExist(['rental_rule_publication_delegation'])) {
            $roleCollation = $this->connection->fetchOne("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_user' AND COLUMN_NAME = 'role'");
            if (!is_string($roleCollation) || !preg_match('/^[a-zA-Z0-9_]+$/', $roleCollation)) {
                throw new \RuntimeException('Impossible de déterminer de façon sûre la collation de organization_user.role pour créer les clés étrangères de délégation.');
            }

            $this->addSql("CREATE TABLE rental_rule_publication_delegation (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
            recipient_role VARCHAR(255) CHARACTER SET utf8mb4 COLLATE {$roleCollation} NOT NULL DEFAULT 'admin_immobilier',
            granted_by_id BIGINT UNSIGNED NOT NULL,
            grantor_role VARCHAR(255) CHARACTER SET utf8mb4 COLLATE {$roleCollation} NOT NULL DEFAULT 'patron',
            permission VARCHAR(40) NOT NULL DEFAULT 'rental_rule_publish',
            expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, revoked_by_id BIGINT UNSIGNED DEFAULT NULL,
            revocation_reason VARCHAR(500) DEFAULT NULL,
            UNIQUE INDEX UNIQ_RULE_DELEGATION_UUID (uuid),
            INDEX idx_rule_delegation_org_user_active (organization_id, user_id, revoked_at, expires_at),
            INDEX IDX_RULE_DELEGATION_GRANTOR (granted_by_id), INDEX IDX_RULE_DELEGATION_REVOKER (revoked_by_id), PRIMARY KEY(id),
            CONSTRAINT chk_rule_delegation_permission CHECK (permission = 'rental_rule_publish'),
            CONSTRAINT chk_rule_delegation_not_self CHECK (user_id <> granted_by_id),
            CONSTRAINT chk_rule_delegation_recipient_role CHECK (recipient_role = 'admin_immobilier'),
            CONSTRAINT chk_rule_delegation_grantor_role CHECK (grantor_role = 'patron'),
            CONSTRAINT chk_rule_delegation_revocation CHECK ((revoked_at IS NULL AND revoked_by_id IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_id IS NOT NULL)),
            CONSTRAINT FK_RULE_DELEGATION_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DELEGATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DELEGATION_RECIPIENT_MEMBERSHIP FOREIGN KEY (organization_id, user_id, recipient_role) REFERENCES organization_user (organization_id, user_id, role) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DELEGATION_GRANTOR FOREIGN KEY (granted_by_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DELEGATION_GRANTOR_MEMBERSHIP FOREIGN KEY (organization_id, granted_by_id, grantor_role) REFERENCES organization_user (organization_id, user_id, role) ON DELETE RESTRICT,
            CONSTRAINT FK_RULE_DELEGATION_REVOKER FOREIGN KEY (revoked_by_id) REFERENCES user (id) ON DELETE RESTRICT
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->triggerExists('trg_rule_delegation_immutable_grant')) {
            $this->addSql("CREATE TRIGGER trg_rule_delegation_immutable_grant BEFORE UPDATE ON rental_rule_publication_delegation FOR EACH ROW BEGIN IF OLD.organization_id <> NEW.organization_id OR OLD.user_id <> NEW.user_id OR OLD.recipient_role <> NEW.recipient_role OR OLD.granted_by_id <> NEW.granted_by_id OR OLD.grantor_role <> NEW.grantor_role OR OLD.permission <> NEW.permission OR NOT (OLD.expires_at <=> NEW.expires_at) OR OLD.created_at <> NEW.created_at OR OLD.uuid <> NEW.uuid OR (OLD.revoked_at IS NOT NULL AND (NOT (OLD.revoked_at <=> NEW.revoked_at) OR NOT (OLD.revoked_by_id <=> NEW.revoked_by_id) OR NOT (OLD.revocation_reason <=> NEW.revocation_reason))) OR (OLD.revoked_at IS NULL AND NEW.revoked_at IS NULL AND (NEW.revoked_by_id IS NOT NULL OR NEW.revocation_reason IS NOT NULL)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une délégation accordée est immuable hors révocation'; END IF; END");
        }
        if (!$this->triggerExists('trg_rule_delegation_no_delete')) {
            $this->addSql("CREATE TRIGGER trg_rule_delegation_no_delete BEFORE DELETE ON rental_rule_publication_delegation FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une délégation est conservée pour la traçabilité'");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER trg_rule_delegation_no_delete');
        $this->addSql('DROP TRIGGER trg_rule_delegation_immutable_grant');
        $this->addSql('DROP TRIGGER trg_rule_draft_no_delete');
        $this->addSql('DROP TRIGGER trg_rule_draft_immutable_after_publish');
        $this->addSql('DROP TABLE rental_rule_publication_delegation');
        $this->addSql('DROP TABLE organization_rental_rule_draft');
    }

    private function triggerExists(string $name): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
            [$name],
        ) > 0;
    }
}
