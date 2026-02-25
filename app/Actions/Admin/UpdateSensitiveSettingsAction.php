<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Domain\UpdateSensitiveSettingsInputData;
use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Contracts\SensitiveSettingsStoreInterface;

readonly class UpdateSensitiveSettingsAction
{
    public function __construct(
        private SensitiveSettingsStoreInterface $settingsStore,
        private AuditLogService $auditLogService,
    ) {
    }

    public function execute(User $actor, UpdateSensitiveSettingsInputData $input): void
    {
        $changedFields = [];

        if (is_string($input->envatoApiToken) && $input->envatoApiToken !== '') {
            $this->settingsStore->saveEnvatoToken($input->envatoApiToken);
            $changedFields[] = 'envato_api_token';
        }

        if (is_string($input->licenseHmacKey) && $input->licenseHmacKey !== '') {
            $this->settingsStore->saveHmacKey($input->licenseHmacKey);
            $changedFields[] = 'license_hmac_key';
        }

        if (is_bool($input->envatoMockMode)) {
            $this->settingsStore->saveEnvatoMockMode($input->envatoMockMode);
            $changedFields[] = 'envato_mock_mode';
        }

        if ($changedFields === []) {
            return;
        }

        $this->auditLogService->log(
            eventType: AuditEventType::TOKEN_CHANGED,
            actor: $actor,
            licenseId: null,
            metadata: [
                'changed_fields' => $changedFields,
            ],
        );
    }
}
