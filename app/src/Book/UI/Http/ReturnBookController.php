<?php

declare(strict_types=1);

namespace App\Book\UI\Http;

use App\Book\Domain\BookSerialNumber;
use App\Book\Infrastructure\Doctrine\BookRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/books/{serialNumber}/return', name: 'book_return', methods: ['POST'], requirements: ['serialNumber' => '[0-9]{6}'])]
final class ReturnBookController
{
    public function __construct(
        private readonly BookRepository $repository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function __invoke(string $serialNumber): JsonResponse
    {
        $book = $this->repository->get(new BookSerialNumber($serialNumber));
        $book->returnBook();
        $this->repository->save();

        $json = $this->serializer->serialize($book, 'json', ['groups' => ['book:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
