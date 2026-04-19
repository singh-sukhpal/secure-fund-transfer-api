<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAccountRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    public string $balance;
}