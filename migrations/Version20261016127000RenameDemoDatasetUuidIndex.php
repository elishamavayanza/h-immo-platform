<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016127000RenameDemoDatasetUuidIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le nom généré de l’index UUID du registre démo sur Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_dataset RENAME INDEX uniq_demo_dataset_uuid TO UNIQ_700B1513D17F50A6');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_dataset RENAME INDEX UNIQ_700B1513D17F50A6 TO uniq_demo_dataset_uuid');
    }
}
