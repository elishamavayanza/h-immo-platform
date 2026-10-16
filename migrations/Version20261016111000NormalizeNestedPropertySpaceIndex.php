<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016111000NormalizeNestedPropertySpaceIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le nom de l’index parent des unités sur la convention Doctrine pour éviter une dérive de schéma.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit RENAME INDEX idx_unit_parent_unit TO IDX_DCBB0C538AF5044B');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit RENAME INDEX IDX_DCBB0C538AF5044B TO idx_unit_parent_unit');
    }
}
