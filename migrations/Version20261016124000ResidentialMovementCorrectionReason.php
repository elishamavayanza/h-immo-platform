<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016124000ResidentialMovementCorrectionReason extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le motif visible des corrections de mouvements résidentiels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement ADD correction_reason VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement DROP correction_reason');
    }
}
