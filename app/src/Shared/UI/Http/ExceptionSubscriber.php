<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Book\Domain\Exception\BookAlreadyAvailableException;
use App\Book\Domain\Exception\BookAlreadyBorrowedException;
use App\Book\Domain\Exception\BookAlreadyExistsException;
use App\Book\Domain\Exception\BookNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Normalizes every exception on /books* API routes into a single stable JSON
 * envelope. Non-API routes (e.g. the homepage) are left to Symfony's default
 * HTML error handling.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();

        if ('/books' !== $path && !str_starts_with($path, '/books/')) {
            return;
        }

        [$status, $code, $message, $details, $headers] = $this->resolve($event->getThrowable());

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $status) {
            $this->logger->error('Unhandled exception on API request.', [
                'exception' => $event->getThrowable(),
                'path' => $path,
            ]);
        }

        $error = ['code' => $code, 'message' => $message];
        if (null !== $details) {
            $error['details'] = $details;
        }

        $event->setResponse(new JsonResponse(['error' => $error], $status, $headers));
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, list<string>>|null, 4: array<string, string>}
     */
    private function resolve(\Throwable $throwable): array
    {
        if ($throwable instanceof BookNotFoundException) {
            return [Response::HTTP_NOT_FOUND, 'BOOK_NOT_FOUND', $throwable->getMessage(), null, []];
        }

        if ($throwable instanceof BookAlreadyBorrowedException) {
            return [Response::HTTP_CONFLICT, 'BOOK_ALREADY_BORROWED', $throwable->getMessage(), null, []];
        }

        if ($throwable instanceof BookAlreadyAvailableException) {
            return [Response::HTTP_CONFLICT, 'BOOK_ALREADY_AVAILABLE', $throwable->getMessage(), null, []];
        }

        if ($throwable instanceof BookAlreadyExistsException) {
            return [Response::HTTP_CONFLICT, 'BOOK_ALREADY_EXISTS', $throwable->getMessage(), null, []];
        }

        if ($throwable instanceof OptimisticLockException) {
            // The state changed between read and write (e.g. two concurrent
            // borrow attempts). Distinct from BOOK_ALREADY_BORROWED: this
            // only proves the row changed, not what the current state is.
            return [Response::HTTP_CONFLICT, 'CONCURRENT_MODIFICATION', 'The book was modified concurrently. Please retry with the latest state.', null, []];
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $headers = $throwable->getHeaders();

            if ($status >= 500) {
                return [Response::HTTP_INTERNAL_SERVER_ERROR, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.', null, []];
            }

            if (Response::HTTP_UNPROCESSABLE_ENTITY === $status) {
                $previous = $throwable->getPrevious();
                $details = $previous instanceof ValidationFailedException ? $this->violationsToDetails($previous) : null;

                return [$status, 'VALIDATION_FAILED', 'Request validation failed.', $details, $headers];
            }

            return match ($status) {
                Response::HTTP_BAD_REQUEST => [$status, 'INVALID_JSON', 'The request body is not valid JSON.', null, $headers],
                Response::HTTP_NOT_FOUND => [$status, 'NOT_FOUND', 'The requested resource was not found.', null, $headers],
                Response::HTTP_METHOD_NOT_ALLOWED => [$status, 'METHOD_NOT_ALLOWED', 'The HTTP method is not allowed for this resource.', null, $headers],
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE => [$status, 'UNSUPPORTED_MEDIA_TYPE', 'The request Content-Type is not supported.', null, $headers],
                default => [$status, 'ERROR', $throwable->getMessage(), null, $headers],
            };
        }

        return [Response::HTTP_INTERNAL_SERVER_ERROR, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.', null, []];
    }

    /**
     * @return array<string, list<string>>
     */
    private function violationsToDetails(ValidationFailedException $exception): array
    {
        $details = [];

        foreach ($exception->getViolations() as $violation) {
            $key = (string) $violation->getPropertyPath();
            $key = '' === $key ? '_request' : $key;
            $details[$key][] = $violation->getMessage();
        }

        return $details;
    }
}
