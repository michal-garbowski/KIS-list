<?php

declare(strict_types=1);

namespace App\Book\Domain;

final class BookSerialNumber
{
    public function __construct(private readonly string $value)
    {
        if (!preg_match('/^[0-9]{6}$/', $value)) {
            throw new \InvalidArgumentException('Book serial number must be exactly six digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
