<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiSensitivityRestrictionGateway implements SensitivityRestrictionGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    /**
     * Confirmed live against Clinical (2026-09-11): GET on
     * clinical/sensitivity-restrictions returns 405 regardless of query
     * params, and clinical/patients/{id}/sensitivity-restrictions is a 404
     * — there is no list/read-back route at all yet. Matches the guide's
     * own framing ("Live as a primitive — not yet consulted by ... export
     * endpoints"): this is create/lift only today. Returns empty rather
     * than calling a route known to fail; revisit once Clinical ships one.
     */
    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        return [];
    }

    public function restrict(ClinicalActor $actor, string $patientId, array $payload): array
    {
        return $this->client->post('clinical/sensitivity-restrictions', array_filter([
            'patient_id' => $patientId,
            'level' => $payload['level'],
            'label' => $payload['label'],
            'reason' => $payload['reason'],
            'resource_reference' => $payload['resource_reference'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function lift(ClinicalActor $actor, string $restrictionId, string $reason): array
    {
        return $this->client->post(
            "clinical/sensitivity-restrictions/{$restrictionId}/lift",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }
}
