<?php

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TransferControllerTest extends WebTestCase
{
    private $client;
    private int $fromAccountId;
    private int $toAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient(); 
        $this->createTestAccounts();
    }

    private function createTestAccounts(): void
    {
        // Create FROM account
        $this->client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'from-' . time() . '-' . rand(1000, 9999) . '@example.com', 'balance' => '1000.00'])
        );
        $fromData = json_decode($this->client->getResponse()->getContent(), true);
        $this->fromAccountId = $fromData['data']['id'];

        // Create TO account
        $this->client->request(
            'POST',
            '/api/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'to-' . time() . '-' . rand(1000, 9999) . '@example.com', 'balance' => '100.00'])
        );
        $toData = json_decode($this->client->getResponse()->getContent(), true);
        $this->toAccountId = $toData['data']['id'];
    }

    public function testTransferSuccess(): void
    {
        $payload = [
            'from' => $this->fromAccountId,
            'to' => $this->toAccountId,
            'amount' => '250.00',
            'referenceId'=> (string)time()
        ];
        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('referenceId', $data['data']);
    }

    public function testTransferValidationErrorMissingFrom(): void
    {
        $payload = [
            'to' => $this->toAccountId,
            'amount' => '250.00'
        ];

        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertArrayHasKey('from', $data['errors']);
    }

    public function testTransferValidationErrorInvalidAmount(): void
    {
        $payload = [
            'from' => $this->fromAccountId,
            'to' => $this->toAccountId,
            'amount' => '-100.00'
        ];

        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('amount', $data['errors']);
    }

    public function testTransferSameAccountError(): void
    {
        $payload = [
            'from' => $this->fromAccountId,
            'to' => $this->fromAccountId,
            'amount' => '100.00',
            'referenceId'=> 'ref-' . time() . '-' . bin2hex(random_bytes(3))
        ];

        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testTransferInsufficientFundsError(): void
    {
        $payload = [
            'from' => $this->fromAccountId,
            'to' => $this->toAccountId,
            'amount' => '5000.00',
            'referenceId'=> 'ref-' . time() . '-' . bin2hex(random_bytes(3))
        ];

        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
       
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testTransferAccountNotFoundError(): void
    {
        $payload = [
            'from' => $this->fromAccountId,
            'to' => 99999,
            'amount' => '100.00',
            "referenceId"=> (string)time()
            
        ];

        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testTransferIdempotency(): void
    {
        $referenceId = 'idempotency-' . (string)time();
        $payload = [
            'from' => $this->fromAccountId,
            'to' => $this->toAccountId,
            'amount' => '100.00',
            'referenceId' => $referenceId
        ];

        // First request
        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $response1 = $this->client->getResponse();
        $data1 = json_decode($response1->getContent(), true);
        $transactionId1 = $data1['data']['transactionId'] ?? $data1['data']['referenceId'];

        // Second request with same referenceId
        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $response2 = $this->client->getResponse();
        $data2 = json_decode($response2->getContent(), true);
        $transactionId2 = $data2['data']['transactionId'] ?? $data2['data']['referenceId'];

        // Both should return success and same transaction ID
        $this->assertSame(Response::HTTP_OK, $response1->getStatusCode());
        $this->assertSame(Response::HTTP_OK, $response2->getStatusCode());
        $this->assertSame($transactionId1, $transactionId2);
    }

    public function testTransferIdempotencyConflictDifferentPayload(): void
    {
        $referenceId = 'ref-' . time() . '-' . bin2hex(random_bytes(3));

        // First transfer
        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from' => $this->fromAccountId,
                'to' => $this->toAccountId,
                'amount' => '100.00',
                'referenceId' => $referenceId
            ])
        );
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Second transfer with same referenceId but different amount
        $this->client->request(
            'POST',
            '/api/transfer-funds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from' => $this->fromAccountId,
                'to' => $this->toAccountId,
                'amount' => '200.00',
                'referenceId' => $referenceId
            ])
        );
        $response = $this->client->getResponse();
        // if failed , due to worker not running
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }

    public function testGetTransferStatusSuccess(): void
    {
        $referenceId = 'status-' . (string)time();

        // Create transfer
        $this->client->request(
            'POST',
            '/api/transfer-status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from' => $this->fromAccountId,
                'to' => $this->toAccountId,
                'amount' => '100.00',
                'referenceId' => $referenceId
            ])
        );
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Get transfer status
        $this->client->request(
            'GET',
            '/api/transfer-status/' . $referenceId
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        // it will not work if worker isnot working because without it, record will not be saved
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('status', $data['data']);
    }

    public function testGetTransferStatusNotFound(): void
    {
        $this->client->request('GET', '/api/transfer-status/non-existent-reference');

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }
}