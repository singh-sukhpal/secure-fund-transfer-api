<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ExceptionHandlingTest extends WebTestCase
{
    public function testNotFoundReturnsStandardErrorFormat(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/non-existent-endpoint');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertArrayHasKey('message', $data);
    }

    public function testValidationErrorReturnsCorrectStatusCode(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => '',
                'balance' => 'invalid'
            ])
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertIsArray($data['errors']);
    }

    public function testMalformedJsonReturnsError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"invalid json"'
        );

        $response = $client->getResponse();
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testResponseHeadersAreCorrect(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com', 'balance' => '100.00'])
        );

        $response = $client->getResponse();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }
}