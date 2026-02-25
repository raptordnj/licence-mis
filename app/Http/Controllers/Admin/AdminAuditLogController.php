<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Queries\AuditLogIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $auditLogs = $query->build($request->validated())
            ->paginate((int) $request->integer('per_page', 20));

        $pagination = $auditLogs->toArray();
        $pagination['data'] = $auditLogs->getCollection()->map(static fn (AuditLog $auditLog): array => [
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
            'metadata' => $auditLog->metadata,
        ])->values()->all();

        return ApiResponse::success($pagination);
    }
}
