<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ValidationLogIndexRequest;
use App\Models\LicenseCheck;
use App\Models\License;
use App\Queries\ValidationLogIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminValidationLogController extends Controller
{
    public function index(ValidationLogIndexRequest $request, ValidationLogIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $logs = $query->build($request->validated())
            ->paginate((int) $request->integer('per_page', 20));

        $mapped = $logs->getCollection()->map(static function (LicenseCheck $licenseCheck): array {
            $requestPayload = is_array($licenseCheck->request_payload) ? $licenseCheck->request_payload : [];
            $responsePayload = is_array($licenseCheck->response_payload) ? $licenseCheck->response_payload : [];
            $instance = $licenseCheck->instance;
            $license = $instance?->license;
            $domainFallback = 'unknown';
            $purchaseCode = 'N/A';
            $itemFallback = 'Unknown Item';
            $signaturePresent = false;

            if ($license !== null) {
                $domainFallback = $license->bound_domain ?? 'unknown';
                $purchaseCode = $license->purchase_code;
                $itemFallback = $license->envato_item_id !== null
                    ? 'Item '.$license->envato_item_id
                    : 'Unknown Item';
            }

            if (is_string(data_get($requestPayload, 'signature_proof'))) {
                $signaturePresent = trim((string) data_get($requestPayload, 'signature_proof')) !== '';
            } elseif (is_bool(data_get($requestPayload, 'signature_present'))) {
                $signaturePresent = (bool) data_get($requestPayload, 'signature_present');
            } elseif (is_bool(data_get($responsePayload, 'signature_present'))) {
                $signaturePresent = (bool) data_get($responsePayload, 'signature_present');
            }

            return [
                'id' => 'check-'.$licenseCheck->id,
                'time' => $licenseCheck->checked_at?->toIso8601String(),
                'result' => $licenseCheck->result->value === 'valid' ? 'success' : 'fail',
                'fail_reason' => $licenseCheck->result->value === 'invalid'
                    ? $licenseCheck->reason
                    : null,
                'domain_requested' => is_string(data_get($requestPayload, 'domain_requested'))
                    ? (string) data_get($requestPayload, 'domain_requested')
                    : (is_string(data_get($requestPayload, 'domain'))
                    ? (string) data_get($requestPayload, 'domain')
                    : ($instance?->domain ?? $domainFallback)),
                'ip' => is_string(data_get($requestPayload, 'ip'))
                    ? (string) data_get($requestPayload, 'ip')
                    : ($instance?->ip ?? '0.0.0.0'),
                'user_agent' => is_string(data_get($requestPayload, 'user_agent'))
                    ? (string) data_get($requestPayload, 'user_agent')
                    : ($instance?->user_agent ?? 'N/A'),
                'purchase_code' => is_string(data_get($requestPayload, 'purchase_code'))
                    ? (string) data_get($requestPayload, 'purchase_code')
                    : $purchaseCode,
                'item_name' => is_string(data_get($requestPayload, 'item_name'))
                    ? (string) data_get($requestPayload, 'item_name')
                    : $itemFallback,
                'correlation_id' => is_string(data_get($requestPayload, 'correlation_id'))
                    ? (string) data_get($requestPayload, 'correlation_id')
                    : (is_string(data_get($responsePayload, 'correlation_id'))
                        ? (string) data_get($responsePayload, 'correlation_id')
                        : 'corr-check-'.$licenseCheck->id),
                'signature_present' => $signaturePresent,
            ];
        })->values();

        $pagination = new LengthAwarePaginator(
            items: $mapped,
            total: $logs->total(),
            perPage: $logs->perPage(),
            currentPage: $logs->currentPage(),
            options: [
                'path' => $logs->path(),
                'query' => $request->query(),
            ],
        );

        return ApiResponse::success($pagination->toArray());
    }
}
