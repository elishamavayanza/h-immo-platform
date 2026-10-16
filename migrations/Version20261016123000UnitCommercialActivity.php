<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016123000UnitCommercialActivity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permet de renseigner l’activité exercée dans un local commercial ou professionnel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit ADD commercial_activity VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP commercial_activity');
    }
}
