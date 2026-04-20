<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;
use App\Exception\ApiException;
use App\Constants\ApiMessages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Enum\TransactionStatus;
use App\Service\CacheService;
use App\Service\LockService;

class TransferService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccountRepository $accountRepo,
        private TransactionRepository $transactionRepo,
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
        private LockService $lockService,
        private CacheService $cacheService
    ) {}

    /**
     * Transfer funds from one account to another.
     * - idempotent for same (referenceId + payload)
     * - conflict (409) for same referenceId with different payload
     *
     * @throws ApiException
     */
    public function transfer(int $fromId, int $toId, string $amount, string $referenceId): Transaction
    {
        $referenceId = trim((string)$referenceId);

        // ✅ Idempotency check
        $existing = $this->transactionRepo->findOneBy([
            'referenceId' => $referenceId
        ]);

        if ($existing) {
            return $existing;
        }
        // try to acquire centralized lock
        $lock = $this->lockService->acquireTransferLock($referenceId, $fromId, $toId, 60);
        if ($lock === null) {
            throw new ApiException(ApiMessages::TRANSFER_FAILED, Response::HTTP_CONFLICT);
        }

        try {
            return $this->retryTransfer($fromId, $toId, $amount, $referenceId);
        } finally {
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }
    }

     private function retryTransfer(int $fromId, int $toId, string $amount, string $referenceId): Transaction
    {
        $retries = 3;
        while ($retries > 0) {
            try {
                return $this->doTransfer($fromId, $toId, $amount, $referenceId);
            } catch (DeadlockException $e) {
                $retries--;
                if ($retries === 0) {
                    throw new ApiException(ApiMessages::DEADLOCK_RETRY_FAILED, Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                usleep(100000); // 100ms
            }
        }

        throw new ApiException(ApiMessages::TRANSFER_FAILED, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

     private function doTransfer(int $fromId, int $toId, string $amount, string $referenceId): Transaction
    {

        $transaction = null;        

        try {
            
            $this->em->beginTransaction();
            // Fetch accounts in deterministic order to avoid deadlocks
            $firstId = min($fromId, $toId);
            $secondId = max($fromId, $toId);

            $first = $this->accountRepo->find($firstId);
            $second = $this->accountRepo->find($secondId);

            if (!$first || !$second) {
                throw new ApiException(ApiMessages::ACCOUNT_NOT_FOUND, Response::HTTP_NOT_FOUND, ['fromId' => $fromId, 'toId' => $toId]);
            }
        
            // Lock in deterministic order
            $this->em->lock($first, LockMode::PESSIMISTIC_WRITE);
            $this->em->lock($second, LockMode::PESSIMISTIC_WRITE);

             // map to logical from/to
            $from = ($fromId === $firstId) ? $first : $second;
            $to   = ($toId === $firstId) ? $first : $second;

            // 💰 Balance check using BCMath
            if (bccomp($from->getBalance(), $amount, 2) === -1) {
                throw new ApiException(
                    ApiMessages::INSUFFICIENT_FUNDS,
                    Response::HTTP_BAD_REQUEST,
                    [
                        'available' => $from->getBalance(),
                        'required'  => $amount
                    ]
                );
            }

            // 🧾 Create transaction (PENDING)
            $transaction = new Transaction();
            $transaction->setFromAccount($from);
            $transaction->setToAccount($to);
            $transaction->setAmount($amount);
            $transaction->setReferenceId($referenceId);
            $transaction->setStatus(TransactionStatus::PENDING);

            $this->em->persist($transaction);

            // 💸 Apply balance updates
            $from->decreaseBalance($amount);
            $to->increaseBalance($amount);

            
            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException $e) {
                // Another request already created it
                $this->em->rollback();
                return $this->transactionRepo->findOneBy(['referenceId' => $referenceId]);
            }

            $this->em->commit();
            $transaction->markSuccess();
            $this->em->flush();
            // IMPORTANT: invalidate cache
            $this->cacheService->deleteTransferStatus($referenceId);
  
            // 📝 Logging
            $this->logger->info(ApiMessages::TRANSFER_SUCCESS, [
                'referenceId' => $referenceId,
                'from' => $fromId,
                'to' => $toId,
                'amount' => $amount
            ]);

            return $transaction;

        } catch (\Throwable $e) {

            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            if ($transaction) {
                $transaction->markFailed();
                $this->em->flush();
            }
            $this->cacheService->deleteTransferStatus($referenceId);
           
            $this->logger->error(ApiMessages::TRANSFER_FAILED, [
                'error' => $e->getMessage(),
                'referenceId' => $referenceId
            ]);

            if ($e instanceof ApiException) {
                throw $e;
            }

            throw new ApiException(
                ApiMessages::TRANSFER_FAILED,
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['detail' => $e->getMessage()]
            );
        }
    }

    /**
     * Check idempotency for a referenceId.
     * Returns existing Transaction when same payload, otherwise throws ApiException when conflict.
     */
    public function resolveIdempotency(int $fromId, int $toId, string $amount, ?string $referenceId): ?Transaction
    {
        if ($referenceId === null) {
            return null;
        }

        $referenceId = trim((string)$referenceId);
        if ($referenceId === '') {
            return null;
        }
        
        $existing = $this->transactionRepo->findOneBy(['referenceId' => $referenceId]);
        if ($existing === null) {
            return null;
        }

        $samePayload =
            $existing->getFromAccount()->getId() === $fromId
            && $existing->getToAccount()->getId() === $toId
            && bccomp((string)$existing->getAmount(), (string)$amount, 2) === 0;

        if ($samePayload) {
            return $existing;
        }

        throw new ApiException(
            ApiMessages::DUPLICATE_REFERENCE,
            Response::HTTP_CONFLICT
        );
    }
}