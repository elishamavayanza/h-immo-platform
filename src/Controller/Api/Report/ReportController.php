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
use App\Dto\Response\Report\SuperAdminDashboardResponse;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Repository\Identity\OrganizationRepository;
use App\Security\SecurityServiceInterface;
use App\Security\SecurityAction;
use App\Service\Report\ReportService;
use App\Service\System\DateTimeService;
use App\Service\System\FileUploadService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
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
        private readonly DateTimeService $dateTime,
        private readonly RequestStack $requestStack,
        private readonly FileUploadService $fileUploadService,
        private readonly KernelInterface $kernel,
    ) {
    }

    private function getFormatFromRequest(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->query->get('format', 'json') ?? 'json';
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
    public function patronReport(#[MapQueryString(validationFailedStatusCode: 422)] PatronReportFilterDto $filter): Response
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

        $periodError = $this->requirePeriodForPdf($filter);
        if ($periodError !== null) {
            return $periodError;
        }

        $report = $this->reportService->generatePatronReport($filter, $organization);

        $format = $this->getFormatFromRequest();
        if ($format === 'pdf') {
            return $this->renderPdf('report/patron.html.twig', $this->buildPdfParams(
                report: $report,
                title: 'Rapport d\'activité — ' . $organization->getName(),
                reportType: 'Rapport organisation (Patron)',
                scope: 'Organisation entière',
                organization: $organization,
                cityName: $this->resolveCityName($filter->cityUuid, $organization),
            ));
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
    public function adminImmobilierReport(#[MapQueryString(validationFailedStatusCode: 422)] AdminImmobilierReportFilterDto $filter): Response
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

        $periodError = $this->requirePeriodForPdf($filter);
        if ($periodError !== null) {
            return $periodError;
        }

        $report = $this->reportService->generateAdminImmobilierReport($filter, $organization);

        $format = $this->getFormatFromRequest();
        if ($format === 'pdf') {
            return $this->renderPdf('report/admin_immobilier.html.twig', $this->buildPdfParams(
                report: $report,
                title: 'Rapport immobilier — ' . $organization->getName(),
                reportType: 'Rapport opérationnel (Admin immobilier)',
                scope: 'Patrimoine de l\'organisation',
                organization: $organization,
                cityName: $this->resolveCityName($filter->cityUuid, $organization),
            ));
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
    public function adminVilleReport(string $cityUuid, #[MapQueryString(validationFailedStatusCode: 422)] AdminVilleReportFilterDto $filter): Response
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

        $periodError = $this->requirePeriodForPdf($filter);
        if ($periodError !== null) {
            return $periodError;
        }

        $report = $this->reportService->generateAdminVilleReport($filter, $city);

        $format = $this->getFormatFromRequest();
        if ($format === 'pdf') {
            return $this->renderPdf('report/admin_ville.html.twig', $this->buildPdfParams(
                report: $report,
                title: 'Rapport de ville — ' . $city->getName(),
                reportType: 'Rapport de ville (Admin ville)',
                scope: 'Ville : ' . $city->getName(),
                organization: $city->getOrganization(),
                cityName: $city->getName(),
            ));
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
    public function superAdminReport(#[MapQueryString(validationFailedStatusCode: 422)] ReportFilterDto $filter): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $this->securityService->requirePlatformRole(\App\Enum\PlatformRole::SUPER_ADMIN);

        $periodError = $this->requirePeriodForPdf($filter);
        if ($periodError !== null) {
            return $periodError;
        }

        $format = $this->getFormatFromRequest();

        $report = $this->reportService->generateSuperAdminReport($filter);

        if ($format === 'pdf') {
            return $this->renderPdf('report/super_admin.html.twig', [
                'report' => $report,
                'title' => 'Rapport Plateforme H-Immo',
                'reportType' => 'Rapport plateforme (Super administrateur)',
                'scope' => 'Toutes les organisations',
                'appliedFilters' => [],
                'generatedAt' => $this->dateTime->now(),
                'applicationLogo' => $this->applicationLogoDataUri(),
            ]);
        }
        return $this->json($report);
    }

    /**
     * Tableau de bord temps réel pour SUPER_ADMIN.
     */
    #[Route('/super-admin/dashboard', name: 'super_admin_dashboard', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/super-admin/dashboard',
        summary: 'Tableau de bord temps réel pour SUPER_ADMIN',
        description: 'Retourne les compteurs globaux des organisations et comptes, ainsi que les événements d’administration plateforme. Aucun indicateur métier détaillé d’organisation n’est inclus.',
        security: [['bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tableau de bord généré', content: new OA\JsonContent(ref: new Model(type: SuperAdminDashboardResponse::class))),
            new OA\Response(response: 403, description: 'Accès réservé à SUPER_ADMIN', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function superAdminDashboard(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $this->securityService->requirePlatformRole(\App\Enum\PlatformRole::SUPER_ADMIN);

        $dashboard = $this->reportService->generateSuperAdminDashboard();

        return $this->json($dashboard);
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
        // La permission dédiée au rapport doit être vérifiée ici : VIEW seul
        // autorise la lecture générale de l'organisation, mais ne doit pas
        // remplacer le droit explicite de consulter les rapports.
        if (!$this->securityService->canAccessOrganization($organization, SecurityAction::VIEW_REPORT)) {
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

    /**
     * Prépare le contexte administratif commun aux PDF de rapport.
     *
     * Le document doit être exploitable seul : identité de l'organisation,
     * périmètre du rapport, période réellement couverte (telle que calculée
     * par le service, pas celle demandée), filtres appliqués et date de
     * génération. Les informations absentes (adresse, logo) sont simplement
     * omises, jamais inventées.
     *
     * @param list<string> $extraFilters
     *
     * @return array<string, mixed>
     */
    private function buildPdfParams(
        object $report,
        string $title,
        string $reportType,
        string $scope,
        Organization $organization,
        ?string $cityName = null,
        array $extraFilters = [],
    ): array {
        $appliedFilters = $extraFilters;

        if ($cityName !== null && $cityName !== '') {
            $appliedFilters[] = 'Ville : ' . $cityName;
        }

        return [
            'report' => $report,
            'title' => $title,
            'reportType' => $reportType,
            'scope' => $scope,
            'organization' => $organization,
            'organizationLogo' => $this->buildLogoDataUri($organization->getLogo()),
            'appliedFilters' => $appliedFilters,
            'generatedAt' => $this->dateTime->now(),
            'netResult' => $report instanceof PatronReportResponse
                ? bcsub($report->totalRevenues, $report->totalExpenses, 2)
                : null,
        ];
    }

    /**
     * Encode le logo de l'organisation en data URI pour dompdf.
     *
     * On lit le fichier plutôt que de pointer vers `/uploads/...` : dompdf
     * charge les images distantes avec `isRemoteEnabled`, mais une URL
     * relative dépend du `basePath` du rendu, ce qui rend le PDF fragile
     * selon l'environnement (CLI, worker, sous-dossier). Un data URI est
     * autonome. Un logo introuvable renvoie `null` : le PDF part sans image,
     * sans échouer.
     */
    private function buildLogoDataUri(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }

        $absolute = $this->fileUploadService->resolveInsideUploads($logo);

        if ($absolute === null || !is_file($absolute)) {
            return null;
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    /**
     * Nom de la ville filtrée, si le filtre désigne une ville du périmètre.
     *
     * N'est appelé que sur le chemin PDF, après que le service a déjà validé
     * l'appartenance de la ville à l'organisation. Sert uniquement à
     * l'affichage des filtres appliqués.
     */
    private function resolveCityName(?string $cityUuid, Organization $organization): ?string
    {
        if ($cityUuid === null || $cityUuid === '') {
            return null;
        }

        $city = $this->reportService->getCityByUuid($cityUuid);

        if ($city === null || $city->getOrganization()->getId() !== $organization->getId()) {
            return null;
        }

        return $city->getName();
    }

    private function renderPdf(string $template, array $params): Response
    {
        $options = new \Dompdf\Options();
        // Les deux logos sont embarqués en data URI ; aucun chargement réseau
        // ni accès à une URL contrôlée par les données du rapport n'est requis.
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $params['applicationLogo'] ??= $this->applicationLogoDataUri();
        $dompdf->loadHtml($this->renderView($template, $params));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $dompdf->getCanvas()->page_text(470, 797, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.25, 0.25, 0.25]);

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rapport-' . $this->dateTime->format($this->dateTime->now(), 'Y-m-d') . '.pdf"',
            ]
        );
    }

    /**
     * Un export doit toujours documenter une période choisie explicitement.
     * Les vues JSON gardent leur comportement historique tant qu'aucun filtre
     * n'est transmis ; l'export PDF, lui, ne génère jamais un intervalle implicite.
     */
    private function requirePeriodForPdf(ReportFilterDto $filter): ?JsonResponse
    {
        if ($this->getFormatFromRequest() !== 'pdf') {
            return null;
        }

        if ($filter->periodFrom !== null && $filter->periodTo !== null) {
            return null;
        }

        return $this->json(
            (new Feedback())
                ->addError('periodFrom', 'Choisissez une date de début et une date de fin.')
                ->addError('periodTo', 'Choisissez une date de début et une date de fin.')
                ->setErrorFlushDescription('Sélectionnez une période complète avant de télécharger le rapport PDF.')
                ->setStatus(400)
                ->autoInitFlush(),
            400
        );
    }

    /** Le logo de l’application est embarqué afin que le PDF reste autonome. */
    private function applicationLogoDataUri(): ?string
    {
        $path = $this->kernel->getProjectDir() . '/tools/assets/soft-immo-logo-pdf.png';
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
