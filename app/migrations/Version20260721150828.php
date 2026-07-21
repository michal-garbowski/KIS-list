<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721150828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the book table with format and loan-consistency CHECK constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE book (serial_number VARCHAR(6) NOT NULL, title VARCHAR(255) NOT NULL, author VARCHAR(255) NOT NULL, borrowed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, borrower_card_number VARCHAR(6) DEFAULT NULL, PRIMARY KEY (serial_number))');

        $this->addSql('ALTER TABLE book ADD CONSTRAINT chk_loan_consistency
            CHECK ((borrowed_at IS NULL) = (borrower_card_number IS NULL))');

        $this->addSql("ALTER TABLE book ADD CONSTRAINT chk_serial_number_format
            CHECK (serial_number ~ '^[0-9]{6}$')");

        $this->addSql("ALTER TABLE book ADD CONSTRAINT chk_borrower_card_number_format
            CHECK (borrower_card_number IS NULL OR borrower_card_number ~ '^[0-9]{6}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE book');
    }
}
