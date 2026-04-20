<?php

namespace App\Tests\Unit\Service;

use App\Service\ApiResponseService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseServiceTest extends TestCase
{
    private ApiResponseService $responseService;

    protected function setUp(): void
    {
        $this->responseService = new ApiResponseService();
    }

    public function testSuccessResponseStructure(): void
    {
        $data = ['id' => 1, 'email' => 'test@example.com'];
        $response = $this->responseService->success($data, 'Account created', 201);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Account created', $json['message']);
        $this->assertEquals($data, $json['data']);
    }

    public function testSuccessResponseWithDefaultValues(): void
    {
        $response = $this->responseService->success();
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame('', $json['message']);
        $this->assertEquals(null, $json['data']);
    }

    public function testErrorResponseAlwaysHasErrorsKey(): void
    {
        $response = $this->responseService->error('Validation failed', [], 422);
       
        $this->assertSame(422, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
       
        $this->assertSame('error', $json['status']);
        $this->assertSame('Validation failed', $json['message']);
    }

    public function testErrorResponseWithFieldErrors(): void
    {
        $errors = [
            'email' => 'This value should not be blank',
            'balance' => 'This value should be a valid decimal'
        ];
        $response = $this->responseService->error('Validation failed', $errors, 422);

        $this->assertSame(422, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertSame('error', $json['status']);
        $this->assertEquals($errors, $json['errors']);
    }

    public function testErrorResponseDefaultStatusCode(): void
    {
        $response = $this->responseService->error('Bad request');

        $this->assertSame(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertSame('error', $json['status']);
    }
}