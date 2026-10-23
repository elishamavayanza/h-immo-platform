<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023180000CustomerInquiryNotifications extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les alertes personnelles persistantes des ADMIN_VILLE affectés aux demandes de leur ville.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customer_inquiry_notification (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL,
            created_at DATETIME NOT NULL,
            inquiry_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            read_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_AA42732DD17F50A6 (uuid),
            UNIQUE INDEX uniq_inquiry_notification_recipient (inquiry_id, user_id),
            INDEX idx_inquiry_notification_inbox (user_id, read_at, created_at),
            PRIMARY KEY(id),
            CONSTRAINT FK_INQUIRY_NOTIFICATION_INQUIRY FOREIGN KEY (inquiry_id) REFERENCES customer_inquiry (id) ON DELETE CASCADE,
            CONSTRAINT FK_INQUIRY_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_inquiry_notification');
    }
}
