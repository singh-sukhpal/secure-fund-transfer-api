<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Repository\AccountRepository;

use App\Message\TransferMessage;
use App\Dto\TransferRequest;
use App\Service\TransferService;
use App\Exception\ApiException;
use App\Service\ApiResponseService;
use App\Constants\ApiMessages;
use App\Service\CacheService;
use App\Service\LockService;

use App\Repository\TransactionRepository;

final class TransferController extends ApiController
{
    public function __construct(
        ApiResponseService $responseService,
        private TransferService $service,
        private RateLimiterFactory $transferLimiter,
        private MessageBusInterface $bus,
        private TransactionRepository $transactionRepo,
        private CacheService $cacheService,
        private LockService $lockService,
        private AccountRepository $accountRepo
    ) {
        parent::__construct($responseService); 
    }

    #[Route('/api/transfer-funds', name: 'transfer_funds', methods: ['POST'])]
    public function transfer(
        Request $request,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        $dto = $serializer->deserialize(
            $request->getContent(),
            TransferRequest::class,
            'json'
        );
        
        // ✅ Validate input
        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $formatted = [];
            foreach ($errors as $e) {
                $formatted[$e->getPropertyPath()][] = $e->getMessage();
            }

            throw new ApiException(ApiMessages::VALIDATION_FAILED, Response::HTTP_UNPROCESSABLE_ENTITY, $formatted);
        }

        // ACCOUNT EXISTENCE CHECK (FAST FAIL)
        $fromAccount = $this->accountRepo->find((int)$dto->from);
        $toAccount   = $this->accountRepo->find((int)$dto->to);

        if (!$fromAccount || !$toAccount) {
            throw new ApiException(
                ApiMessages::ACCOUNT_NOT_FOUND,
                Response::HTTP_NOT_FOUND
            );
        }
         // SAME ACCOUNT CHECK
        if ((int)$dto->from === (int)$dto->to) {
            throw new ApiException(
                ApiMessages::SAME_ACCOUNT_TRANSFER,
                Response::HTTP_BAD_REQUEST
            );
        }

        //   // 💰 Balance check using BCMath
        if (bccomp($fromAccount->getBalance(), $dto->amount, 2) === -1) {
            throw new ApiException(
                ApiMessages::INSUFFICIENT_FUNDS,
                Response::HTTP_CONFLICT,
                [
                    'available' => $fromAccount->getBalance(),
                    'required'  => $dto->amount
                ]
            );
        }
      
         // 🔒 Rate limiting (per account)
        $limiter = $this->transferLimiter->create('transfer_' . $dto->from);

        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            throw new ApiException(
                ApiMessages::TOO_MANY_REQUESTS,
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'retry_after' => $limit->getRetryAfter()?->getTimestamp()
                ]
            );
        }
      
        // Synchronous idempotency check BEFORE dispatching to the queue
        $existing = $this->service->resolveIdempotency((int)$dto->from, (int)$dto->to, (string)$dto->amount, $dto->referenceId);
        if ($existing !== null) {
            return $this->success([
                'transactionId' => $existing->getId(),
            ], ApiMessages::TRANSFER_SUCCESS, Response::HTTP_OK);
        }

        // optional: acquire short distributed lock to avoid races before enqueue
        $lock = $this->lockService->acquireTransferLock($dto->referenceId, (int)$dto->from, (int)$dto->to, 30);
        if ($lock === null) {
            throw new ApiException(ApiMessages::TRANSFER_FAILED, Response::HTTP_CONFLICT);
        }
        try {
            //  Dispatch async job
            $this->bus->dispatch(new \App\Message\TransferMessage((int)$dto->from, (int)$dto->to, (string)$dto->amount, $dto->referenceId));
        } finally {
            if ($lock && $lock->isAcquired()) {
                $lock->release();
            }
        }
        
       
        return $this->success([
            'status' => 'processing',
            'referenceId' => $dto->referenceId
        ], ApiMessages::TRANSFER_PROCESS_QUEUE);

    }

    #[Route('/api/transfer-status/{referenceId}', name: 'transfer_status', methods: ['GET'])]
    public function status(string $referenceId): JsonResponse
    {
        $data = $this->cacheService->getTransferStatus($referenceId, function (ItemInterface $item) use ($referenceId) {

            $item->expiresAfter(60); // 60 seconds

            $transaction = $this->transactionRepo->findOneBy([
                'referenceId' => $referenceId
            ]);

            if (!$transaction) {
                return [
                    'found' => false
                ];
            }

            return [
                'found' => true,
                'referenceId' => $transaction->getReferenceId(),
                'status' => $transaction->getStatus(),
                'amount' => $transaction->getAmount(),
                'from' => $transaction->getFromAccount()->getId(),
                'to' => $transaction->getToAccount()->getId(),
            ];
        });

        if (!$data['found']) {
            throw new ApiException(ApiMessages::TRANSACTION_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->success($data, ApiMessages::TRANSFER_STATUS);
       
    }
}
