<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Entity\Identity\PasswordResetToken;
use App\Entity\Identity\User;
use App\Repository\Identity\PasswordResetTokenRepository;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityServiceInterface;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;

/**
 * PasswordResetService
 *
 * Package : Identity & Access
 *
 * Gère le flux complet de réinitialisation de mot de passe :
 *   1. forgot-password : création d'un jeton, envoi email
 *   2. reset-password  : validation jeton, hachage nouveau mot de passe
 *
 * Sécurité :
 * - Jeton SHA-256 stocké en base (jamais en clair)
 * - Jeton à usage unique, expiration 1h
 * - Invalidation des jetons précédents à chaque nouvelle demande
 * - Pas de révélation si l'email existe (réponse toujours 200)
 * - Hachage bcrypt via PasswordHasherInterface
 *
 * L'envoi d'email utilise un service d'abstraction (MailerInterface)
 * pour permettre un mock en test et une vraie implémentation en prod.
 *
 * Un échec du mailer ne doit jamais remonter en exception : le compte
 * appelant peut déjà être créé en base, et une 500 ferait croire au
 * PATRON que rien n'a été écrit. L'échec est journalisé et remonté sous
 * forme de booléen par `requestResetForNewUser()`.
 */
final readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
        private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private SecurityServiceInterface $security,
        private DateTimeService $dateTime,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Traite une demande de réinitialisation de mot de passe.
     *
     * Ne révèle PAS si l'email existe : répond toujours 200 avec le même
     * message générique pour éviter l'énumération d'emails.
     *
     * Si l'utilisateur existe et est actif :
     * - Invalide ses jetons précédents
     * - Génère un jeton sécurisé (32 octets aléatoires)
     * - Stocke le hash SHA-256 + expiration 1h
     * - Envoie l'email avec le lien de réinitialisation
     */
    public function requestReset(string $email): Feedback
    {
        // Le résultat de l'envoi est volontairement ignoré : ce point
        // d'entrée est public et ne doit rien révéler de l'existence du
        // compte ni de la santé du mailer.
        $this->dispatchResetRequest($email);

        $feedback = new Feedback();

        // Toujours même réponse pour éviter l'énumération
        $genericMessage = 'Si cette adresse existe, un lien de réinitialisation a été envoyé.';

        return $feedback
            ->setFlushDescription($genericMessage)
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Chemin interne réservé aux appelants qui viennent de créer le compte.
     *
     * Contrairement à `requestReset()`, cette méthode ne masque pas le
     * résultat de l'envoi : l'appelant a déjà écrit l'utilisateur en base
     * et doit pouvoir l'avertir que l'invitation n'est pas partie, plutôt
     * que de renvoyer une erreur qui laisserait croire à un échec total.
     *
     * @return bool true si l'email a été accepté par le mailer, false
     *              si le compte n'était pas éligible ou si l'envoi a échoué
     */
    public function requestResetForNewUser(string $email): bool
    {
        return $this->dispatchResetRequest($email);
    }

    /**
     * Crée le jeton puis tente l'envoi, en isolant les deux.
     *
     * Le jeton est invalidé puis recréé à chaque demande, donc un jeton
     * laissé valide par un échec d'envoi ne peut pas être réutilisé : la
     * demande suivante le consomme. Il expire de toute façon au bout d'une
     * heure.
     *
     * @return bool true si l'email est parti
     */
    private function dispatchResetRequest(string $email): bool
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$user->isActive() || $user->isDeleted()) {
            return false;
        }

        // Invalider les jetons précédents de cet utilisateur
        $this->tokenRepository->consumeAllForUser($user);

        // Générer jeton cryptographiquement sécurisé (32 octets = 256 bits)
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Expiration : 1 heure (épinglée en UTC via le service dates/heures)
        $expiresAt = $this->dateTime->now()->modify('+1 hour');

        // Le jeton est immuable : il se construit complet, jamais par
        // setters successifs.
        $token = new PasswordResetToken($tokenHash, $user, $expiresAt);

        $this->em->persist($token);
        $this->em->flush();

        return $this->sendResetEmail($user, $rawToken);
    }

    /**
     * Valide le jeton et définit le nouveau mot de passe.
     *
     * Vérifications :
     * - Jeton non expiré
     * - Jeton non consommé
     * - Correspondance hash SHA-256
     *
     * À succès :
     * - Hache le nouveau mot de passe (bcrypt)
     * - Marque le jeton comme consommé
     * - Invalide les autres jetons de l'utilisateur
     */
    public function resetPassword(string $rawToken, string $newPassword): Feedback
    {
        $feedback = new Feedback();

        $tokenHash = hash('sha256', $rawToken);
        $token = $this->tokenRepository->findUsableByTokenHash($tokenHash);

        if (!$token) {
            return $feedback
                ->addError('token', 'Jeton invalide, expiré ou déjà utilisé.')
                ->setErrorFlushDescription('Impossible de réinitialiser le mot de passe.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $user = $token->getUser();

        // Vérifier que l'utilisateur est toujours actif
        if (!$user->isActive() || $user->isDeleted()) {
            return $feedback
                ->setErrorFlushDescription('Ce compte n\'est plus accessible.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Hacher le nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        // Marquer le jeton comme consommé
        $token->markConsumed();

        // Invalider les autres jetons de l'utilisateur
        $this->tokenRepository->consumeAllForUser($user);

        $this->em->flush();

        return $feedback
            ->setFlushDescription('Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Envoie l'email de réinitialisation avec le lien contenant le jeton brut.
     *
     * Le transport est un service externe : un DSN invalide, un SMTP
     * indisponible ou un quota dépassé ne doit pas faire échouer
     * l'opération métier appelante. L'erreur est journalisée et traduite
     * en `false`.
     *
     * @return bool true si le mailer a accepté le message
     */
    private function sendResetEmail(User $user, string $rawToken): bool
    {
        $resetUrl = $this->params->get('app.frontend_url', 'http://localhost:3000')
            . '/reset-password?token=' . $rawToken;

        $email = (new Email())
            ->from($this->params->get('mailer.from', 'noreply@soft-immo.local'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - Soft-IMMO')
            ->html($this->buildResetEmailHtml($user->getFullName(), $resetUrl))
            ->text($this->buildResetEmailText($user->getFullName(), $resetUrl));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            // Le jeton en clair n'apparaît jamais dans le log : il est
            // à usage unique et constitue le seul moyen de prendre le compte.
            $this->logger->error('Échec de l\'envoi de l\'email de réinitialisation de mot de passe.', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
                'exception' => $exception,
            ]);

            return false;
        }

        return true;
    }

    private function buildResetEmailHtml(string $fullName, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background: #1a5276; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Réinitialisation de votre mot de passe</h2>
        <p>Bonjour <strong>{$fullName}</strong>,</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe sur <strong>Soft-IMMO</strong>.</p>
        <p>Cliquez sur le bouton ci-dessous pour définir votre nouveau mot de passe :</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$resetUrl}" class="button">Réinitialiser mon mot de passe</a>
        </p>
        <p>Ce lien expire dans <strong>1 heure</strong> et ne peut être utilisé qu'une seule fois.</p>
        <p>Si vous n'avez pas fait cette demande, ignorez simplement cet email.</p>
        <div class="footer">
            <p>Équipe Soft-IMMO</p>
            <p>Ce message est automatique, merci de ne pas y répondre.</        </p>
    </div>
</body>
</html>
HTML;
    }

    private function buildResetEmailText(string $fullName, string $resetUrl): string
    {
        return <<<TEXT
Réinitialisation de votre mot de passe

Bonjour {$fullName},

Vous avez demandé la réinitialisation de votre mot de passe sur Soft-IMMO.

Cliquez sur le lien ci-dessous pour définir votre nouveau mot de passe :
{$resetUrl}

Ce lien expire dans 1 heure et ne peut être utilisé qu'une seule fois.

Si vous n'avez pas fait cette demande, ignorez simplement cet email.

--
Équipe Soft-IMMO
Ce message est automatique, merci de ne pas y répondre.
TEXT;
    }
}