<?php

declare(strict_types=1);

namespace App\Tests\Book;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Confirms the three CHECK constraints added by hand in the migration are
 * actually enforced by Postgres, not dead SQL. Bypasses the application
 * layer (raw INSERT) since the domain model/validator would never let these
 * values through in the first place.
 */
final class BookConstraintsTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get('doctrine')->getConnection();
        $this->connection->executeStatement('TRUNCATE book');
    }

    public function testSerialNumberFormatConstraintIsEnforced(): void
    {
        $this->assertInsertIsRejected(
            "INSERT INTO book (serial_number, title, author) VALUES ('abc123', 'Title', 'Author')",
        );
    }

    public function testBorrowerCardNumberFormatConstraintIsEnforced(): void
    {
        $this->assertInsertIsRejected(
            "INSERT INTO book (serial_number, title, author, borrowed_at, borrower_card_number)
             VALUES ('123456', 'Title', 'Author', NOW(), 'bad-card')",
        );
    }

    public function testLoanConsistencyConstraintIsEnforced(): void
    {
        $this->assertInsertIsRejected(
            "INSERT INTO book (serial_number, title, author, borrowed_at, borrower_card_number)
             VALUES ('123456', 'Title', 'Author', NOW(), NULL)",
        );
    }

    private function assertInsertIsRejected(string $sql): void
    {
        $this->connection->beginTransaction();

        try {
            $this->expectException(DbalException::class);
            $this->connection->executeStatement($sql);
        } finally {
            // A CHECK violation aborts the transaction; it must be rolled
            // back before the connection can be used again, including by
            // later tests sharing the same connection.
            $this->connection->rollBack();
        }
    }
}
