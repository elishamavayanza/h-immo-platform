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
use Symfony\Component\Mime\Part\DataPart;
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
            ->from($this->params->get('mailer.from', 'noreply@h-immo.local'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - H-Immo');

        $logoTag = $this->attachEmailLogo($email);

        $email
            ->html($this->buildResetEmailHtml($user->getFullName(), $resetUrl, $logoTag))
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

    /**
     * Joint le logo au message et renvoie la balise `<img>` à injecter.
     *
     * Le logo est embarqué en pièce jointe interne (`cid:`) plutôt que
     * référencé par URL : aucun client ne dépend du domaine de l'API et
     * l'image reste affichée même si les images distantes sont bloquées.
     * Un logo absent ne doit jamais faire échouer l'envoi : l'email part
     * sans image, le texte suffit à l'action.
     */
    private function attachEmailLogo(Email $email): string
    {
        $projectDir = $this->params->get('kernel.project_dir', '');

        try {
            $logoPart = DataPart::fromPath($projectDir.'/public/logo-email.png', 'logo-email.png')->asInline();
            $cid = $logoPart->getContentId();
            $email->addPart($logoPart);
        } catch (\Throwable $exception) {
            $this->logger->warning('Logo email introuvable, envoi sans image.', [
                'projectDir' => $projectDir,
                'exception' => $exception,
            ]);

            return '';
        }

        return '<img src="cid:'.$cid.'" alt="H-Immo" width="150" height="136" style="display:block;margin:0 auto 24px auto;border:0;outline:none;text-decoration:none;">';
    }

    private function buildResetEmailHtml(string $fullName, string $resetUrl, string $logoTag): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
</head>
<body style="margin:0;padding:0;background-color:#0A0B0D;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0A0B0D">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#15171A;border:1px solid #262A2E;border-radius:16px;">
                    <tr>
                        <td align="center" style="padding:36px 32px 4px 32px;">
                            {$logoTag}
                            <h1 style="margin:0;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;color:#F5F5F5;text-align:center;line-height:1.35;">Réinitialisation de votre mot de passe</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 8px 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:1.7;color:#A5A8AD;">
                            <p style="margin:0 0 14px 0;">Bonjour <strong style="color:#F5F5F5;font-weight:600;">{$fullName}</strong>,</p>
                            <p style="margin:0 0 14px 0;">Vous avez demandé la réinitialisation de votre mot de passe sur <strong style="color:#25BDE8;font-weight:600;">H-Immo</strong>.</p>
                            <p style="margin:0;">Cliquez sur le bouton ci-dessous pour définir votre nouveau mot de passe :</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 32px;">
                            <a href="{$resetUrl}" style="display:inline-block;background-color:#25BDE8;color:#0A0B0D;text-decoration:none;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;line-height:1;padding:15px 32px;border-radius:10px;">Réinitialiser mon mot de passe</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 8px 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:13px;line-height:1.7;color:#A5A8AD;">
                            <p style="margin:0 0 10px 0;">Ce lien expire dans <strong style="color:#F5F5F5;font-weight:600;">1 heure</strong> et ne peut être utilisé qu'une seule fois.</p>
                            <p style="margin:0;">Si vous n'avez pas fait cette demande, ignorez simplement cet email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 32px 32px;border-top:1px solid #262A2E;">
                            <p style="margin:0;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#6B7077;">
                                <strong style="color:#A5A8AD;font-weight:600;">Équipe H-Immo</strong><br>
                                Ce message est automatique, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function buildResetEmailText(string $fullName, string $resetUrl): string
    {
        return <<<TEXT
Réinitialisation de votre mot de passe

Bonjour {$fullName},

Vous avez demandé la réinitialisation de votre mot de passe sur H-Immo.

Cliquez sur le lien ci-dessous pour définir votre nouveau mot de passe :
{$resetUrl}

Ce lien expire dans 1 heure et ne peut être utilisé qu'une seule fois.

Si vous n'avez pas fait cette demande, ignorez simplement cet email.

--
Équipe H-Immo
Ce message est automatique, merci de ne pas y répondre.
TEXT;
    }
}