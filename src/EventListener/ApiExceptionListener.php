<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Response\HttpErrorResponsePayload;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\NoResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\SecurityException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

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
        private ?Security $security = null,
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

        [$statusCode, $statusText, $violations] = $this->resolveError($exception);

        // Le message d'une erreur interne ne doit jamais fuir en
        // production : il révèle l'état de la base et l'arborescence du
        // code. Les erreurs applicatives, elles, sont déjà écrites pour
        // être lues par l'utilisateur.
        $message = $statusCode >= 500 && $this->kernel->getEnvironment() !== 'dev'
            ? 'Une erreur interne est survenue. Veuillez contacter l\'administrateur.'
            : $exception->getMessage();

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

        // Construction des détails supplémentaires (uniquement en environnement
        // de développement, et jamais pour un échec de sécurité).
        //
        // `AccessDeniedException` n'hérite PAS de `SecurityException` : il
        // l'implémente seulement via `ExceptionInterface` et dérive
        // directement de `RuntimeException`. Les deux doivent donc être
        // testés, sinon un refus d'accès continue de renvoyer une trace
        // d'exécution complète.
        //
        // Un refus d'accès est un événement normal d'une API exposée sur
        // Internet : le détail n'intéresse pas l'appelant, et publier
        // l'arborescence du serveur n'aide que l'attaquant.
        $isSecurityFailure = $exception instanceof SecurityException
            || $exception instanceof SecurityAccessDeniedException;

        $details = null;
        if ($this->kernel->getEnvironment() === 'dev' && !$isSecurityFailure) {
            $details = [
                'exceptionClass' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        // Les violations de validation sont une information utile même en
        // production : elles décrivent ce que le client doit corriger.
        if ($violations !== null) {
            $details = array_merge($details ?? [], ['violations' => $violations]);
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

        // Un 401 doit indiquer comment s'authentifier. Sans cet en-tête,
        // un client d'API ne peut pas distinguer « présente un jeton » de
        // « tes identifiants sont mauvais » et ne sait pas quoi corriger.
        //
        // Ce listener intercepte l'AccessDeniedException AVANT l'entry
        // point du pare-feu (priorité 10 contre -64), qui n'est donc jamais
        // appelé : l'en-tête est posé ici, au seul endroit qui produit
        // effectivement la réponse.
        if ($statusCode === Response::HTTP_UNAUTHORIZED) {
            $response->headers->set('WWW-Authenticate', 'Bearer');
        }

        $event->setResponse($response);
    }

    /**
     * Traduit une exception en triplet (code HTTP, libellé, violations).
     *
     * Sans ce mapping, une exception métier qui n'hérite pas de
     * `HttpExceptionInterface` — cas de la plupart des exceptions Doctrine
     * et de celles du validateur — serait renvoyée en 500. Un champ
     * manquant se traduisait alors par « erreur serveur » au lieu de
     * « 422, corrige ta requête », et une violation d'unicité en base par
     * « 500 » au lieu de « 409, conflit ».
     *
     * @return array{0: int, 1: string, 2: array<string, list<string>>|null}
     */
    private function resolveError(\Throwable $exception): array
    {
        // Le validateur Symfony : 422, avec le détail des champs fautifs.
        if ($exception instanceof ValidationFailedException) {
            $violations = [];

            foreach ($exception->getViolations() as $violation) {
                // Les violations de classe (UniqueEntity, callbacks de
                // formulaire) n'ont pas de chemin de champ et remontent des
                // messages qui dupliquent ceux déjà portés par l'entité.
                if ($violation->getPropertyPath() === '') {
                    continue;
                }

                // Un même champ peut être fautif pour plusieurs raisons
                // (vide ET mal formaté, par exemple). Les regrouper en
                // liste évite d'écraser silencieusement un message au
                // profit d'un autre.
                $violations[$violation->getPropertyPath()][] = (string) $violation->getMessage();
            }

            return [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                Response::$statusTexts[Response::HTTP_UNPROCESSABLE_ENTITY] ?? 'Unprocessable Entity',
                $violations === [] ? null : $violations,
            ];
        }

        // Doctrine : rien trouvé → 404. Cette situation arrive réellement
        // quand un UUID est bien formé mais absent de la base.
        if ($exception instanceof EntityNotFoundException || $exception instanceof NoResultException) {
            return [Response::HTTP_NOT_FOUND, Response::$statusTexts[404] ?? 'Not Found', null];
        }

        // Doctrine : l'écriture viole une contrainte d'unicité, ou la
        // version lue a été modifiée entre-temps → 409, l'état demandé
        // entre en conflit avec l'état actuel.
        if ($exception instanceof UniqueConstraintViolationException
            || $exception instanceof OptimisticLockException
        ) {
            return [Response::HTTP_CONFLICT, Response::$statusTexts[409] ?? 'Conflict', null];
        }

        // Sécurité : ce listener s'exécute AVANT l'`ExceptionListener` du
        // firewall (priorité -64), car 10 > -64. Sans ce cas, une requête
        // anonyme refusée par `access_control` produirait un 500 au lieu
        // d'un 401 : `AccessDeniedException` de Symfony n'implémente pas
        // `HttpExceptionInterface` et retombait donc sur le cas 500.
        //
        // Symfony lève la même exception pour « pas encore authentifié »
        // (401) et « authentifié mais interdit » (403) : c'est la présence
        // d'un utilisateur qui distingue les deux, pas le type d'exception.
        // Trop de tentatives : le client doit pouvoir distinguer « mot de
        // passe erroné » de « temporairement bloqué », sans quoi il ne
        // peut pas savoir s'il faut réessayer plus tard. Cette classe
        // hérite de `AuthenticationException`, le test doit donc précéder.
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return [Response::HTTP_TOO_MANY_REQUESTS, 'Too Many Requests', null];
        }

        if ($exception instanceof AuthenticationException) {
            return [Response::HTTP_UNAUTHORIZED, Response::$statusTexts[401] ?? 'Unauthorized', null];
        }

        if ($exception instanceof SecurityAccessDeniedException) {
            $statusCode = $this->security?->getUser() === null
                ? Response::HTTP_UNAUTHORIZED
                : Response::HTTP_FORBIDDEN;

            return [
                $statusCode,
                Response::$statusTexts[$statusCode] ?? 'HTTP Error',
                null,
            ];
        }

        // Toute exception HTTP (dont `AccessDeniedException` 403 et
        // `UnauthenticatedException` 401) porte déjà son propre code.
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();

            return [
                $statusCode,
                Response::$statusTexts[$statusCode] ?? 'HTTP Error',
                null,
            ];
        }

        return [
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR] ?? 'Internal Server Error',
            null,
        ];
    }
}
