<?php

declare(strict_types=1);

namespace App\Mapper\Identity;

use App\Dto\Request\Identity\UserRequest;
use App\Dto\Response\Identity\UserResponse;
use App\Entity\Identity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * UserMapper
 *
 * Package : Identity & Access — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité User
 * et ses DTOs de requête et de réponse associés.
 */
final readonly class UserMapper
{
    /**
     * Injecte le service de hachage de mot de passe Symfony.
     * Permet la sécurisation du mot de passe lors de la mise en entité.
     */
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     *
     * @param array<int, array{organizationId: string, organizationName: string, role: string}> $memberships
     */
    public function toResponse(User $user, array $memberships = []): UserResponse
    {
        return UserResponse::fromEntity($user, $memberships);
    }


    /**
     * Mappe les données d'un DTO UserRequest vers l'entité User.
     * Assemble le nom complet et hache le mot de passe si fourni.
     *
     * Chaque champ n'est appliqué que s'il est présent dans la requête : un
     * PUT est ici traité comme un patch, faute de quoi un champ absent
     * écraserait la valeur stockée. `isActive` était précisément le contre-exemple
     * — non nullable avec un défaut `true`, il réactivait d'office un compte
     * suspendu à la moindre mise à jour de numéro de téléphone.
     */
    public function copyToEntity(UserRequest $dto, User $user): User
    {
        if ($dto->email !== null) {
            $user->setEmail($dto->email);
        }

        if ($dto->password !== null && $dto->password !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
            $user->setPassword($hashedPassword);
        }

        if ($dto->firstName !== null || $dto->lastName !== null) {
            $fullName = trim(($dto->firstName ?? '') . ' ' . ($dto->lastName ?? ''));
            if ($fullName !== '') {
                $user->setFullName($fullName);
            }
        }

        if ($dto->phone !== null) {
            $user->setPhone($dto->phone);
        }

        if ($dto->profilePhoto !== null) {
            $user->setProfilePhoto($dto->profilePhoto);
        }

        if ($dto->platformRole !== null) {
            $user->setPlatformRole($dto->platformRole);
        }

        if ($dto->isActive !== null) {
            $user->setIsActive($dto->isActive);
        }

        return $user;
    }
}
