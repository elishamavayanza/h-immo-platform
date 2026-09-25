<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Response\HttpErrorResponsePayload;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * ApiExceptionListener
 *
 * Intercepte toutes les exceptions non capturées dans l'application
 * et produit une JsonResponse au format HttpErrorResponsePayload.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class ApiExceptionListener
{
    public function __construct(
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private KernelInterface $kernel,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Ne traiter que les requêtes API (ou acceptant du JSON)
        if (!str_starts_with($request->getPathInfo(), '/api') && $request->getRequestFormat() !== 'json') {
            return;
        }

        $exception = $event->getThrowable();

        // Détermination du code HTTP et du libellé d'erreur
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $statusText = Response::$statusTexts[$statusCode] ?? 'HTTP Error';
            $message = $exception->getMessage();
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $statusText = 'Internal Server Error';

            // En production, masquer le message d'erreur interne brute pour des raisons de sécurité
            $message = $this->kernel->getEnvironment() === 'dev'
                ? $exception->getMessage()
                : 'Une erreur interne est survenue. Veuillez contacter l\'administrateur.';
        }

        // Journalisation de l'exception
        if ($statusCode >= 500) {
            $this->logger->critical($exception->getMessage(), [
                'exception' => $exception,
                'path' => $request->getPathInfo(),
            ]);
        } else {
            $this->logger->warning($exception->getMessage(), [
                'exception' => $exception,
                'path' => $request->getPathInfo(),
            ]);
        }

        // Construction des détails supplémentaires (uniquement en environnement de développement)
        $details = null;
        if ($this->kernel->getEnvironment() === 'dev') {
            $details = [
                'exceptionClass' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        // Instanciation du DTO de réponse HTTP
        $payload = new HttpErrorResponsePayload(
            status: $statusCode,
            error: $statusText,
            message: $message,
            details: $details,
        );

        // Sérialisation JSON
        $json = $this->serializer->serialize($payload, 'json');

        // Remplacement de la réponse HTTP par notre JsonResponse
        $response = new JsonResponse($json, $statusCode, [], true);
        $event->setResponse($response);
    }
}
