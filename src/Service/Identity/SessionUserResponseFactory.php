<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Response\Identity\SessionCityAccess;
use App\Dto\Response\Identity\SessionOrganizationMembership;
use App\Dto\Response\Identity\SessionUserResponse;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\CityAccessScope;
use App\Enum\CityStatus;
use App\Enum\OrganizationRole;
use App\Enum\PlatformRole;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Property\CityRepository;

/**
 * SessionUserResponseFactory
 *
 * Assemble la description d'un utilisateur authentifié.
 *
 * Why a factory and not a `fromEntity()` on the DTO: les rôles métier et
 * les villes assignées vivent dans des tables pivots, pas dans l'entité
 * `User`. Un `fromEntity(User $user)` ne pourrait pas y accéder sans
 * injection, et l'assemblage serait alors impossible à tester hors
 * contexte HTTP.
 *
 * Les repositories sont injectés, et non résolus via
 * `EntityManager::getRepository()` : les entités ne déclarent pas
 * l'attribut `repositoryClass`, et cette méthode attend une classe
 * d'ENTITÉ, pas un repository. Lui passer `OrganizationUserRepository::class`
 * levait une `MappingException` en production. L'injection ne coûte aucune
 * requête au démarrage : un repository n'interroge la base que lorsqu'on
 * appelle une méthode.
 */
final class SessionUserResponseFactory
{
    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly CityRepository $cityRepository,
    ) {
    }

    public function create(User $user): SessionUserResponse
    {
        // Résolu une fois et réutilisé : `isAdminVille()` parcourt les mêmes
        // appartenances que `buildOrganizations()`. Deux appels à
        // `findByUser()` déclencheraient deux requêtes identiques pour
        // construire la même réponse.
        $memberships = $this->organizationUserRepository->findByUser($user);
        $isAdminVille = $this->hasRole($memberships, OrganizationRole::ADMIN_VILLE);

        return new SessionUserResponse(
            uuid: (string) $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            phone: $user->getPhone(),
            profilePhoto: $user->getProfilePhoto(),
            platformRole: $user->getPlatformRole(),
            roles: $user->getRoles(),
            organizations: $this->buildOrganizations($memberships),
            cities: $this->buildCities($user, $isAdminVille),
            cityScope: $this->resolveCityScope($user, $isAdminVille),
            isActive: $user->isActive(),
            lastLoginAt: $user->getLastLoginAt(),
        );
    }

    /**
     * Un rôle métier par Organization dont l'utilisateur est membre.
     *
     * Trier rend la réponse stable entre deux appels : sans tri, l'ordre
     * suit celui que MySQL renvoie, qui n'est pas garanti, et les listes du
     * client se réordonnent à chaque rechargement.
     *
     * @param list<OrganizationUser> $memberships
     *
     * @return list<SessionOrganizationMembership>
     */
    private function buildOrganizations(array $memberships): array
    {
        $result = [];

        foreach ($memberships as $membership) {
            $organization = $membership->getOrganization();

            $result[] = new SessionOrganizationMembership(
                uuid: (string) $organization->getUuid(),
                code: $organization->getCode(),
                name: $organization->getName(),
                role: $membership->getRole(),
            );
        }

        usort(
            $result,
            static fn (SessionOrganizationMembership $a, SessionOrganizationMembership $b): int => $a->code <=> $b->code,
        );

        return $result;
    }

    /**
     * Villes explicitement assignées au compte.
     *
     * Seules les villes `ACTIVE` sont listées : une ville `INACTIVE`
     * produirait une entrée que l'API refuse d'exploiter, et le client
     * proposerait un filtre qui mène à un refus.
     *
     * @return list<SessionCityAccess>
     */
    private function buildCities(User $user, bool $isAdminVille): array
    {
        if (!$isAdminVille) {
            return [];
        }

        $result = [];

        foreach ($this->cityRepository->findAssignedToUser($user) as $city) {
            if ($city->getStatus() !== CityStatus::ACTIVE) {
                continue;
            }

            $result[] = new SessionCityAccess(
                uuid: (string) $city->getUuid(),
                name: $city->getName(),
                province: $city->getProvince(),
                status: $city->getStatus(),
            );
        }

        usort(
            $result,
            static fn (SessionCityAccess $a, SessionCityAccess $b): int => $a->name <=> $b->name,
        );

        return $result;
    }

    /**
     * Levée de l'ambiguïté « liste vide ».
     *
     * Un SUPER_ADMIN n'a pas de villes assignées mais tous les accès : sa
     * liste `cities` est donc vide, et c'est `cityScope` qui l'exprime. Un
     * client qui ne lirait que `cities` en déduirait à tort qu'il est
     * cantonné à rien.
     */
    private function resolveCityScope(User $user, bool $isAdminVille): CityAccessScope
    {
        if ($user->getPlatformRole() === PlatformRole::SUPER_ADMIN) {
            return CityAccessScope::PLATFORM;
        }

        return $isAdminVille ? CityAccessScope::ASSIGNED : CityAccessScope::NONE;
    }

    /**
     * Le rôle `ADMIN_VILLE` est porté par une table pivot : il n'existe
     * dans aucune colonne de l'entité `User`.
     *
     * @param list<OrganizationUser> $memberships
     */
    private function hasRole(array $memberships, OrganizationRole $role): bool
    {
        foreach ($memberships as $membership) {
            if ($membership->getRole() === $role) {
                return true;
            }
        }

        return false;
    }


}
