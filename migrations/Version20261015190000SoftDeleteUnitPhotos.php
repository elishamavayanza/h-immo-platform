<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015190000SoftDeleteUnitPhotos extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les photos retirées des galeries d’unités pour permettre leur restauration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit_photo ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_unit_photo_unit_deleted ON unit_photo (unit_id, deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_unit_photo_unit_deleted ON unit_photo');
        $this->addSql('ALTER TABLE unit_photo DROP deleted_at');
    }
}
