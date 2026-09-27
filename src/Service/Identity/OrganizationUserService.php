<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationUserRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\OrganizationUser;
use App\Mapper\Identity\OrganizationUserMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * OrganizationUserService
 *
 * Package : Identity & Access — Service Métier
 *
 * Gère l'affectation et la révocation des accès/rôles des utilisateurs
 * au sein des différentes organisations (Tenants) via Feedback.
 */
final readonly class OrganizationUserService
{
    /**
     * Prépare le service en injectant les repositories d'accès utilisateur et tenant.
     * Fournit la logique d'assignation des privilèges et des rôles d'organisation.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationUserRepository $orgUserRepository,
        private OrganizationRepository $orgRepository,
        private UserRepository $userRepository,
        private OrganizationUserMapper $mapper,
        private ValidatorInterface $validator,
        private SecurityServiceInterface $security
    ) {
    }

    /**
     * Liste les utilisateurs et rôles rattachés à une organisation donnée.
     * Recherche les membres via l'UUID de l'organisation avec pagination.
     */
    public function listByOrganization(string $orgUuid, PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();
        $org = $this->orgRepository->findOneBy(['uuid' => $orgUuid]);

        if (!$org) {
            return $feedback
                ->addError('organizationUuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Impossible de récupérer la liste des utilisateurs.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        // Consulter la liste des membres d'une Organization est un droit
        // d'administration, pas une simple lecture.
        $this->security->checkOrganizationAccess($org, SecurityAction::MANAGE_USERS);

        $paginatedResult = $this->orgUserRepository->findPaginatedByOrganization(
            $org,
            $query->page,
            $query->limit
        );

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des membres récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Rattache un utilisateur à une organisation en lui assignant un rôle spécifique.
     * Contrôle l'unicité de la relation (un seul rôle par couple utilisateur/organisation).
     */
    public function assignUser(OrganizationUserRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de l\'affectation invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $organization = $this->orgRepository->findOneBy(['uuid' => $request->organizationUuid]);
        if (!$organization) {
            return $feedback
                ->addError('organizationUuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Affectation impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $user = $this->userRepository->findOneBy(['uuid' => $request->userUuid]);
        if (!$user) {
            return $feedback
                ->addError('userUuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Affectation impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        // Attribuer un rôle revient à accorder des droits : sans ce contrôle,
        // n'importe quel utilisateur authentifié pouvait se nommer lui-même
        // PATRON d'une Organization et hériter de toutes ses données.
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_USERS);
        $this->security->checkUserAccess($user, SecurityAction::MANAGE_USERS);

        $existing = $this->orgUserRepository->findOneBy(['organization' => $organization, 'user' => $user]);
        if ($existing) {
            return $feedback
                ->addError('userUuid', 'L\'utilisateur appartient déjà à cette organisation.')
                ->setErrorFlushDescription('Relation utilisateur-organisation déjà existante.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $orgUser = new OrganizationUser();
        $orgUser->setOrganization($organization);
        $orgUser->setUser($user);
        $orgUser->setRole($request->role);

        $this->em->persist($orgUser);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($orgUser))
            ->setFlushDescription('L\'utilisateur a été affecté à l\'organisation.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Modifie le rôle d'un utilisateur au sein d'une organisation.
     * Recherche la relation par son UUID public et applique le nouveau privilège.
     */
    public function updateRole(string $uuid, OrganizationUserRequest $request): Feedback
    {
        $feedback = new Feedback();
        $orgUser = $this->orgUserRepository->findOneBy(['uuid' => $uuid]);

        if (!$orgUser) {
            return $feedback
                ->addError('uuid', 'Affectation utilisateur-organisation introuvable.')
                ->setErrorFlushDescription('Mise à jour du rôle impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess(
            $orgUser->getOrganization(),
            SecurityAction::MANAGE_USERS
        );

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de rôle invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($request->role !== null) {
            $orgUser->setRole($request->role);
            $this->em->flush();
        }

        return $feedback
            ->setData($this->mapper->toResponse($orgUser))
            ->setFlushDescription('Le rôle de l\'utilisateur a été mis à jour.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Retire l'accès d'un utilisateur à une organisation spécifique.
     * Supprime physiquement l'enregistrement de liaison entre l'utilisateur et le tenant.
     */
    public function revokeUser(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $orgUser = $this->orgUserRepository->findOneBy(['uuid' => $uuid]);

        if (!$orgUser) {
            return $feedback
                ->addError('uuid', 'Affectation introuvable.')
                ->setErrorFlushDescription('Révocation de l\'accès impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess(
            $orgUser->getOrganization(),
            SecurityAction::MANAGE_USERS
        );

        $this->em->remove($orgUser);
        $this->em->flush();

        return $feedback
            ->setFlushDescription('L\'accès de l\'utilisateur à l\'organisation a été révoqué.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
