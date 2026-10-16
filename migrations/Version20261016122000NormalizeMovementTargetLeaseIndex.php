<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016122000NormalizeMovementTargetLeaseIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le nom de l’index de clé étrangère du bail cible avec celui attendu par Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement RENAME INDEX idx_res_move_next_lease TO IDX_1CCEF233EBD018C2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement RENAME INDEX IDX_1CCEF233EBD018C2 TO idx_res_move_next_lease');
    }
}
