<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Dto\Request\System\ClientErrorReportRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/** Envoi best-effort des erreurs inattendues à l’équipe, sans données métier. */
final readonly class ErrorReportService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_ERROR_REPORT_RECIPIENT)%')]
        private string $recipient,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private string $sender,
        #[Autowire(service: 'limiter.server_error_reports')]
        private RateLimiterFactory $serverErrorLimiter,
    ) {
    }

    public function reportServerException(Throwable $exception, Request $request, string $errorId, int $status): void
    {
        if (!$this->isEnabled()) return;

        $route = (string) $request->attributes->get('_route', $request->getPathInfo());
        $fingerprint = hash('sha256', $exception::class.'|'.$route.'|'.$status);
        if (!$this->serverErrorLimiter->create($fingerprint)->consume(1)->isAccepted()) return;

        $body = implode("\n", [
            'Erreur serveur inattendue',
            'Identifiant : '.$errorId,
            'HTTP : '.$status,
            'Route : '.$this->scrub($route, 500),
            'Méthode : '.$request->getMethod(),
            'Exception : '.$exception::class,
            'Message : '.$this->scrub($exception->getMessage(), 1200),
            '',
            'Actions récentes (aucune valeur de formulaire) :',
            $this->scrub(mb_substr($request->headers->get('X-UI-Action-Trail', '(aucune interaction récente capturée)'), 0, 1800), 1800),
            '',
            'Source (pile PHP) :',
            $this->scrub($exception->getTraceAsString(), 9000),
        ]);

        $this->send('Soft-IMMO · erreur serveur '.$errorId, $body, $errorId);
    }

    public function reportClientError(ClientErrorReportRequest $report, UserInterface $user, Request $request): string
    {
        $errorId = bin2hex(random_bytes(8));
        $body = implode("\n", [
            'Erreur inattendue dans l’interface Soft-IMMO',
            'Identifiant : '.$errorId,
            'Type : '.$report->kind,
            'Compte (identifiant interne) : '.$this->scrub($user->getUserIdentifier(), 180),
            'Route : '.$this->scrub($report->route ?? $request->getPathInfo(), 500),
            'Horodatage navigateur : '.$this->scrub($report->occurredAt ?? '', 40),
            'Navigateur : '.$this->scrub($report->userAgent ?? '', 300),
            'Message : '.$this->scrub($report->message, 1200),
            'Source précise : '.$this->scrub($report->sourceLocation ?? '(non fournie par le navigateur)', 500),
            '',
            'Actions récentes (aucune valeur de formulaire) :',
            $this->scrub($report->actionTrail ?? '(aucune interaction récente capturée)', 1800),
            '',
            'Source (pile JavaScript) :',
            $this->scrub($report->stack ?? '(aucune pile fournie)', 7000),
            '',
            'Pile des composants React :',
            $this->scrub($report->componentStack ?? '(non applicable)', 4000),
            '',
            'Aucune valeur de formulaire, aucun jeton et aucun corps de requête ne sont inclus.',
        ]);

        $this->send('Soft-IMMO · erreur interface '.$errorId, $body, $errorId);

        return $errorId;
    }

    private function isEnabled(): bool
    {
        return trim($this->recipient) !== '' && trim($this->sender) !== '';
    }

    private function send(string $subject, string $body, string $errorId): void
    {
        if (!$this->isEnabled()) return;

        try {
            $this->mailer->send((new Email())
                ->from($this->sender)
                ->to($this->recipient)
                ->subject($subject)
                ->text($body));
        } catch (Throwable $mailError) {
            // Le rapport ne doit jamais transformer une opération métier en panne.
            $this->logger->error('Échec de livraison d’un rapport de débogage.', [
                'error_id' => $errorId,
                'exception_class' => $mailError::class,
            ]);
        }
    }

    private function scrub(string $value, int $maxLength): string
    {
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email masqué]', $value) ?? $value;
        $value = preg_replace('/\bBearer\s+[^\s]+/i', 'Bearer [jeton masqué]', $value) ?? $value;
        $value = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', '[jeton masqué]', $value) ?? $value;
        $value = preg_replace('/\b(password|passwd|token|secret|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[valeur masquée]', $value) ?? $value;
        $value = preg_replace('/\b\+?\d[\d ()-]{7,}\d\b/', '[numéro masqué]', $value) ?? $value;

        return mb_substr($value, 0, $maxLength);
    }
}
