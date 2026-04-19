<?php

namespace App\Controller;

use App\Service\ApiResponseService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController
{
    public function __construct(private ApiResponseService $responseService) {}

    protected function success(mixed $data = null, string $message = '', int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->responseService->success($data, $message, $status);
    }
   
}