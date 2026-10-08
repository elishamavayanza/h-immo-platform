<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Entity\Identity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * UserNotificationService
 *
 * Package : Identity & Access — Notification
 *
 * Envoie les emails de notification liés au cycle de vie d'un compte
 * utilisateur (suspension par l'administration de la plateforme).
 *
 * Même règle que `OrganizationNotificationService` : un échec du mailer ne
 * remonte JAMAIS une exception. La suspension est tracée en base (`isActive`
 * + audit) avant l'envoi — si le mailer tombe, le compte est déjà désactivé
 * et l'email peut être renvoyé. L'échec est journalisé et remonté sous forme
 * de liste d'adresses en échec, que l'appelant traduit en `warning` dans le
 * Feedback.
 */
final readonly class UserNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Notifie un utilisateur de la suspension de son propre compte.
     *
     * @return list<string> Adresses en échec (le mailer a rejeté le message)
     */
    public function notifyAccountSuspended(User $user, ?string $reason): array
    {
        if (($user->getSettings()['emailNotifications'] ?? true) === false) {
            return [];
        }

        $message = (new Email())
            ->from($this->params->get('mailer.from', 'noreply@h-immo.local'))
            ->to($user->getEmail())
            ->subject('Suspension de votre compte - H-Immo');

        $logoTag = $this->attachEmailLogo($message);

        $message
            ->html($this->buildSuspensionHtml($user->getFullName(), $reason, $logoTag))
            ->text($this->buildSuspensionText($user->getFullName(), $reason));

        try {
            $this->mailer->send($message);

            return [];
        } catch (\Throwable $exception) {
            // Ne journalise aucune donnée personnelle : uniquement les
            // références techniques utiles au diagnostic réseau.
            $this->logger->error('Échec de l\'envoi de l\'email de suspension de compte.', [
                'userId' => $user->getId(),
                'exception' => $exception,
            ]);

            return [$user->getEmail()];
        }
    }

    /**
     * Joint le logo au message et renvoie la balise `<img>` à injecter.
     *
     * Le logo est embarqué en pièce jointe interne (`cid:`) plutôt que
     * référencé par URL : aucun client ne dépend du domaine de l'API et
     * l'image reste affichée même si les images distantes sont bloquées.
     * Un logo absent ne doit jamais faire échouer l'envoi : l'email part
     * sans image, le texte suffit à l'information.
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

    private function buildSuspensionHtml(string $fullName, ?string $reason, string $logoTag): string
    {
        $name = htmlspecialchars($fullName, ENT_QUOTES);

        if ($reason === null || trim($reason) === '') {
            $motifBlock = '';
        } else {
            $motif = nl2br(htmlspecialchars($reason, ENT_QUOTES));
            $motifBlock = <<<HTML
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#202326;border:1px solid #262A2E;border-radius:12px;margin:0 0 16px 0;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 4px 0;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#6B7077;">Motif de la suspension</p>
                                        <p style="margin:0;font-size:15px;color:#F5F5F5;">{$motif}</p>
                                    </td>
                                </tr>
                            </table>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspension de votre compte</title>
</head>
<body style="margin:0;padding:0;background-color:#0A0B0D;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0A0B0D">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#15171A;border:1px solid #262A2E;border-radius:16px;">
                    <tr>
                        <td align="center" style="padding:36px 32px 4px 32px;">
                            {$logoTag}
                            <h1 style="margin:0;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;color:#F5F5F5;text-align:center;line-height:1.35;">Suspension de votre compte</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#A5A8AD;">
                            <p style="margin:0 0 16px 0;">Bonjour {$name},</p>
                            <p style="margin:0 0 16px 0;">Votre compte sur la plateforme H-Immo a été <strong style="color:#F5F5F5;">suspendu</strong>.</p>
                            <p style="margin:0 0 16px 0;">Vous ne pourrez plus vous connecter tant que la suspension n'est pas levée par l'administrateur de la plateforme.</p>
                            {$motifBlock}
                            <p style="margin:0;font-size:13px;color:#6B7077;">Pour toute question, contactez l'administrateur de la plateforme.</p>
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

    private function buildSuspensionText(string $fullName, ?string $reason): string
    {
        $motif = ($reason === null || trim($reason) === '')
            ? "Aucun motif n'a été communiqué."
            : $reason;

        return <<<TXT
Suspension de votre compte - H-Immo

Bonjour {$fullName},

Votre compte sur la plateforme H-Immo a été suspendu.
Vous ne pourrez plus vous connecter tant que la suspension n'est pas levée par l'administrateur de la plateforme.

Motif de la suspension :
{$motif}

Pour toute question, contactez l'administrateur de la plateforme.
TXT;
    }
}
