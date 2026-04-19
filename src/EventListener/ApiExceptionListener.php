<?php

namespace App\EventListener;

use App\Exception\ApiException;
use App\Service\ApiResponseService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use App\Constants\ApiMessages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class ApiExceptionListener implements EventSubscriberInterface
{
    public function __construct(private ApiResponseService $responseService, private bool $debug)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // ✅ Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

         // custom exceptions (HIGHEST PRIORITY)
        if ($exception instanceof ApiException) {
            $event->setResponse(
                $this->responseService->error(
                    $exception->getMessage(),
                    $exception->getErrors(),
                    $exception->getStatusCode()
                )
            );
            return;
        }

        if ($exception instanceof HandlerFailedException) {

            // unwrap real exception
            $realException = $exception->getPrevious();

            // fallback message
            $message = $realException?->getMessage() ?? 'Transfer failed';

            $event->setResponse(
                $this->responseService->error(
                    $message,
                    [],
                    Response::HTTP_BAD_REQUEST
                )
            );

            return;
        }
        // Validation errors (Symfony 6+ ValidationFailedException)
        if ($exception instanceof ValidationFailedException) {
            
            $errors = $this->formatViolations($exception->getViolations());

            $event->setResponse(
                $this->responseService->error(ApiMessages::VALIDATION_FAILED, $errors, Response::HTTP_UNPROCESSABLE_ENTITY)
            );
            return;
        }

        // Not found
        if ($exception instanceof NotFoundHttpException) {
            $response = $this->responseService->error(ApiMessages::NOT_FOUND, [], Response::HTTP_NOT_FOUND);
            $event->setResponse($response);
            return;
        }

        // Other HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(
                $this->responseService->error(
                    $exception->getMessage() ?: ApiMessages::HTTP_ERROR,
                    [],
                    $exception->getStatusCode()
                )
            );
            return;
        }

        // ✅ 5. Unknown exceptions (VERY IMPORTANT)
        $message = $this->debug
            ? $exception->getMessage()
            : ApiMessages::INTERNAL_ERROR;

        $extra = $this->debug
            ? [
                'exception' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]
            : [];

        $event->setResponse(
            $this->responseService->error($message, $extra, Response::HTTP_INTERNAL_SERVER_ERROR)
        );
    }

    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            $errors[$field][] = $violation->getMessage();
        }
        return $errors;
    }
   
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }
}