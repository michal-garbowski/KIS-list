<?php

declare(strict_types=1);

namespace App\Book\Domain\Exception;

final class BookNotFoundException extends \RuntimeException
{
    public static function withSerialNumber(string $serialNumber): self
    {
        return new self(sprintf('Book with serial number "%s" was not found.', $serialNumber));
    }
}
