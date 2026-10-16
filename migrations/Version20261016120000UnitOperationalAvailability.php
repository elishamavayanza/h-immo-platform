<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000UnitOperationalAvailability extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un état opérationnel aux unités pour bloquer les locations pendant réservation, travaux ou indisponibilité.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE unit ADD operational_status VARCHAR(32) NOT NULL DEFAULT 'available'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP operational_status');
    }
}
