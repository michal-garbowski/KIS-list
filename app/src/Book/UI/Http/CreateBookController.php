<?php

declare(strict_types=1);

namespace App\Book\UI\Http;

use App\Book\Domain\Book;
use App\Book\Domain\BookSerialNumber;
use App\Book\Infrastructure\Doctrine\BookRepository;
use App\Book\UI\Http\Request\CreateBookRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/books', name: 'book_create', methods: ['POST'], format: 'json')]
final class CreateBookController
{
    public function __construct(
        private readonly BookRepository $repository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                DenormalizerInterface::COLLECT_EXTRA_ATTRIBUTES_ERRORS => true,
            ],
        )]
        CreateBookRequest $request,
    ): JsonResponse {
        $book = Book::create(
            new BookSerialNumber($request->serialNumber),
            $request->title,
            $request->author,
        );

        $this->repository->add($book);

        $json = $this->serializer->serialize($book, 'json', ['groups' => ['book:read']]);

        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
    }
}
