<?php

declare(strict_types=1);

namespace App\Book\UI\Http;

use App\Book\Domain\BookSerialNumber;
use App\Book\Infrastructure\Doctrine\BookRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/books/{serialNumber}', name: 'book_delete', methods: ['DELETE'], requirements: ['serialNumber' => '[0-9]{6}'])]
final class DeleteBookController
{
    public function __construct(private readonly BookRepository $repository)
    {
    }

    public function __invoke(string $serialNumber): Response
    {
        $book = $this->repository->get(new BookSerialNumber($serialNumber));
        $this->repository->remove($book);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
