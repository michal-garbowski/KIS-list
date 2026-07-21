<?php

declare(strict_types=1);

namespace App\Book\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class BorrowBookRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex('/^[0-9]{6}$/', message: 'This value should contain exactly six digits.')]
    public readonly string $borrowerCardNumber;

    public function __construct(string $borrowerCardNumber)
    {
        $this->borrowerCardNumber = $borrowerCardNumber;
    }
}
