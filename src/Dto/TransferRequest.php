<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public int $from;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public int $to;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public string $amount;

    #[Assert\NotBlank]
    public string $referenceId;
}