<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/** Permet plusieurs rôles distincts pour un même utilisateur et une organisation. */
final class Version20261013100000MultiRoleOrganizationAssignments extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise plusieurs rôles par utilisateur et organisation tout en empêchant les affectations en double.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_user DROP INDEX uniq_org_user');
        $this->addSql('CREATE UNIQUE INDEX uniq_org_user_role ON organization_user (organization_id, user_id, role)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT organization_id, user_id FROM organization_user GROUP BY organization_id, user_id HAVING COUNT(*) > 1) duplicate_assignments') > 0,
            'Impossible de restaurer l’unicité utilisateur/organisation : des comptes ont plusieurs rôles.'
        );

        $this->addSql('DROP INDEX uniq_org_user_role ON organization_user');
        $this->addSql('CREATE UNIQUE INDEX uniq_org_user ON organization_user (organization_id, user_id)');
    }
}
