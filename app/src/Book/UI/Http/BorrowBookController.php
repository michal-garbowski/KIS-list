<?php

declare(strict_types=1);

namespace App\Book\UI\Http;

use App\Book\Domain\BookSerialNumber;
use App\Book\Domain\LibraryCardNumber;
use App\Book\Infrastructure\Doctrine\BookRepository;
use App\Book\UI\Http\Request\BorrowBookRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/books/{serialNumber}/borrow', name: 'book_borrow', methods: ['POST'], format: 'json', requirements: ['serialNumber' => '[0-9]{6}'])]
final class BorrowBookController
{
    public function __construct(
        private readonly BookRepository $repository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function __invoke(
        string $serialNumber,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                DenormalizerInterface::COLLECT_EXTRA_ATTRIBUTES_ERRORS => true,
            ],
        )]
        BorrowBookRequest $request,
    ): JsonResponse {
        $book = $this->repository->get(new BookSerialNumber($serialNumber));
        $book->borrow(
            new LibraryCardNumber($request->borrowerCardNumber),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->repository->save();

        $json = $this->serializer->serialize($book, 'json', ['groups' => ['book:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
