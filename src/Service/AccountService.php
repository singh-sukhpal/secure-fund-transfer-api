<?php

namespace App\Service;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\ApiException;
use App\Constants\ApiMessages;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class AccountService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccountRepository $accountRepo
    ) {}

    public function create(string $email, float $balance): Account
    {
        $account = new Account();
        $account->setEmail($email);
        $account->setBalance($balance);

        $this->em->persist($account);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new ApiException(
                ApiMessages::ACCOUNT_ALREADY_EXISTS,
                Response::HTTP_CONFLICT
            );
        }

        return $account;
    }
}