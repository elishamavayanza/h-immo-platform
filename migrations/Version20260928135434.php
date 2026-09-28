<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928135434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_active_lease_per_unit ON lease');
        $this->addSql('ALTER TABLE lease ADD terms LONGTEXT DEFAULT NULL, DROP active_unit_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lease ADD active_unit_id BIGINT DEFAULT NULL, DROP terms');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_lease_per_unit ON lease (active_unit_id)');
    }
}
