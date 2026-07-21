<?php

declare(strict_types=1);

namespace App\Book\Infrastructure\Doctrine;

use App\Book\Domain\Book;
use App\Book\Domain\BookSerialNumber;
use App\Book\Domain\Exception\BookAlreadyExistsException;
use App\Book\Domain\Exception\BookNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class BookRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(BookSerialNumber $serialNumber): Book
    {
        $book = $this->entityManager->find(Book::class, $serialNumber->value());

        if (null === $book) {
            throw BookNotFoundException::withSerialNumber($serialNumber->value());
        }

        return $book;
    }

    /**
     * @return Book[]
     */
    public function findAllOrdered(): array
    {
        return $this->entityManager->getRepository(Book::class)
            ->createQueryBuilder('book')
            ->orderBy('book.serialNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function add(Book $book): void
    {
        try {
            $this->entityManager->persist($book);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw BookAlreadyExistsException::withSerialNumber($book->getSerialNumber());
        }
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }

    public function remove(Book $book): void
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }
}
