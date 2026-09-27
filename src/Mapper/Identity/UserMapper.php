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
     */
    public function toResponse(User $user): UserResponse
    {
        return UserResponse::fromEntity($user);
    }


    /**
     * Mappe les données d'un DTO UserRequest vers l'entité User.
     * Assemble le nom complet et hache le mot de passe si fourni.
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

        $user->setIsActive($dto->isActive);

        return $user;
    }
}
