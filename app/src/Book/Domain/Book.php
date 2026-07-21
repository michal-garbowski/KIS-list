<?php

declare(strict_types=1);

namespace App\Book\Domain;

use App\Book\Domain\Exception\BookAlreadyAvailableException;
use App\Book\Domain\Exception\BookAlreadyBorrowedException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'book')]
final class Book
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 6)]
    #[Groups(['book:read'])]
    private string $serialNumber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['book:read'])]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['book:read'])]
    private string $author;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['book:read'])]
    private ?\DateTimeImmutable $borrowedAt = null;

    #[ORM\Column(type: Types::STRING, length: 6, nullable: true)]
    #[Groups(['book:read'])]
    private ?string $borrowerCardNumber = null;

    private function __construct()
    {
    }

    public static function create(BookSerialNumber $serialNumber, string $title, string $author): self
    {
        $book = new self();
        $book->serialNumber = $serialNumber->value();
        $book->title = self::normalizeText($title, 'Title');
        $book->author = self::normalizeText($author, 'Author');

        return $book;
    }

    public function borrow(LibraryCardNumber $cardNumber, \DateTimeImmutable $at): void
    {
        if ($this->isBorrowed()) {
            throw BookAlreadyBorrowedException::withSerialNumber($this->serialNumber);
        }

        $this->borrowedAt = $at;
        $this->borrowerCardNumber = $cardNumber->value();
    }

    public function returnBook(): void
    {
        if (!$this->isBorrowed()) {
            throw BookAlreadyAvailableException::withSerialNumber($this->serialNumber);
        }

        $this->borrowedAt = null;
        $this->borrowerCardNumber = null;
    }

    #[Groups(['book:read'])]
    public function isBorrowed(): bool
    {
        return $this->borrowedAt !== null;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getBorrowedAt(): ?\DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function getBorrowerCardNumber(): ?string
    {
        return $this->borrowerCardNumber;
    }

    private static function normalizeText(string $value, string $fieldLabel): string
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException(sprintf('%s must not be blank.', $fieldLabel));
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(sprintf('%s must not be longer than 255 characters.', $fieldLabel));
        }

        return $trimmed;
    }
}
