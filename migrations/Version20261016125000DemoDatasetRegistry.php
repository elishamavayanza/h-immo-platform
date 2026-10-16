<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016125000DemoDatasetRegistry extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un registre explicite des jeux de démonstration générés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE demo_dataset (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', created_at DATETIME NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, seed VARCHAR(10) NOT NULL, scenario VARCHAR(16) NOT NULL, generator_version VARCHAR(20) NOT NULL, UNIQUE INDEX uniq_demo_dataset_seed_scenario (seed, scenario), UNIQUE INDEX uniq_demo_dataset_organization (organization_id), PRIMARY KEY(id), CONSTRAINT fk_demo_dataset_organization FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo_dataset');
    }
}
