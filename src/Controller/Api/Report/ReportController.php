<?php

declare(strict_types=1);

namespace App\Controller\Api\Report;

use App\Dto\Feedback;
use App\Dto\Request\Report\AdminImmobilierReportFilterDto;
use App\Dto\Request\Report\AdminVilleReportFilterDto;
use App\Dto\Request\Report\PatronReportFilterDto;
use App\Dto\Request\Report\ReportFilterDto;
use App\Dto\Response\Report\AdminImmobilierReportResponse;
use App\Dto\Response\Report\AdminVilleReportResponse;
use App\Dto\Response\Report\PatronReportResponse;
use App\Dto\Response\Report\SuperAdminReportResponse;
use App\Entity\Identity\User;
use App\Service\Report\ReportService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ReportController
 *
 * Package : Report Management
 *
 * Endpoints de génération de rapports administratifs multi-niveaux.
 *
 * Chaque rôle dispose d'un rapport adapté à son périmètre de responsabilité :
 * - PATRON        : vision globale de son organisation (finances, occupation, impayés, dépenses)
 * - ADMIN_IMMOBILIER : rapport opérationnel du patrimoine (occupation, impayés, dépenses liées aux biens)
 * - ADMIN_VILLE   : rapport limité aux villes qui lui sont attribuées (occupation, impayés, dépenses, personnel)
 * - SUPER_ADMIN   : vue plateforme (organisations, utilisateurs, indicateurs globaux)
 *
 * Tous les endpoints supportent l'export PDF (`?format=pdf`) via dompdf.
 * L'authentification se fait par cookie de session `HIMMOMPA`.
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
    ) {
    }

    // ==================== PATRON ====================

    #[Route('/patron', name: 'patron', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/reports/patron',
        summary: 'Rapport global pour le Patron (niveau Organisation)',
        description: 'Retourne un rapport consolidé : finances, occupation, impayés, dépenses par ville/parcelle/immeuble.',
        security: [['sessionCookie' => []]],
        parameters: [
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'cityUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par ville'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: PatronReportResponse::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function patronReport(PatronReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $organization = $this->reportService->getOrganizationForUser($user);

        if (!$this->isGranted('ROLE_PATRON', $organization)) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Accès réservé au Patron de l\'organisation.')->setStatus(403)->autoInitFlush(),
                403
            );
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
        security: [['sessionCookie' => []]],
        parameters: [
            new OA\Parameter(name: 'periodFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début'),
            new OA\Parameter(name: 'periodTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin'),
            new OA\Parameter(name: 'cityUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par ville'),
            new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', enum: ['json', 'pdf']), description: 'Format de sortie (json ou pdf)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rapport généré', content: new OA\JsonContent(ref: new Model(type: AdminImmobilierReportResponse::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function adminImmobilierReport(AdminImmobilierReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $organization = $this->reportService->getOrganizationForUser($user);

        if (!$this->isGranted('ROLE_ADMIN_IMMOBILIER', $organization)) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Accès réservé à l\'Administrateur Immobilier.')->setStatus(403)->autoInitFlush(),
                403
            );
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
        security: [['sessionCookie' => []]],
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
    public function adminVilleReport(string $cityUuid, AdminVilleReportFilterDto $filter): JsonResponse
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

        // Vérifier que l'utilisateur a accès à cette ville
        if (!$this->isGranted('ROLE_ADMIN_VILLE', $city)) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Vous n\'êtes pas autorisé sur cette ville.')->setStatus(403)->autoInitFlush(),
                403
            );
        }

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
        security: [['sessionCookie' => []]],
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
    public function superAdminReport(ReportFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Accès réservé au SUPER_ADMIN.')->setStatus(403)->autoInitFlush(),
                403
            );
        }

        $report = $this->reportService->generateSuperAdminReport($filter);

        if ($filter->format === 'pdf') {
            return $this->renderPdf('report/super_admin.html.twig', ['report' => $report]);
        }

        return $this->json($report);
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