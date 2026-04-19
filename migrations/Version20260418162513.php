<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418162513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX idx_from_account_status ON transactions (from_account_id, status)');
        $this->addSql('CREATE INDEX idx_to_account_status ON transactions (to_account_id, status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_from_account_status ON transactions');
        $this->addSql('DROP INDEX idx_to_account_status ON transactions');
        $this->addSql('ALTER TABLE transactions CHANGE status status VARCHAR(20) NOT NULL');
    }
}
