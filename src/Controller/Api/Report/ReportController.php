<?php

declare(strict_types=1);

namespace App\Controller\Api\Report;

use InvalidArgumentException;

use App\Dto\Feedback;
use App\Dto\Request\Report\AdminImmobilierReportFilterDto;
use App\Dto\Request\Report\AdminVilleReportFilterDto;
use App\Dto\Request\Report\PatronReportFilterDto;
use App\Dto\Request\Report\ReportFilterDto;
use App\Dto\Response\Report\AdminImmobilierReportResponse;
use App\Dto\Response\Report\AdminVilleReportResponse;
use App\Dto\Response\Report\PatronReportResponse;
use App\Dto\Response\Report\SuperAdminReportResponse;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Repository\Identity\OrganizationRepository;
use App\Security\SecurityServiceInterface;
use App\Security\SecurityAction;
use App\Service\Report\ReportService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * ReportController
 *
 * Package : Report Management
 *
 * Endpoints de génération de rapports administratifs multi-niveaux.
 *
 * Les filtres sont lus par `#[MapQueryString]` et NON par un mapping de
 * corps : ces routes sont en GET, et un mapping de corps ne trouve jamais
 * de corps. Sans cet attribut, tous les filtres arrivaient à `null` — y
 * compris la période demandée — et le rapport ignorait silencieusement ce
 * que le client avait demandé. Le défaut était antérieur au correctif
 * P0-1 ; il est devenu visible seulement quand `organizationUuid` est
 * devenu obligatoire et a fait échouer la requête en 400.
 *
 * Chaque rôle dispose d'un rapport adapté à son périmètre de responsabilité :
 * - PATRON        : vision globale de son organisation (finances, occupation, impayés, dépenses)
 * - ADMIN_IMMOBILIER : rapport opérationnel du patrimoine (occupation, impayés, dépenses liées aux biens)
 * - ADMIN_VILLE   : rapport limité aux villes qui lui sont attribuées (occupation, impayés, dépenses, personnel)
 * - SUPER_ADMIN   : vue plateforme (organisations, utilisateurs, indicateurs globaux)
 *
 * Tous les endpoints supportent l'export PDF (`?format=pdf`) via dompdf.
 * L'authentification se fait par jeton Bearer : `Authorization: Bearer <token>`.
 *
 * @see ReportService pour la logique d'agrégation et d'isolation Organization → City.
 */
#[Route('/api/v1/reports', name: 'api_reports_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Reports', description: 'Rapports administratifs par rôle (Patron, Admin Immobilier, Admin Ville, Super Admin)')]
final class ReportController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly OrganizationRepository $organizationRepository,
        private readonly SecurityServiceInterface $securityService,
    ) {
    }

    // ==================== PATRON ====================

    #[Route('/patron', name: 'patron', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/patron',
        summary: 'Rapport global pour le Patron (niveau Organisation)',
        description: 'Retourne un rapport consolidé : finances, occupation, impayés, dépenses par ville/parcelle/immeuble.',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'organizationUuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de l\'organisation concernée (obligatoire)'),
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'cityUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par ville'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: PatronReportResponse::class))),
            new OA\Response(response: 400, description: 'Paramètre organizationUuid absent ou invalide', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function patronReport(#[MapQueryString] PatronReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        [$organization, $error] = $this->resolveReportOrganization(
            $filter->organizationUuid,
            $user,
            OrganizationRole::PATRON,
            'Accès réservé au Patron de l\'organisation.'
        );

        if ($error !== null) {
            return $error;
        }

        $report = $this->reportService->generatePatronReport($filter, $organization);

        if ($filter->format === 'pdf') {
            return $this->renderPdf('report/patron.html.twig', ['report' => $report]);
        }

        return $this->json($report);
    }

    // ==================== ADMIN_IMMOBILIER ====================

    #[Route('/admin-immobilier', name: 'admin_immobilier', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/admin-immobilier',
        summary: 'Rapport opérationnel pour l\'Administrateur Immobilier',
        description: 'Retourne occupation par parcelle/immeuble, impayés, dépenses liées aux biens, évolution occupation.',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'organizationUuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de l\'organisation concernée (obligatoire)'),
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'cityUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par ville'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: AdminImmobilierReportResponse::class))),
            new OA\Response(response: 400, description: 'Paramètre organizationUuid absent ou invalide', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function adminImmobilierReport(#[MapQueryString] AdminImmobilierReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        [$organization, $error] = $this->resolveReportOrganization(
            $filter->organizationUuid,
            $user,
            OrganizationRole::ADMIN_IMMOBILIER,
            'Accès réservé à l\'Administrateur Immobilier.'
        );

        if ($error !== null) {
            return $error;
        }

        $report = $this->reportService->generateAdminImmobilierReport($filter, $organization);

        if ($filter->format === 'pdf') {
            return $this->renderPdf('report/admin_immobilier.html.twig', ['report' => $report]);
        }

        return $this->json($report);
    }

    // ==================== ADMIN_VILLE ====================

    #[Route('/admin-ville/{cityUuid}', name: 'admin_ville', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/admin-ville/{cityUuid}',
        summary: 'Rapport pour l\'Administrateur de Ville',
        description: 'Retourne occupation, impayés, dépenses et personnel de la ville (limité aux villes attribuées à l\'utilisateur).',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'cityUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la ville'),
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: AdminVilleReportResponse::class))),
            new OA\Response(response: 403, description: 'Accès refusé (ville non attribuée)', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Ville introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function adminVilleReport(string $cityUuid, #[MapQueryString] AdminVilleReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $city = $this->reportService->getCityByUuid($cityUuid);

        if (!$city) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Ville introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        // `checkCityAccess()` enchaîne l'appartenance à l'Organization, la
        // matrice rôle x action, puis la restriction UserCity propre à
        // l'ADMIN_VILLE. C'est le seul contrôle qui retire à un
        // administrateur de ville l'accès à une ville de sa propre
        // organization qu'il ne s'est pas vu attribuer.
        $this->securityService->checkCityAccess($city, SecurityAction::VIEW_REPORT);

        $report = $this->reportService->generateAdminVilleReport($filter, $city);

        if ($filter->format === 'pdf') {
            return $this->renderPdf('report/admin_ville.html.twig', ['report' => $report]);
        }

        return $this->json($report);
    }

    // ==================== SUPER_ADMIN ====================

    #[Route('/super-admin', name: 'super_admin', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/super-admin',
        summary: 'Rapport global pour SUPER_ADMIN (plateforme entière)',
        description: 'Retourne la liste des organisations avec leurs indicateurs clés.',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: SuperAdminReportResponse::class))),
            new OA\Response(response: 403, description: 'Accès réservé à SUPER_ADMIN', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function superAdminReport(#[MapQueryString] ReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $this->securityService->requirePlatformRole(\App\Enum\PlatformRole::SUPER_ADMIN);

        $report = $this->reportService->generateSuperAdminReport($filter);

        if ($filter->format === 'pdf') {
            return $this->renderPdf('report/super_admin.html.twig', ['report' => $report]);
        }

        return $this->json($report);
    }

    /**
     * Résout l'Organization ciblée par la requête et vérifie que
     * l'appelant y détient le rôle exigé.
     *
     * L'organisation vient TOUJOURS de la requête, jamais d'une
     * appartenance choisie dans la liste du compte : un utilisateur peut
     * appartenir à plusieurs Organizations, et la première renvoyée par la
     * base n'a aucun rapport avec celle qu'il souhaite consulter. Sans
     * identifiant explicite, un Patron multi-sociétés recevrait le rapport
     * d'une entreprise qui n'est pas la sienne — ou se le refuserait
     * injustement.
     *
     * Le rôle est résolu DANS cette Organization via
     * `SecurityService::hasOrganizationRole()`, jamais via
     * `isGranted('ROLE_...')` : `User::getRoles()` ne porte que ROLE_USER et
     * ROLE_SUPER_ADMIN (les rôles métier vivent dans `organization_user`),
     * il n'existe aucun Voter, et `security.yaml` ne déclare aucun
     * `role_hierarchy`. Un `isGranted('ROLE_PATRON', $organization)` est
     * donc refusé à tout le monde, y compris à un vrai Patron.
     *
     * @return array{0: ?Organization, 1: ?JsonResponse} l'Organization, ou
     *                                                 la réponse d'erreur
     */
    private function resolveReportOrganization(
        ?string $organizationUuid,
        User $user,
        OrganizationRole $requiredRole,
        string $deniedMessage,
    ): array {
        if ($organizationUuid === null || $organizationUuid === '') {
            return [
                null,
                $this->json(
                    (new Feedback())
                        ->addError('organizationUuid', 'Paramètre obligatoire.')
                        ->setErrorFlushDescription('Le paramètre "organizationUuid" est obligatoire : le rapport porte sur une organisation précise.')
                        ->setStatus(400)->autoInitFlush(),
                    400
                ),
            ];
        }

        try {
            $parsed = Uuid::fromString($organizationUuid);
        } catch (InvalidArgumentException) {
            return [
                null,
                $this->json(
                    (new Feedback())
                        ->addError('organizationUuid', 'UUID invalide.')
                        ->setErrorFlushDescription('Le paramètre "organizationUuid" n\'est pas un UUID valide.')
                        ->setStatus(400)->autoInitFlush(),
                    400
                ),
            ];
        }

        $organization = $this->organizationRepository->findOneByUuid($parsed);

        if ($organization === null) {
            return [
                null,
                $this->json(
                    (new Feedback())->setErrorFlushDescription('Organisation introuvable.')->setStatus(404)->autoInitFlush(),
                    404
                ),
            ];
        }

        // Appartenance + statut ACTIVE de l'Organization : un compte
        //Patron d'une organisation désactivée ne doit pas lire ses
        // chiffres.
        if (!$this->securityService->canAccessOrganization($organization, SecurityAction::VIEW)) {
            return [
                null,
                $this->json(
                    (new Feedback())->setErrorFlushDescription($deniedMessage)->setStatus(403)->autoInitFlush(),
                    403
                ),
            ];
        }

        if (!$this->securityService->hasOrganizationRole($user, $organization, $requiredRole)) {
            return [
                null,
                $this->json(
                    (new Feedback())->setErrorFlushDescription($deniedMessage)->setStatus(403)->autoInitFlush(),
                    403
                ),
            ];
        }

        return [$organization, null];
    }

    private function renderPdf(string $template, array $params): Response
    {
        $html = $this->renderView($template, $params);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rapport-' . date('Y-m-d') . '.pdf"',
            ]
        );
    }
}