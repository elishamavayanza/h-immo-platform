<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023160000CustomerInquiries extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les demandes publiques d’annonces, leur suivi et leur historique append-only.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customer_inquiry (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            listing_id BIGINT UNSIGNED NOT NULL,
            converted_lease_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(16) NOT NULL,
            name VARCHAR(120) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(180) DEFAULT NULL,
            message LONGTEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            assigned_to_id BIGINT UNSIGNED DEFAULT NULL,
            consented_at DATETIME NOT NULL,
            closed_reason LONGTEXT DEFAULT NULL,
            idempotency_key_hash CHAR(64) NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            UNIQUE INDEX UNIQ_INQUIRY_UUID (uuid),
            UNIQUE INDEX UNIQ_INQUIRY_ORG_IDEMPOTENCY (organization_id, idempotency_key_hash),
            UNIQUE INDEX UNIQ_INQUIRY_CONVERTED_LEASE (converted_lease_id),
            INDEX IDX_INQUIRY_ORG_STATUS_CREATED (organization_id, status, created_at),
            INDEX IDX_INQUIRY_LISTING (listing_id, created_at),
            INDEX IDX_INQUIRY_ASSIGNEE (assigned_to_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_INQUIRY_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT,
            CONSTRAINT FK_INQUIRY_LISTING FOREIGN KEY (listing_id) REFERENCES property_listing (id) ON DELETE RESTRICT,
            CONSTRAINT FK_INQUIRY_CONVERTED_LEASE FOREIGN KEY (converted_lease_id) REFERENCES lease (id) ON DELETE RESTRICT,
            CONSTRAINT FK_INQUIRY_ASSIGNEE FOREIGN KEY (assigned_to_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT CHK_INQUIRY_TYPE CHECK (type IN ('information', 'visit')),
            CONSTRAINT CHK_INQUIRY_STATUS CHECK (status IN ('new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed')),
            CONSTRAINT CHK_INQUIRY_CLOSED_REASON CHECK ((status = 'closed' AND closed_reason IS NOT NULL) OR (status <> 'closed' AND closed_reason IS NULL))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE customer_inquiry_history (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            inquiry_id BIGINT UNSIGNED NOT NULL,
            actor_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(32) NOT NULL,
            old_status VARCHAR(24) DEFAULT NULL,
            new_status VARCHAR(24) NOT NULL,
            reason LONGTEXT DEFAULT NULL,
            UNIQUE INDEX UNIQ_INQUIRY_HISTORY_UUID (uuid),
            INDEX IDX_INQUIRY_HISTORY_DATE (inquiry_id, created_at),
            INDEX IDX_INQUIRY_HISTORY_ORGANIZATION (organization_id),
            INDEX IDX_INQUIRY_HISTORY_ACTOR (actor_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_INQUIRY_HISTORY_INQUIRY FOREIGN KEY (inquiry_id) REFERENCES customer_inquiry (id) ON DELETE RESTRICT,
            CONSTRAINT FK_INQUIRY_HISTORY_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT,
            CONSTRAINT FK_INQUIRY_HISTORY_ACTOR FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT CHK_INQUIRY_HISTORY_OLD_STATUS CHECK (old_status IS NULL OR old_status IN ('new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed')),
            CONSTRAINT CHK_INQUIRY_HISTORY_NEW_STATUS CHECK (new_status IN ('new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed'))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE customer_visit (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            inquiry_id BIGINT UNSIGNED NOT NULL,
            created_by_id BIGINT UNSIGNED NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status VARCHAR(16) NOT NULL,
            outcome LONGTEXT DEFAULT NULL,
            UNIQUE INDEX UNIQ_CUSTOMER_VISIT_UUID (uuid),
            INDEX IDX_CUSTOMER_VISIT_ORG_STATUS_DATE (organization_id, status, scheduled_at),
            INDEX IDX_CUSTOMER_VISIT_INQUIRY_DATE (inquiry_id, scheduled_at),
            INDEX IDX_CUSTOMER_VISIT_CREATED_BY (created_by_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CUSTOMER_VISIT_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT,
            CONSTRAINT FK_CUSTOMER_VISIT_INQUIRY FOREIGN KEY (inquiry_id) REFERENCES customer_inquiry (id) ON DELETE RESTRICT,
            CONSTRAINT FK_CUSTOMER_VISIT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT CHK_CUSTOMER_VISIT_STATUS CHECK (status IN ('proposed', 'confirmed', 'rescheduled', 'completed', 'cancelled'))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_visit');
        $this->addSql('DROP TABLE customer_inquiry_history');
        $this->addSql('DROP TABLE customer_inquiry');
    }
}
