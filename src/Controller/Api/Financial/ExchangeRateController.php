<?php

declare(strict_types=1);

namespace App\Controller\Api\Financial;

use App\Dto\Request\Financial\CityExchangeRatePermissionRequest;
use App\Dto\Request\Financial\ExchangeRateRequest;
use App\Dto\Request\Financial\OrganizationCurrencyRequest;
use App\Service\Financial\ExchangeRateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/exchange-rates', name: 'api_exchange_rates_')]
final class ExchangeRateController extends AbstractController
{
    public function __construct(private readonly ExchangeRateService $service) {}

    #[Route('/organizations/{uuid}/settings', name: 'settings', methods: ['GET'])]
    public function settings(string $uuid): JsonResponse
    {
        $feedback = $this->service->settings($uuid);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/organizations/{uuid}/history', name: 'history', methods: ['GET'])]
    public function history(string $uuid, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $feedback = $this->service->history($uuid, (string) $request->query->get('date', ''));
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/organizations/{uuid}/organization', name: 'organization_update', methods: ['PUT'])]
    public function updateOrganization(string $uuid, #[MapRequestPayload] ExchangeRateRequest $request): JsonResponse
    {
        $feedback = $this->service->updateOrganizationRate($uuid, $request);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/organizations/{uuid}/currency', name: 'organization_currency_update', methods: ['PUT'])]
    public function updateOrganizationCurrency(string $uuid, #[MapRequestPayload] OrganizationCurrencyRequest $request): JsonResponse
    {
        $feedback = $this->service->updateOrganizationCurrency($uuid, $request);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/organizations/{uuid}/city-permission', name: 'city_permission', methods: ['PUT'])]
    public function setCityPermission(string $uuid, #[MapRequestPayload] CityExchangeRatePermissionRequest $request): JsonResponse
    {
        $feedback = $this->service->setCityPermission($uuid, $request);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/cities/{uuid}', name: 'city_update', methods: ['PUT'])]
    public function updateCity(string $uuid, #[MapRequestPayload] ExchangeRateRequest $request): JsonResponse
    {
        $feedback = $this->service->updateCityRate($uuid, $request);
        return $this->json($feedback, $feedback->getStatus());
    }
}
