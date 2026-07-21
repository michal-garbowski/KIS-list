<?php

declare(strict_types=1);

namespace App\Book\Domain\Exception;

final class BookAlreadyBorrowedException extends \RuntimeException
{
    public static function withSerialNumber(string $serialNumber): self
    {
        return new self(sprintf('Book with serial number "%s" is already borrowed.', $serialNumber));
    }
}
