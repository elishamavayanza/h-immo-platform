<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016114000BuildingLevels extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des niveaux nommés, ordonnés et décrits aux bâtiments, puis relie les unités existantes à leur niveau correspondant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE building_level (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reference VARCHAR(50) NOT NULL, label VARCHAR(120) NOT NULL, display_order INT NOT NULL, description LONGTEXT DEFAULT NULL, building_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_F64A7D89D17F50A6 (uuid), UNIQUE INDEX uniq_building_level_reference (building_id, reference), INDEX idx_building_level_order (building_id, display_order), INDEX IDX_F64A7D894D2A7E12 (building_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE building_level ADD CONSTRAINT FK_F64A7D894D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE unit ADD level_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_DCBB0C535FB14BA7 ON unit (level_id)');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C535FB14BA7 FOREIGN KEY (level_id) REFERENCES building_level (id)');
        $this->addSql("INSERT INTO building_level (uuid, created_at, updated_at, reference, label, display_order, building_id) SELECT UNHEX(REPLACE(UUID(), '-', '')), NOW(), NOW(), CONCAT('HIST-', u.floor, '-', LEFT(MD5(COALESCE(NULLIF(u.floor_label, ''), '')), 12)), COALESCE(NULLIF(u.floor_label, ''), CASE WHEN u.floor = 0 THEN 'Rez-de-chaussée' ELSE CONCAT('Niveau ', u.floor) END), u.floor, u.building_id FROM unit u WHERE u.deleted_at IS NULL GROUP BY u.building_id, u.floor, COALESCE(NULLIF(u.floor_label, ''), '')");
        $this->addSql("UPDATE unit u INNER JOIN building_level l ON l.building_id = u.building_id AND l.display_order = u.floor AND BINARY l.label = BINARY COALESCE(NULLIF(u.floor_label, ''), CASE WHEN u.floor = 0 THEN 'Rez-de-chaussée' ELSE CONCAT('Niveau ', u.floor) END) SET u.level_id = l.id WHERE u.deleted_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY FK_DCBB0C535FB14BA7');
        $this->addSql('DROP INDEX IDX_DCBB0C535FB14BA7 ON unit');
        $this->addSql('ALTER TABLE unit DROP level_id');
        $this->addSql('DROP TABLE building_level');
    }
}
