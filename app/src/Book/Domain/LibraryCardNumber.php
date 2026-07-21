<?php

declare(strict_types=1);

namespace App\Book\Domain;

final class LibraryCardNumber
{
    public function __construct(private readonly string $value)
    {
        if (!preg_match('/^[0-9]{6}$/', $value)) {
            throw new \InvalidArgumentException('Library card number must be exactly six digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
