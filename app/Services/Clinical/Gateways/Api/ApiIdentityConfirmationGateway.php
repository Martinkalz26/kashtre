<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiIdentityConfirmationGateway implements IdentityConfirmationGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function confirm(ClinicalActor $actor, string $patientId, array $payload): array
    {
        return $this->client->post(
            "clinical/patients/{$patientId}/identity-confirmations",
            array_filter([
                'action_type' => $payload['action_type'],
                'confirmed_by_user_id' => $payload['confirmed_by_user_id'],
                'method' => $payload['method'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    /**
     * Confirmed live against Clinical (2026-09-11): GET on this same path
     * returns 405 — the route exists for POST only. The guide's own table
     * documents just "POST .../identity-confirmations" for this capability,
     * with no read-back endpoint, so this returns empty rather than making
     * a call known to fail. Revisit once Clinical ships a list endpoint.
     */
    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        return [];
    }
}
