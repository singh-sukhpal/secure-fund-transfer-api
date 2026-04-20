<?php

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AccountControllerTest extends WebTestCase
{
    public function testCreateAccountSuccess(): void
    {
        $client = static::createClient();
        $payload = [
            'email' => 'user4@example.com',
            'balance' => '1000.00'
        ];

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertSame('user4@example.com', $data['data']['email']);
        $this->assertSame('1000.00', $data['data']['balance']);
    }

    public function testCreateAccountValidationErrorEmptyEmail(): void
    {
        $client = static::createClient();
        $payload = [
            'email' => '',
            'balance' => '100.00'
        ];

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('Validation failed!', $data['message']);
        $this->assertIsArray($data['errors']);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testCreateAccountValidationErrorInvalidEmail(): void
    {
        $client = static::createClient();
        $payload = [
            'email' => 'not-an-email',
            'balance' => '100.00'
        ];

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testCreateAccountValidationErrorInvalidBalance(): void
    {
        $client = static::createClient();
        $payload = [
            'email' => 'user@example.com',
            'balance' => '-100.00'
        ];

        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('balance', $data['errors']);
    }

    public function testCreateAccountDuplicateEmail(): void
    {
        $client = static::createClient();
        $payload = [
            'email' => 'duplicate2@example.com',
            'balance' => '100.00'
        ];

        // Create first account
        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // Try to create duplicate
        $client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }
}