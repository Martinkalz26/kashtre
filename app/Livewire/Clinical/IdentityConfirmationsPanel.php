<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 2 — positive patient identification, generalizing MAR's
 * existing 5-Rights check to 7 more action types beyond medication
 * administration.
 */
#[Lazy]
class IdentityConfirmationsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $actionType = 'SPECIMEN_COLLECTION';

    public string $method = 'VERBAL_AND_BAND';

    /** @var array<int, string> */
    public array $identifiersUsed = ['NAME', 'DOB'];

    public string $notes = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    private const ACTION_TYPES = [
        'SPECIMEN_COLLECTION', 'BLOOD_TRANSFUSION', 'PROCEDURE_OR_SURGERY',
        'IMAGING_STUDY', 'DISCHARGE', 'PATIENT_HANDOVER', 'DOCUMENT_RELEASE',
    ];

    public const IDENTIFIER_OPTIONS = ['NAME', 'DOB', 'WRISTBAND', 'MRN', 'PATIENT_ID', 'PHOTO'];

    /**
     * Confirmed live against Clinical (2026-09-11): of the SRD's 7 action
     * types above, only SPECIMEN_COLLECTION matches Clinical's actual
     * action_type enum — the other 6 all return 422 "The selected action
     * type is invalid." Neither source doc gives the literal enum, and
     * Clinical has no metadata endpoint to discover it from. OTHER is
     * confirmed to exist, so an unmatched selection maps to it with the
     * chosen label preserved in the notes sent, rather than 422ing.
     */
    private const WIRE_CONFIRMED_ACTION_TYPES = ['SPECIMEN_COLLECTION'];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $rows = [];

        try {
            $rows = app(IdentityConfirmationGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load identity confirmations — Clinical may be unreachable.';
        }

        return view('livewire.clinical.identity-confirmations-panel', [
            'rows' => $rows,
            'actionTypes' => self::ACTION_TYPES,
            'identifierOptions' => self::IDENTIFIER_OPTIONS,
        ]);
    }

    public function confirm(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'actionType' => ['required', 'string'],
            'identifiersUsed' => ['required', 'array', 'min:1'],
        ]);

        $this->errorMessage = null;
        $this->resultMessage = null;

        $wireActionType = in_array($this->actionType, self::WIRE_CONFIRMED_ACTION_TYPES, true)
            ? $this->actionType
            : 'OTHER';
        $notes = $wireActionType === 'OTHER'
            ? trim("[{$this->actionType}] {$this->notes}")
            : ($this->notes ?: null);

        try {
            app(IdentityConfirmationGateway::class)->confirm($this->actor(), $this->clientId, [
                'action_type' => $wireActionType,
                'confirmed_by_user_id' => Auth::id(),
                'method' => $this->method,
                'identifiers_used' => $this->identifiersUsed,
                'notes' => $notes,
            ]);
        } catch (ClinicalApiException $e) {
            $fieldErrors = collect($e->errors())->filter(fn ($v) => is_array($v))->flatten();
            $this->errorMessage = $fieldErrors->isNotEmpty() ? $fieldErrors->first() : $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Identity confirmed and recorded.';
        $this->reset(['notes']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
