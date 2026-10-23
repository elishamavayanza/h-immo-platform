<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023100000PropertyListingWorkflow extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les annonces de vitrine avec transitions éditoriales et historique immuable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE property_listing (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            unit_id BIGINT UNSIGNED NOT NULL,
            created_by_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL,
            title VARCHAR(180) NOT NULL,
            description LONGTEXT NOT NULL,
            offer_type VARCHAR(16) NOT NULL,
            price NUMERIC(12, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            published_at DATETIME DEFAULT NULL,
            public_snapshot JSON DEFAULT NULL,
            version INT NOT NULL DEFAULT 1,
            UNIQUE INDEX UNIQ_PROPERTY_LISTING_UUID (uuid),
            UNIQUE INDEX UNIQ_PROPERTY_LISTING_UNIT (unit_id),
            INDEX IDX_PROPERTY_LISTING_STATUS_UPDATED (status, updated_at),
            INDEX IDX_PROPERTY_LISTING_CREATOR (created_by_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_PROPERTY_LISTING_UNIT FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE RESTRICT,
            CONSTRAINT FK_PROPERTY_LISTING_CREATOR FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE RESTRICT,
            CONSTRAINT CHK_PROPERTY_LISTING_STATUS CHECK (status IN ('draft', 'pending_review', 'corrections_requested', 'pending_publication', 'published', 'rejected', 'withdrawn')),
            CONSTRAINT CHK_PROPERTY_LISTING_OFFER CHECK (offer_type IN ('rent', 'sale')),
            CONSTRAINT CHK_PROPERTY_LISTING_PRICE CHECK (price > 0),
            CONSTRAINT CHK_PROPERTY_LISTING_CURRENCY CHECK (currency IN ('USD', 'CDF')),
            CONSTRAINT CHK_PROPERTY_LISTING_PUBLISHED_SNAPSHOT CHECK ((status = 'published' AND published_at IS NOT NULL AND public_snapshot IS NOT NULL) OR (status <> 'published'))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE property_listing_history (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            listing_id BIGINT UNSIGNED NOT NULL,
            actor_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(32) NOT NULL,
            old_status VARCHAR(32) DEFAULT NULL,
            new_status VARCHAR(32) NOT NULL,
            reason LONGTEXT DEFAULT NULL,
            old_values JSON DEFAULT NULL,
            new_values JSON DEFAULT NULL,
            UNIQUE INDEX UNIQ_PROPERTY_LISTING_HISTORY_UUID (uuid),
            INDEX IDX_LISTING_HISTORY_LISTING_DATE (listing_id, created_at),
            INDEX IDX_LISTING_HISTORY_ACTOR (actor_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_LISTING_HISTORY_LISTING FOREIGN KEY (listing_id) REFERENCES property_listing (id) ON DELETE RESTRICT,
            CONSTRAINT FK_LISTING_HISTORY_ACTOR FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE RESTRICT
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE property_listing_history');
        $this->addSql('DROP TABLE property_listing');
    }
}
