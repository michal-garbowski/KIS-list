<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        static::getContainer()->get('doctrine')->getConnection()->executeStatement('TRUNCATE book');
    }

    /**
     * Creates a book through the public API and returns its serial number.
     */
    protected function createBook(
        string $serialNumber = '100001',
        string $title = 'Sample Title',
        string $author = 'Sample Author',
    ): string {
        $this->client->request(
            'POST',
            '/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'serialNumber' => $serialNumber,
                'title' => $title,
                'author' => $author,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);

        return $serialNumber;
    }

    /**
     * @return array<mixed>
     */
    protected function decodeResponse(): array
    {
        return json_decode(
            $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
    }
}
