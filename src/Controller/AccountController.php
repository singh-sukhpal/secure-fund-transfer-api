<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Exception\ApiException;
use App\Service\AccountService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Dto\CreateAccountRequest;
use App\Service\ApiResponseService;
use App\Constants\ApiMessages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class AccountController extends ApiController
{
    public function __construct(
        ApiResponseService $responseService,
        private AccountService $accountService,
        private RateLimiterFactory $accountLimiter
    ) {
        parent::__construct($responseService);
    }

    #[Route('/api/account', name: 'create_account', methods: ['POST'])]
        public function createAccount(
            Request $request,
            ValidatorInterface $validator,
            SerializerInterface $serializer
        ): JsonResponse {
            
            $dto = $serializer->deserialize(
                $request->getContent(),
                CreateAccountRequest::class,
                'json'
            );

            // Validate input
            $errors = $validator->validate($dto);
            if (count($errors) > 0) {
                $fieldErrors = [];
                foreach ($errors as $violation) {
                    $field = $violation->getPropertyPath();
                    $fieldErrors[$field][] = $violation->getMessage();
                }
                throw new ApiException(ApiMessages::VALIDATION_FAILED, Response::HTTP_UNPROCESSABLE_ENTITY, $fieldErrors);
       
            }
            $key = 'account_' . $dto->email;

            $limiter = $this->accountLimiter->create($key);
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter();

                $retrySeconds = $retryAfter
                    ? $retryAfter->getTimestamp() - time()
                    : null;
                throw new ApiException(
                    ApiMessages::TOO_MANY_REQUESTS,
                    Response::HTTP_TOO_MANY_REQUESTS,
                    [
                        'retry_after_seconds' => $retrySeconds,
                    ]
                );
            }
            $account = $this->accountService->create($dto->email, $dto->balance);
            return $this->success([
                'id' => $account->getId(),
                'email' => $account->getEmail(),
                'balance' => number_format($account->getBalance(), 2, '.', ''),
            ], ApiMessages::ACCOUNT_CREATED, Response::HTTP_CREATED);
        }
}
