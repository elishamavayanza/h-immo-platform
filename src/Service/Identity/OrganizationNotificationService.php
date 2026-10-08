<?php

declare(strict_types=1);

namespace App\Service\Identity;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * OrganizationNotificationService
 *
 * Package : Identity & Access — Notification
 *
 * Envoie les emails de notification aux membres d'une Organization.
 *
 * Règle : un échec du mailer ne doit JAMAIS faire remonter une exception.
 * La suspension est une opération d'administration tracée en base
 * (`status` + audit) avant l'envoi : si le mailer tombe, le tenant est déjà
 * suspendu et l'email peut être renvoyé. L'échec est journalisé et remonté
 * sous forme de liste d'emails en échec, que l'appelant traduit en
 * `warning` dans le Feedback (verdict « persistance » et verdict
 * « notification » restent séparés).
 */
final readonly class OrganizationNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Notifie les membres d'une organisation de sa suspension.
     *
     * @param list<string> $emails Adresses des membres abonnés (dédupliquées par l'appelant)
     *
     * @return list<string> Adresses en échec (le mailer a rejeté le message)
     */
    public function notifySuspension(string $organizationName, string $reason, array $emails): array
    {
        $failed = [];

        foreach ($emails as $email) {
            $message = (new Email())
                ->from($this->params->get('mailer.from', 'noreply@h-immo.local'))
                ->to($email)
                ->subject('Suspension de votre organisation - H-Immo');

            $logoTag = $this->attachEmailLogo($message);

            $message
                ->html($this->buildSuspensionHtml($organizationName, $reason, $logoTag))
                ->text($this->buildSuspensionText($organizationName, $reason));

            try {
                $this->mailer->send($message);
            } catch (\Throwable $exception) {
                // Ne journalise aucune donnée personnelle : uniquement les
                // références techniques utiles au diagnostic réseau.
                $this->logger->error('Échec de l\'envoi de l\'email de suspension d\'organisation.', [
                    'organizationName' => $organizationName,
                    'exception' => $exception,
                ]);

                $failed[] = $email;
            }
        }

        return $failed;
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

    private function buildSuspensionHtml(string $organizationName, string $reason, string $logoTag): string
    {
        $organization = htmlspecialchars($organizationName, ENT_QUOTES);
        $motif = nl2br(htmlspecialchars($reason, ENT_QUOTES));

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspension de votre organisation</title>
</head>
<body style="margin:0;padding:0;background-color:#0A0B0D;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0A0B0D">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#15171A;border:1px solid #262A2E;border-radius:16px;">
                    <tr>
                        <td align="center" style="padding:36px 32px 4px 32px;">
                            {$logoTag}
                            <h1 style="margin:0;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;color:#F5F5F5;text-align:center;line-height:1.35;">Suspension de votre organisation</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#A5A8AD;">
                            <p style="margin:0 0 16px 0;">Bonjour,</p>
                            <p style="margin:0 0 16px 0;">L'accès de votre organisation <strong style="color:#F5F5F5;">{$organization}</strong> a été suspendu sur la plateforme.</p>
                            <p style="margin:0 0 16px 0;">Vous ne pourrez plus vous connecter tant que la suspension n'est pas levée.</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#202326;border:1px solid #262A2E;border-radius:12px;margin:0 0 16px 0;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 4px 0;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#6B7077;">Motif de la suspension</p>
                                        <p style="margin:0;font-size:15px;color:#F5F5F5;">{$motif}</p>
                                    </td>
                                </tr>
                            </table>
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

    private function buildSuspensionText(string $organizationName, string $reason): string
    {
        return <<<TXT
Suspension de votre organisation - H-Immo

Bonjour,

L'accès de votre organisation « {$organizationName} » a été suspendu sur la plateforme.
Vous ne pourrez plus vous connecter tant que la suspension n'est pas levée.

Motif de la suspension :
{$reason}

Pour toute question, contactez l'administrateur de la plateforme.
TXT;
    }
}