<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Enum\CityAccessScope;
use App\Enum\PlatformRole;
use OpenApi\Attributes as OA;

/**
 * SessionUserResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Description complète de l'utilisateur qui vient de s'authentifier.
 *
 * L'authentification de ce projet est stateful : le « jeton » est le cookie
 * de session `HIMMOMPA`, illisible par le JavaScript de la page. Il ne peut
 * donc pas transporter d'information, et c'est volontaire : un cookie
 * `HttpOnly` ne peut pas être dérobé par un script injecté, contrairement à
 * un jeton stocké en localStorage.
 *
 * L'information d'identité et de droits est donc renvoyée dans le corps de
 * la réponse. Le client n'a plus besoin d'appeler `/api/auth/me` juste pour
 * savoir qui il est et ce qu'il peut faire.
 *
 * Contenu, du plus stable au plus volatil :
 *   - identité : UUID, email, nom ;
 *   - rôle plateforme : `super_admin` ou null ;
 *   - rôles métier, par Organization : c'est le seul endroit où se joue
 *     l'isolation multi-tenant, d'où sa présence explicite ;
 *   - villes accessibles : sans effet pour un compte non `ADMIN_VILLE`.
 *
 * Ces données restent indicatives côté client : seule l'API autorise, via
 * `SecurityService`. Un client qui altère sa copie locale ne s'octroie rien.
 */
#[OA\Schema(
    title: 'SessionUserResponse',
    description: 'Utilisateur authentifié, avec ses rôles par Organization et ses villes accessibles.'
)]
final readonly class SessionUserResponse implements \JsonSerializable
{
    /**
     * @param list<SessionOrganizationMembership> $organizations rôles métier, un par Organization
     * @param list<SessionCityAccess>             $cities       villes assignées (rôle ADMIN_VILLE)
     */
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'utilisateur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $uuid,

        #[OA\Property(description: 'Adresse email de connexion', format: 'email', example: 'jean.kasereka@example.com')]
        public string $email,

        #[OA\Property(description: 'Nom complet', example: 'Jean Kasereka')]
        public string $fullName,

        #[OA\Property(description: 'Numéro de téléphone', example: '+243990000000', nullable: true)]
        public ?string $phone,

        #[OA\Property(description: 'URL de la photo de profil', nullable: true, example: '/uploads/users/jean.jpg')]
        public ?string $profilePhoto,

        #[OA\Property(
            description: 'Rôle global sur la plateforme. `null` pour un utilisateur métier : ses droits sont alors uniquement ceux de ses Organizations.',
            type: 'string',
            nullable: true,
            enum: PlatformRole::class,
            example: PlatformRole::SUPER_ADMIN
        )]
        public ?PlatformRole $platformRole,

        #[OA\Property(
            description: 'Rôles globaux effectifs au niveau Symfony (voter d\'autorisation)',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['ROLE_USER', 'ROLE_SUPER_ADMIN']
        )]
        public array $roles,

        #[OA\Property(description: 'Rôles métier du compte, un par Organization dont il est membre')]
        public array $organizations,

        #[OA\Property(description: 'Villes assignées au compte. Vide hors rôle ADMIN_VILLE ; se combiner avec `cityScope` pour lever l\'ambiguïté.')]
        public array $cities,

        #[OA\Property(
            description: 'Mode de résolution du périmètre villes. `platform` = toutes les villes actives, `assigned` = seules celles de `cities`, `none` = aucune.',
            type: 'string',
            enum: CityAccessScope::class,
            example: CityAccessScope::ASSIGNED
        )]
        public CityAccessScope $cityScope,

        #[OA\Property(description: 'État du compte', example: true)]
        public bool $isActive,

        #[OA\Property(description: 'Horodatage de la dernière connexion', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $lastLoginAt,
    ) {
    }

    /**
     * Sérialisation explicite pour `json_encode`.
     *
     * `JsonResponse` passe par `json_encode`, pas par le Serializer
     * Symfony. Sans cette méthode, un `DateTimeImmutable` se sérialise en
     * `{"date":"2026-09-28 07:41:01","timezone_type":3,...}` — une fuite de
     * structure interne de PHP dans un contrat d'API, que Swagger affiche
     * comme un objet et non comme une date.
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'fullName' => $this->fullName,
            'phone' => $this->phone,
            'profilePhoto' => $this->profilePhoto,
            'platformRole' => $this->platformRole?->value,
            'roles' => $this->roles,
            'organizations' => $this->organizations,
            'cities' => $this->cities,
            'cityScope' => $this->cityScope->value,
            'isActive' => $this->isActive,
            'lastLoginAt' => $this->lastLoginAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
