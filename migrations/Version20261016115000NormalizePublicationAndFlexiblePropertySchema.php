<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016115000NormalizePublicationAndFlexiblePropertySchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les types SQL et index historiques avec Doctrine, et garantit l’unicité des UUID des destinataires de publication.';
    }

    public function up(Schema $schema): void
    {
        foreach (['access_point', 'building_level'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s MODIFY uuid BINARY(16) NOT NULL, MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL, MODIFY deleted_at DATETIME DEFAULT NULL', $table));
        }

        $this->addSql('ALTER TABLE lease_occupant MODIFY uuid BINARY(16) NOT NULL, MODIFY created_at DATETIME NOT NULL, MODIFY start_date DATE NOT NULL, MODIFY end_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE publication MODIFY uuid BINARY(16) NOT NULL, MODIFY scheduled_at DATETIME DEFAULT NULL, MODIFY published_at DATETIME DEFAULT NULL, MODIFY ends_at DATETIME DEFAULT NULL, MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE publication RENAME INDEX uniq_publication_uuid TO UNIQ_AF3C6779D17F50A6');
        $this->addSql('ALTER TABLE publication RENAME INDEX idx_publication_organization TO IDX_AF3C677932C8A3DE');
        $this->addSql('ALTER TABLE publication RENAME INDEX idx_publication_creator TO IDX_AF3C6779B03A8386');
        $this->addSql('ALTER TABLE publication_recipient MODIFY uuid BINARY(16) NOT NULL, MODIFY available_at DATETIME NOT NULL, MODIFY first_viewed_at DATETIME DEFAULT NULL, MODIFY withdrawn_at DATETIME DEFAULT NULL, MODIFY created_at DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7447FDDED17F50A6 ON publication_recipient (uuid)');
        $this->addSql('ALTER TABLE publication_recipient RENAME INDEX idx_recipient_publication TO IDX_7447FDDE38B217A7');
        $this->addSql('ALTER TABLE publication_recipient RENAME INDEX idx_recipient_user TO IDX_7447FDDEA76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_recipient RENAME INDEX IDX_7447FDDE38B217A7 TO idx_recipient_publication');
        $this->addSql('ALTER TABLE publication_recipient RENAME INDEX IDX_7447FDDEA76ED395 TO idx_recipient_user');
        $this->addSql('DROP INDEX UNIQ_7447FDDED17F50A6 ON publication_recipient');
        $this->addSql("ALTER TABLE publication_recipient MODIFY uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', MODIFY available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY first_viewed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY withdrawn_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE publication RENAME INDEX UNIQ_AF3C6779D17F50A6 TO uniq_publication_uuid');
        $this->addSql('ALTER TABLE publication RENAME INDEX IDX_AF3C677932C8A3DE TO idx_publication_organization');
        $this->addSql('ALTER TABLE publication RENAME INDEX IDX_AF3C6779B03A8386 TO idx_publication_creator');
        $this->addSql("ALTER TABLE publication MODIFY uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', MODIFY scheduled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY ends_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE lease_occupant MODIFY uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY start_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', MODIFY end_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        foreach (['access_point', 'building_level'] as $table) {
            $this->addSql(sprintf("ALTER TABLE %s MODIFY uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', MODIFY deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'", $table));
        }
    }
}
