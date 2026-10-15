<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Aligne les noms des index de maintenance sur ceux générés par Doctrine. */
final class Version20261015160000MaintenanceDoctrineIndexNames extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les noms des index de maintenance avec le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX uniq_maintenance_uuid TO UNIQ_FA956839D17F50A6');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX idx_maintenance_parcel TO IDX_FA956839465E670C');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX idx_maintenance_building TO IDX_FA9568394D2A7E12');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX idx_maintenance_unit TO IDX_FA956839F8BD700D');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX idx_maintenance_assignment TO IDX_FA9568394129D6B6');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX idx_maintenance_user TO IDX_FA956839DE12AB56');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX UNIQ_FA956839D17F50A6 TO uniq_maintenance_uuid');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX IDX_FA956839465E670C TO idx_maintenance_parcel');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX IDX_FA9568394D2A7E12 TO idx_maintenance_building');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX IDX_FA956839F8BD700D TO idx_maintenance_unit');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX IDX_FA9568394129D6B6 TO idx_maintenance_assignment');
        $this->addSql('ALTER TABLE maintenance_work_order RENAME INDEX IDX_FA956839DE12AB56 TO idx_maintenance_user');
    }
}
