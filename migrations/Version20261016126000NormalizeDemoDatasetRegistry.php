<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016126000NormalizeDemoDatasetRegistry extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne l’UUID et son index unique du registre démo sur le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_dataset CHANGE uuid uuid BINARY(16) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DEMO_DATASET_UUID ON demo_dataset (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DEMO_DATASET_UUID ON demo_dataset');
        $this->addSql("ALTER TABLE demo_dataset CHANGE uuid uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
    }
}
