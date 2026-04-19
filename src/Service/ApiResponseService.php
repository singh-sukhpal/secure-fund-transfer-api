<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponseService
{
    public function success(mixed $data = null, string $message = '', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function error(string $message = '', array $errors = [], int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
        ];
        // only include "errors" key when there is error data
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        return new JsonResponse($payload, $statusCode);
    }
}