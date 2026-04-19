<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417125227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, balance NUMERIC(15, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_7D3656A4E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, status VARCHAR(20) NOT NULL, reference_id VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, from_account_id INT NOT NULL, to_account_id INT NOT NULL, INDEX IDX_EAA81A4CB0CF99BD (from_account_id), INDEX IDX_EAA81A4CBC58BDC7 (to_account_id), UNIQUE INDEX uniq_reference_id (reference_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB0CF99BD FOREIGN KEY (from_account_id) REFERENCES account (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CBC58BDC7 FOREIGN KEY (to_account_id) REFERENCES account (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CB0CF99BD');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CBC58BDC7');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE transactions');
    }
}
