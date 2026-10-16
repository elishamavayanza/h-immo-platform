<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016121000ResidentialMovementTargetLease extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relie un transfert résidentiel à son bail de destination pour conserver les deux contrats dans l’historique.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement ADD next_lease_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_res_move_next_lease ON residential_movement (next_lease_id)');
        $this->addSql('ALTER TABLE residential_movement ADD CONSTRAINT FK_RES_MOVE_NEXT_LEASE FOREIGN KEY (next_lease_id) REFERENCES lease (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement DROP FOREIGN KEY FK_RES_MOVE_NEXT_LEASE');
        $this->addSql('DROP INDEX idx_res_move_next_lease ON residential_movement');
        $this->addSql('ALTER TABLE residential_movement DROP next_lease_id');
    }
}
