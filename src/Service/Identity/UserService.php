<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\UserRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\User;
use App\Mapper\Identity\UserMapper;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserService
 *
 * Package : Identity & Access — Service Métier
 *
 * Traite la logique métier, la persistance, le hachage des mots de passe
 * et la gestion du cycle de vie des utilisateurs enveloppée dans des Feedback.
 */
final readonly class UserService
{
    /**
     * Initialise les dépendances requises pour la gestion du domaine utilisateur.
     * Injecte l'EntityManager, le repository, le mapper et le validateur.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $repository,
        private UserMapper $mapper,
        private ValidatorInterface $validator,
        private SecurityServiceInterface $security
    ) {
    }

    /**
     * Récupère la liste paginée et filtrée des utilisateurs enregistrés.
     * Renvoie les résultats mappés en DTO dans une enveloppe Feedback HTTP 200.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        // Sans restriction, cette liste exposerait les comptes de tous les
        // tenants de la plateforme. Un utilisateur ne voit que les comptes
        // d'une Organization dont il est membre ; seul le SUPER_ADMIN peut
        // inventorier l'ensemble des comptes.
        $paginatedResult = $this->security->isSuperAdmin()
            ? $this->repository->findPaginatedAll($query->page, $query->limit, $query->search)
            : $this->repository->findPaginatedByOrganizations(
                $this->security->getCurrentUserOrganizations(),
                $query->page,
                $query->limit,
                $query->search
            );

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des utilisateurs récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Obtient le détail d'un utilisateur par son identifiant unique UUID.
     * Retourne une réponse Feedback en erreur HTTP 404 si l'utilisateur n'existe pas.
     */
    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'L\'utilisateur demandé n\'existe pas.')
                ->setErrorFlushDescription('Utilisateur introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::VIEW_USER);

        return $feedback
            ->setData($this->mapper->toResponse($user))
            ->setFlushDescription('Détails de l\'utilisateur récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Crée un nouveau compte utilisateur après vérification de l'unicité de l'email.
     * Hache le mot de passe, persiste l'entité et retourne un Feedback HTTP 201.
     */
    public function create(UserRequest $request): Feedback
    {
        $feedback = new Feedback();

        // Créer un compte est une opération d'administration de plateforme.
        $this->security->requirePlatformRole();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données utilisateur invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($this->repository->findOneBy(['email' => $request->email])) {
            return $feedback
                ->addError('email', 'Cette adresse e-mail est déjà utilisée.')
                ->setErrorFlushDescription('Conflit sur l\'adresse e-mail.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $user = $this->mapper->copyToEntity($request, new User());
        // `isActive` est nullable dans le DTO (un PUT ne doit pas réactiver un
        // compte suspendu) ; une création, elle, part toujours d'un compte actif
        // sauf demande explicite de la part d'un SUPER_ADMIN.
        $user->setIsActive($request->isActive ?? true);
        $this->em->persist($user);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($user))
            ->setFlushDescription('Compte utilisateur créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Met à jour les informations d'un utilisateur identifié par son UUID.
     * Effectue la validation du payload et la mise à jour sélective des champs.
     */
    public function update(string $uuid, UserRequest $request): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Mise à jour impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::UPDATE_USER);

        // Un droit de rôle ne se délègue pas par un PUT sur une fiche.
        // `checkUserAccess()` autorise explicitement l'auto-service, il ne peut
        // donc pas servir de garde-fou sur un champ qui *accorde* des droits :
        // sans ce filtre, `{"platformRole": "super_admin"}` sur sa propre fiche
        // suffirait à devenir administrateur de la plateforme.
        if ($request->platformRole !== null) {
            $this->security->requirePlatformRole();
        }

        // Même raison pour l'activation : un PATRON peut suspendre un compte de
        // sa société, mais un membre ne doit ni réactiver le sien (contournement
        // d'une suspension) ni en suspendre un autre. La matrice de rôle refuse
        // déjà ces deux actions aux ADMIN_IMMOBILIER et ADMIN_VILLE.
        if ($request->isActive !== null) {
            $this->security->checkUserAccess(
                $user,
                $request->isActive
                    ? SecurityAction::ACTIVATE_USER
                    : SecurityAction::SUSPEND_USER
            );
        }

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Échec de la validation des données.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($request->email !== null && $request->email !== $user->getEmail()) {
            if ($this->repository->findOneBy(['email' => $request->email])) {
                return $feedback
                    ->addError('email', 'Cette adresse e-mail est déjà attribuée.')
                    ->setErrorFlushDescription('Adresse e-mail indisponible.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        $this->mapper->copyToEntity($request, $user);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($user))
            ->setFlushDescription('Utilisateur mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Effectue une suppression logique (Soft Delete) du compte utilisateur.
     * Desactive le compte et enregistre la date de suppression sans destruction physique.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Suppression impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::DELETE_USER);

        $user->softDelete();
        $user->setIsActive(false);
        $this->em->flush();

        return $feedback
            ->setFlushDescription('L\'utilisateur a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
