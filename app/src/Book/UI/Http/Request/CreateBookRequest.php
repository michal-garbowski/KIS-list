<?php

declare(strict_types=1);

namespace App\Book\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateBookRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex('/^[0-9]{6}$/', message: 'This value should contain exactly six digits.')]
    public readonly string $serialNumber;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public readonly string $title;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public readonly string $author;

    public function __construct(string $serialNumber, string $title, string $author)
    {
        $this->serialNumber = $serialNumber;
        $this->title = trim($title);
        $this->author = trim($author);
    }
}
