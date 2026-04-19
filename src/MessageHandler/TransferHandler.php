<?php 

namespace App\MessageHandler;

use App\Message\TransferMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Service\TransferService;

#[AsMessageHandler]
class TransferHandler
{
    public function __construct(
        private TransferService $service
    ) {}

    public function __invoke(TransferMessage $message)
    {
        $this->service->transfer(
            $message->from,
            $message->to,
            $message->amount,
            $message->referenceId
        );
    }
}