<?php

declare(strict_types=1);

namespace App\Service\System;

/**
 * DateTimeService
 *
 * Source unique de vérité pour l'horloge, le fuseau de stockage et la
 * présentation des dates et heures. Aucun autre service, repository,
 * contrôleur ou commande ne doit construire lui-même un `DateTimeInterface`
 * pour « maintenant » : il délègue à ce service.
 *
 * Règles portées par ce service (et documentées dans AGENTS.md) :
 *
 * 1. Toutes les dates sont des `DateTimeImmutable`. Aucun `DateTime` mutable
 *    ne doit entrer dans `src/` : une date qui voyage ne doit pas pouvoir être
 *    modifiée par effet de bord.
 * 2. Le stockage (colonnes DATE / DATETIME) et les calculs internes sont
 *    effectués en UTC, quel que soit le fuseau du serveur ou de l'utilisateur.
 *    `STORAGE_TIMEZONE` est la valeur de référence : épingler explicitement le
 *    fuseau sur chaque construction évite qu'un changement de
 *    `date.timezone` ne change silencieusement les échéances, les bornes
 *    d'année des rapports ou les expirations de jetons.
 * 3. La conversion de fuseau est exclusivement une opération de
 *    présentation, réalisée par `toTimezone()` / `format()`. Elle n'est
 *    donc jamais appliquée côté entité ni dans un calcul métier : les
 *    entités ne contiennent aucune conversion de fuseau.
 * 4. Le parsing d'une date fournie par le client se fait via
 *    `parseDate()` / `parseDateTime()`, qui refusent strictement une valeur
 *    invalide en renvoyant `null` (au lieu d'une `Exception` inattendue —
 *    un `new \DateTimeImmutable($raw)` sur une entrée malformée levait une
 *    500 dans AuditLogController).
 *
 * Le constructeur est vide : l'injection se limite au type de la classe et ne
 * coûte aucune dépendance. Les méthodes sont irréductibles — pas de logique
 * métier ici — et le service est `final readonly` pour être inerte et sûr à
 * partager.
 */
final readonly class DateTimeService
{
    /**
     * Fuseau de stockage et de calcul : toutes les colonnes DATETIME et tous
     * les instants internes sont exprimés ici.
     */
    public const STORAGE_TIMEZONE = 'UTC';

    /**
     * Fuseau de présentation par défaut du produit (RDC). Sert uniquement
     * aux rendus destinés à un humain (PDF, e-mails, affichage) ; les
     * réponses API conservent leur valeur UTC : convertir un instant en
     * `PRESENTATION_TIMEZONE` est une décision explicite de l'appelant.
     */
    public const PRESENTATION_TIMEZONE = 'Africa/Kinshasa';

    /**
     * Formats acceptés par `parseDateTime()`, dans l'ordre de préférence.
     * `DATE_ATOM` est le contrat OpenAPI (`format: date-time`) ; les deux
     * autres tolérent les clients qui émettent un séparateur espace ou
     * omettent le fuseau.
     */
    private const DATETIME_INPUT_FORMATS = [
        \DateTimeInterface::ATOM,
        'Y-m-d\TH:i:s',
        'Y-m-d H:i:s',
    ];

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::STORAGE_TIMEZONE));
    }

    /**
     * Minuit UTC du jour courant : l'équivalent sûr de
     * `new \DateTimeImmutable('today')`, indépendant du fuseau par défaut
     * déclaré par `date.timezone`.
     */
    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::STORAGE_TIMEZONE));
    }

    /**
     * Premier janvier de l'année courante, à minuit UTC. Remplace le littéral
     * `'first day of January this year'` (résolu dans le fuseau par défaut,
     * donc dépendant de la configuration du serveur).
     */
    public function startOfCurrentYear(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('first day of January this year', new \DateTimeZone(self::STORAGE_TIMEZONE));
    }

    /**
     * 31 décembre de l'année courante, à minuit UTC — la sémantique exacte
     * du littéral `'last day of December this year'` qu'il remplace : cette
     * chaîne résout à minuit, pas à 23:59:59. Toute requête « année en
     * cours » qui doit inclure la journée du 31 décembre doit donc combiner
     * cette borne avec un comparateur `<` sur le premier janvier suivant,
     * ou expliciter sa borne haute.
     */
    public function endOfCurrentYear(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('last day of December this year', new \DateTimeZone(self::STORAGE_TIMEZONE));
    }

    /**
     * Parsing strict d'une date `Y-m-d` fournie par le client.
     *
     * Sans disjoncteur, `DateTimeImmutable::createFromFormat()` accepte des
     * valeurs qui « débordent » (2026-13-45 devient une date normalisée au
     * lieu d'être refusée), laisse la part horaire vide prendre l'heure
     * courante du serveur, et conserve le fuseau renseigné dans la chaîne.
     * Ici : format `!` (champs non fournis à zéro), contrôle de
     * `getLastErrors()` et épinglage UTC. Une entrée invalide renvoie
     * `null`, jamais une exception : l'appelant choisit de l'ignorer ou de
     * la refuser.
     */
    public function parseDate(string $value): ?\DateTimeImmutable
    {
        // `createFromFormat()` accepte sans warning une date non paddée
        // (`2026-3-5`), ce qui violerait le contrat `Y-m-d` documenté : on
        // refuse d'abord toute forme qui n'est pas exactement 4-2-2 chiffres.
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone(self::STORAGE_TIMEZONE));

        return $this->isValidParse($parsed) ? $parsed : null;
    }

    /**
     * Parsing strict d'un jalon date-heure (RFC3339 de préférence, formes
     * ISO courantes en secours) fourni par le client. Le résultat est
     * épinglé en UTC : un instant exprimé `+02:00` est converti, pas conservé.
     * Une entrée invalide renvoie `null`.
     */
    public function parseDateTime(string $value): ?\DateTimeImmutable
    {
        foreach (self::DATETIME_INPUT_FORMATS as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value, new \DateTimeZone(self::STORAGE_TIMEZONE));
            if (!$this->isValidParse($parsed)) {
                continue;
            }

            return $parsed->setTimezone(new \DateTimeZone(self::STORAGE_TIMEZONE));
        }

        return null;
    }

    /**
     * `createFromFormat()` renvoie `false` OU une date portant des warnings
     * (`2026-02-30`, mois de 13) : les deux cas sont une entrée refusée.
     */
    private function isValidParse(\DateTimeImmutable|false $parsed): bool
    {
        if (false === $parsed) {
            return false;
        }

        $errors = \DateTimeImmutable::getLastErrors();

        return !(is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0));
    }

    /**
     * Conversion de présentation : renvoie une copie de l'instant dans le
     * fuseau demandé (le fuseau de présentation par défaut du produit). La
     * valeur absolue ne change pas ; seuls le fuseau et l'affichage
     * changent. N'appeler que pour rendre un instant à un humain.
     */
    public function toTimezone(\DateTimeImmutable $date, string $timezone = self::PRESENTATION_TIMEZONE): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone($timezone));
    }

    /**
     * Mise en forme d'un instant, potentiellement dans un fuseau de
     * présentation. Par défaut le fuseau de stockage est conservé (sortie
     * identique à `$date->format($format)`) : la conversion n'est appliquée
     * que si l'appelant la demande explicitement.
     */
    public function format(\DateTimeImmutable $date, string $format, string $timezone = self::STORAGE_TIMEZONE): string
    {
        return $this->toTimezone($date, $timezone)->format($format);
    }
}