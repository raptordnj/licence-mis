<?php

declare(strict_types=1);

namespace App\Data\Domain;

use App\Enums\LicenseValidationReason;
use Spatie\LaravelData\Data;

class ValidationResultDTO extends Data
{
    /**
     * @param  array<int, string>  $domainRestrictions
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public bool $valid,
        public LicenseValidationReason $reason,
        public ?int $envatoItemId,
        public ?string $buyer,
        public ?string $supportedUntil,
        public ?string $itemName,
        public ?int $maxActivations,
        public array $domainRestrictions,
        public string $source,
        public string $matchedBy,
        public ?array $rawPayload,
    ) {
    }

    /**
     * @param  array<int, string>  $domainRestrictions
     * @param  array<string, mixed>|null  $rawPayload
     */
    public static function validResult(
        ?int $envatoItemId,
        ?string $buyer,
        ?string $supportedUntil,
        ?string $itemName,
        ?int $maxActivations,
        array $domainRestrictions,
        string $source,
        string $matchedBy,
        ?array $rawPayload = null,
    ): self {
        return new self(
            valid: true,
            reason: LicenseValidationReason::NONE,
            envatoItemId: $envatoItemId,
            buyer: $buyer,
            supportedUntil: $supportedUntil,
            itemName: $itemName,
            maxActivations: $maxActivations,
            domainRestrictions: $domainRestrictions,
            source: $source,
            matchedBy: $matchedBy,
            rawPayload: $rawPayload,
        );
    }

    public static function invalidResult(
        LicenseValidationReason $reason,
        string $source,
        string $matchedBy,
    ): self {
        return new self(
            valid: false,
            reason: $reason,
            envatoItemId: null,
            buyer: null,
            supportedUntil: null,
            itemName: null,
            maxActivations: null,
            domainRestrictions: [],
            source: $source,
            matchedBy: $matchedBy,
            rawPayload: null,
        );
    }
}
