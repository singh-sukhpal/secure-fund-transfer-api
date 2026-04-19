<?php 

namespace App\Message;

class TransferMessage
{
    public function __construct(
        public int $from,
        public int $to,
        public string $amount,
        public string $referenceId
    ) {}
}