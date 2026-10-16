<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016101000NormalizeResidentialMovementUuid extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le type UUID et le nom de son index unique sur la convention Doctrine du projet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement MODIFY uuid BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE residential_movement RENAME INDEX uniq_residential_movement_uuid TO UNIQ_1CCEF233D17F50A6');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE residential_movement RENAME INDEX UNIQ_1CCEF233D17F50A6 TO uniq_residential_movement_uuid');
        $this->addSql("ALTER TABLE residential_movement MODIFY uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
    }
}
