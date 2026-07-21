<?php

declare(strict_types=1);

namespace App\Book\UI\Http;

use App\Book\Infrastructure\Doctrine\BookRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/books', name: 'book_list', methods: ['GET'])]
final class ListBooksController
{
    public function __construct(
        private readonly BookRepository $repository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $json = $this->serializer->serialize(
            $this->repository->findAllOrdered(),
            'json',
            ['groups' => ['book:read']],
        );

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
