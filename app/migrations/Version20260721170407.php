<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721170407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optimistic locking version column to book.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book DROP version');
    }
}
