<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\LicenseStatus;
use App\Models\AuditLog;
use App\Models\License;

readonly class AdminDashboardViewModel
{
    /**
     * @return array{
     *     metrics: array{total_licenses: int, active_licenses: int, revoked_licenses: int, expired_licenses: int},
     *     recent_licenses: array<int, array{id: int, purchase_code: string, status: string, bound_domain: string|null, envato_item_id: int|null, verified_at: string|null}>,
     *     recent_audit_logs: array<int, array{id: int, event_type: string, created_at: string|null, actor: array{id: int, name: string, email: string}|null, license: array{id: int, purchase_code: string}|null}>
     * }
     */
    public function toArray(): array
    {
        $recentLicenses = License::query()
            ->select([
                'id',
                'purchase_code',
                'status',
                'bound_domain',
                'envato_item_id',
                'verified_at',
            ])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static fn (License $license): array => [
                'id' => $license->id,
                'purchase_code' => $license->purchase_code,
                'status' => $license->status->value,
                'bound_domain' => $license->bound_domain,
                'envato_item_id' => $license->envato_item_id,
                'verified_at' => $license->verified_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentAuditLogs = AuditLog::query()
            ->with([
                'actor:id,name,email',
                'license:id,purchase_code',
            ])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static fn (AuditLog $auditLog): array => [
                'id' => $auditLog->id,
                'event_type' => $auditLog->event_type->value,
                'created_at' => $auditLog->created_at?->toIso8601String(),
                'actor' => $auditLog->actor !== null ? [
                    'id' => $auditLog->actor->id,
                    'name' => $auditLog->actor->name,
                    'email' => $auditLog->actor->email,
                ] : null,
                'license' => $auditLog->license !== null ? [
                    'id' => $auditLog->license->id,
                    'purchase_code' => $auditLog->license->purchase_code,
                ] : null,
            ])
            ->values()
            ->all();

        return [
            'metrics' => [
                'total_licenses' => License::query()->count(),
                'active_licenses' => License::query()->where('status', LicenseStatus::ACTIVE)->count(),
                'revoked_licenses' => License::query()->where('status', LicenseStatus::REVOKED)->count(),
                'expired_licenses' => License::query()->where('status', LicenseStatus::EXPIRED)->count(),
            ],
            'recent_licenses' => $recentLicenses,
            'recent_audit_logs' => $recentAuditLogs,
        ];
    }
}
