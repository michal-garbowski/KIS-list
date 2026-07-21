<?php

declare(strict_types=1);

namespace App\Tests\Book;

use App\Book\Domain\Book;
use App\Book\Domain\BookSerialNumber;
use App\Book\Domain\LibraryCardNumber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the #[ORM\Version] guard on Book actually prevents a lost update,
 * rather than being a decorative attribute. PHPUnit has no real concurrency,
 * so the race is reproduced at the SQL level instead: two independent
 * EntityManagers load the same row (same version), the first writes and
 * wins, the second's flush() must then fail because the row it thinks it's
 * updating no longer has the version it read.
 */
final class BookConcurrencyTest extends KernelTestCase
{
    private const SERIAL_NUMBER = '900001';

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE book');
        $em->persist(Book::create(new BookSerialNumber(self::SERIAL_NUMBER), 'Title', 'Author'));
        $em->flush();
        $em->clear();
    }

    public function testSecondConcurrentBorrowFailsOptimisticLock(): void
    {
        /** @var EntityManagerInterface $em1 */
        $em1 = self::getContainer()->get(EntityManagerInterface::class);
        $book1 = $em1->find(Book::class, self::SERIAL_NUMBER);
        self::assertNotNull($book1);

        // A second, genuinely independent EntityManager sharing the same
        // connection: its own UnitOfWork and identity map, so it loads its
        // own PHP object for the same row instead of reusing $em1's. Using
        // ManagerRegistry::resetManager() here would NOT work reliably in
        // this Symfony version's lazy-ghost-object service resetting doesn't
        // guarantee a truly independent manager instance/UnitOfWork.
        $em2 = new EntityManager($em1->getConnection(), $em1->getConfiguration());
        $book2 = $em2->find(Book::class, self::SERIAL_NUMBER);
        self::assertNotNull($book2);
        self::assertNotSame($book1, $book2);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // First writer: succeeds, row's version increments in the database.
        $book1->borrow(new LibraryCardNumber('111111'), $now);
        $em1->flush();

        // Second writer: still holds the pre-write state in memory (its
        // in-memory isBorrowed() check passes), but its flush must fail
        // because the database row's version has already moved on.
        self::assertFalse($book2->isBorrowed());
        $book2->borrow(new LibraryCardNumber('222222'), $now);

        // A failed flush leaves $em2 unusable for further Doctrine operations;
        // nothing after this line relies on it. The next test's setUp()
        // TRUNCATEs the table via a fresh call to the container's own EM.
        $this->expectException(OptimisticLockException::class);
        $em2->flush();
    }
}
