<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023170000NormalizeCustomerInquirySchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les types et les noms d’index des tables de demandes sur le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_inquiry MODIFY idempotency_key_hash VARCHAR(64) NOT NULL, MODIFY payload_hash VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE customer_inquiry RENAME INDEX uniq_inquiry_uuid TO UNIQ_46E4A74ED17F50A6');
        $this->addSql('ALTER TABLE customer_inquiry RENAME INDEX uniq_inquiry_converted_lease TO UNIQ_46E4A74EF542C244');
        $this->addSql('ALTER TABLE customer_inquiry_history RENAME INDEX uniq_inquiry_history_uuid TO UNIQ_B78F5874D17F50A6');
        $this->addSql('ALTER TABLE customer_visit RENAME INDEX uniq_customer_visit_uuid TO UNIQ_8D4B9C02D17F50A6');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_visit RENAME INDEX UNIQ_8D4B9C02D17F50A6 TO uniq_customer_visit_uuid');
        $this->addSql('ALTER TABLE customer_inquiry_history RENAME INDEX UNIQ_B78F5874D17F50A6 TO uniq_inquiry_history_uuid');
        $this->addSql('ALTER TABLE customer_inquiry RENAME INDEX UNIQ_46E4A74EF542C244 TO uniq_inquiry_converted_lease');
        $this->addSql('ALTER TABLE customer_inquiry RENAME INDEX UNIQ_46E4A74ED17F50A6 TO uniq_inquiry_uuid');
        $this->addSql('ALTER TABLE customer_inquiry MODIFY idempotency_key_hash CHAR(64) NOT NULL, MODIFY payload_hash CHAR(64) NOT NULL');
    }
}
