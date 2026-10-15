<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015180000Publications extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les publications de plateforme et leur suivi individuel par destinataire.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE publication (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', organization_id BIGINT UNSIGNED DEFAULT NULL, created_by_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, content_blocks JSON NOT NULL, category VARCHAR(255) NOT NULL, audience VARCHAR(255) NOT NULL, audience_roles JSON NOT NULL, scheduled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', status VARCHAR(255) NOT NULL, images JSON NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_PUBLICATION_UUID (uuid), INDEX idx_publication_status_date (status, published_at), INDEX IDX_PUBLICATION_ORGANIZATION (organization_id), INDEX IDX_PUBLICATION_CREATOR (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB COLLATE `utf8mb4_unicode_ci`");
        $this->addSql("CREATE TABLE publication_recipient (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', publication_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', first_viewed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', withdrawn_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_publication_recipient (publication_id, user_id), INDEX idx_recipient_active (user_id, available_at, first_viewed_at), INDEX IDX_RECIPIENT_PUBLICATION (publication_id), INDEX IDX_RECIPIENT_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB COLLATE `utf8mb4_unicode_ci`");
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_PUBLICATION_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_PUBLICATION_CREATOR FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE publication_recipient ADD CONSTRAINT FK_RECIPIENT_PUBLICATION FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication_recipient ADD CONSTRAINT FK_RECIPIENT_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publication_recipient');
        $this->addSql('DROP TABLE publication');
    }
}
