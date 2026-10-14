<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Ajoute une échéance aux suspensions des organisations et comptes utilisateurs. */
final class Version20261014100000TimedSuspensions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permet des suspensions temporaires ou indéterminées pour les organisations et les comptes utilisateurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD suspension_ends_at DATETIME DEFAULT NULL, ADD INDEX IDX_ORGANIZATION_SUSPENSION_END (status, suspension_ends_at)');
        $this->addSql('ALTER TABLE user ADD suspension_ends_at DATETIME DEFAULT NULL, ADD INDEX IDX_USER_SUSPENSION_END (is_active, suspension_ends_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP INDEX IDX_ORGANIZATION_SUSPENSION_END, DROP suspension_ends_at');
        $this->addSql('ALTER TABLE user DROP INDEX IDX_USER_SUSPENSION_END, DROP suspension_ends_at');
    }
}
