<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Crée le registre des pièces privées attachées aux ressources métier. */
final class Version20261015130000PrivateDocuments extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les métadonnées isolées par organisation/ville pour les documents stockés hors du répertoire public.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE private_document (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, organization_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED DEFAULT NULL, uploaded_by BIGINT UNSIGNED NOT NULL, category VARCHAR(32) NOT NULL, related_type VARCHAR(24) NOT NULL, related_uuid VARCHAR(36) NOT NULL, original_name VARCHAR(180) NOT NULL, storage_name VARCHAR(80) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, description VARCHAR(500) DEFAULT NULL, UNIQUE INDEX UNIQ_7FFF55DBD17F50A6 (uuid), UNIQUE INDEX UNIQ_7FFF55DB570EB513 (storage_name), INDEX idx_private_document_org_created (organization_id, created_at), INDEX idx_private_document_city_created (city_id, created_at), INDEX idx_private_document_related (related_type, related_uuid), INDEX IDX_7FFF55DBE3E73126 (uploaded_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE private_document ADD CONSTRAINT FK_PRIVATE_DOCUMENT_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE private_document ADD CONSTRAINT FK_PRIVATE_DOCUMENT_CITY FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE private_document ADD CONSTRAINT FK_PRIVATE_DOCUMENT_UPLOADER FOREIGN KEY (uploaded_by) REFERENCES user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE private_document');
    }
}
