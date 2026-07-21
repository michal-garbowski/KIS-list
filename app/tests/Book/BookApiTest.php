<?php

declare(strict_types=1);

namespace App\Tests\Book;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BookApiTest extends ApiTestCase
{
    public function testCreateAndListPreservesLeadingZero(): void
    {
        $this->createBook(serialNumber: '000042', title: 'Lalka', author: 'Bolesław Prus');

        $this->client->request('GET', '/books');

        self::assertResponseStatusCodeSame(200);
        $books = $this->decodeResponse();
        self::assertCount(1, $books);
        self::assertSame('000042', $books[0]['serialNumber']);
        self::assertSame('Lalka', $books[0]['title']);
        self::assertSame('Bolesław Prus', $books[0]['author']);
    }

    public function testAvailableBookResponseHasExplicitNulls(): void
    {
        $this->createBook(serialNumber: '000042');

        $this->client->request('GET', '/books');
        $book = $this->decodeResponse()[0];

        self::assertArrayHasKey('borrowedAt', $book);
        self::assertArrayHasKey('borrowerCardNumber', $book);
        self::assertNull($book['borrowedAt']);
        self::assertNull($book['borrowerCardNumber']);
        self::assertFalse($book['borrowed']);
    }

    public function testListIsSortedBySerialNumber(): void
    {
        $this->createBook(serialNumber: '000030');
        $this->createBook(serialNumber: '000010');
        $this->createBook(serialNumber: '000020');

        $this->client->request('GET', '/books');
        $books = $this->decodeResponse();

        self::assertSame(['000010', '000020', '000030'], array_column($books, 'serialNumber'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSerialNumberProvider(): iterable
    {
        yield 'too short' => ['4242'];
        yield 'too long' => ['42424242'];
        yield 'contains letters' => ['abc123'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidSerialNumberProvider')]
    public function testCreateRejectsInvalidSerialNumber(string $serialNumber): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['serialNumber' => $serialNumber, 'title' => 'X', 'author' => 'Y'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->decodeResponse()['error']['code']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidTitleOrAuthorProvider(): iterable
    {
        yield 'blank title' => ['', 'Author', 'title'];
        yield 'whitespace-only title' => ['   ', 'Author', 'title'];
        yield 'title too long' => [str_repeat('a', 256), 'Author', 'title'];
        yield 'blank author' => ['Title', '', 'author'];
        yield 'whitespace-only author' => ['Title', '   ', 'author'];
        yield 'author too long' => ['Title', str_repeat('ą', 256), 'author'];
    }

    #[DataProvider('invalidTitleOrAuthorProvider')]
    public function testCreateRejectsInvalidTitleOrAuthor(string $title, string $author, string $invalidField): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['serialNumber' => '123123', 'title' => $title, 'author' => $author], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $error = $this->decodeResponse()['error'];
        self::assertSame('VALIDATION_FAILED', $error['code']);
        self::assertArrayHasKey($invalidField, $error['details']);
    }

    public function testCreateRejectsMissingField(): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['serialNumber' => '123123', 'title' => 'X'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('author', $this->decodeResponse()['error']['details']);
    }

    public function testCreateRejectsMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{not valid json',
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('INVALID_JSON', $this->decodeResponse()['error']['code']);
    }

    public function testCreateRejectsUnsupportedContentType(): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'not json',
        );

        self::assertResponseStatusCodeSame(415);
        self::assertSame('UNSUPPORTED_MEDIA_TYPE', $this->decodeResponse()['error']['code']);
    }

    public function testCreateRejectsUnknownField(): void
    {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(
                ['serialNumber' => '123123', 'title' => 'X', 'author' => 'Y', 'unexpectedField' => 'oops'],
                \JSON_THROW_ON_ERROR,
            ),
        );

        self::assertResponseStatusCodeSame(422);
        $error = $this->decodeResponse()['error'];
        self::assertSame('VALIDATION_FAILED', $error['code']);
        self::assertArrayHasKey('unexpectedField', $error['details']);
    }

    public function testCreateRejectsDuplicateSerialNumber(): void
    {
        $this->createBook(serialNumber: '000042');

        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['serialNumber' => '000042', 'title' => 'X', 'author' => 'Y'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        self::assertSame('BOOK_ALREADY_EXISTS', $this->decodeResponse()['error']['code']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCardNumberProvider(): iterable
    {
        yield 'too short' => ['123'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidCardNumberProvider')]
    public function testBorrowRejectsInvalidCardNumber(string $cardNumber): void
    {
        $serialNumber = $this->createBook();

        $this->client->request(
            'POST',
            "/books/{$serialNumber}/borrow",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['borrowerCardNumber' => $cardNumber], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->decodeResponse()['error']['code']);
    }

    public function testBorrowRejectsMissingCardNumber(): void
    {
        $serialNumber = $this->createBook();

        $this->client->request(
            'POST',
            "/books/{$serialNumber}/borrow",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testBorrowSetsLoanState(): void
    {
        $serialNumber = $this->createBook();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->client->request(
            'POST',
            "/books/{$serialNumber}/borrow",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['borrowerCardNumber' => '654321'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
        $book = $this->decodeResponse();
        self::assertTrue($book['borrowed']);
        self::assertSame('654321', $book['borrowerCardNumber']);
        self::assertNotNull($book['borrowedAt']);

        // Format assertion + a reasonable time window, never an exact-second match.
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $book['borrowedAt']);
        $borrowedAt = new \DateTimeImmutable($book['borrowedAt']);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $borrowedAt->getTimestamp());
        self::assertLessThanOrEqual($before->getTimestamp() + 5, $borrowedAt->getTimestamp());
    }

    public function testBorrowAlreadyBorrowedReturnsConflict(): void
    {
        $serialNumber = $this->createBook();
        $payload = json_encode(['borrowerCardNumber' => '654321'], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', "/books/{$serialNumber}/borrow", server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', "/books/{$serialNumber}/borrow", server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('BOOK_ALREADY_BORROWED', $this->decodeResponse()['error']['code']);
    }

    public function testReturnClearsLoanState(): void
    {
        $serialNumber = $this->createBook();
        $this->client->request(
            'POST',
            "/books/{$serialNumber}/borrow",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['borrowerCardNumber' => '654321'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', "/books/{$serialNumber}/return");

        self::assertResponseStatusCodeSame(200);
        $book = $this->decodeResponse();
        self::assertFalse($book['borrowed']);
        self::assertNull($book['borrowedAt']);
        self::assertNull($book['borrowerCardNumber']);
    }

    public function testReturnAlreadyAvailableReturnsConflict(): void
    {
        $serialNumber = $this->createBook();

        $this->client->request('POST', "/books/{$serialNumber}/return");

        self::assertResponseStatusCodeSame(409);
        self::assertSame('BOOK_ALREADY_AVAILABLE', $this->decodeResponse()['error']['code']);
    }

    public function testDeleteRemovesBook(): void
    {
        $serialNumber = $this->createBook();

        $this->client->request('DELETE', "/books/{$serialNumber}");
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/books');
        self::assertSame([], $this->decodeResponse());
    }

    public function testDeleteWorksRegardlessOfLoanState(): void
    {
        $serialNumber = $this->createBook();
        $this->client->request(
            'POST',
            "/books/{$serialNumber}/borrow",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['borrowerCardNumber' => '654321'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->request('DELETE', "/books/{$serialNumber}");

        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingBookOperationProvider(): iterable
    {
        yield 'delete' => ['DELETE', '/books/999999'];
        yield 'borrow' => ['POST', '/books/999999/borrow'];
        yield 'return' => ['POST', '/books/999999/return'];
    }

    #[DataProvider('missingBookOperationProvider')]
    public function testOperationsOnMissingBookReturn404(string $method, string $uri): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['borrowerCardNumber' => '654321'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        self::assertSame('BOOK_NOT_FOUND', $this->decodeResponse()['error']['code']);
    }

    public function testInvalidSerialNumberInUrlReturnsGenericNotFound(): void
    {
        $this->client->request('DELETE', '/books/abc');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->decodeResponse()['error']['code']);
    }

    public function testUnsupportedMethodReturns405WithAllowHeader(): void
    {
        $this->client->request('PUT', '/books');

        self::assertResponseStatusCodeSame(405);
        self::assertSame('METHOD_NOT_ALLOWED', $this->decodeResponse()['error']['code']);
        self::assertTrue($this->client->getResponse()->headers->has('Allow'));
    }
}
