<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table de révocation des jetons d'API.
 *
 * Un JWT est auto-porteur : sa signature prouve qu'il a été émis par le
 * serveur, mais rien ne permet de le retirer avant son expiration. Cette
 * table rend la déconnexion effective.
 */
final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table revoked_token : permet de révoquer un JWT d’API avant son expiration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE revoked_token (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, jti VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E5F664CD17F50A6 (uuid), UNIQUE INDEX uniq_revoked_token_jti (jti), INDEX idx_revoked_token_expires (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE revoked_token');
    }
}
