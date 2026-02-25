import axios, { AxiosError, type AxiosRequestConfig } from 'axios';
import { z } from 'zod';

import { apiEnvelopeSchema } from '@/admin/schemas/api';
import type { ApiError } from '@/admin/types/api';

let authToken: string | null = null;

export class ApiRequestError extends Error {
    public code: string;

    public status: number;

    public constructor(code: string, message: string, status: number) {
        super(message);
        this.code = code;
        this.status = status;
    }
}

export const http = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

http.interceptors.request.use((config) => {
    if (authToken !== null && authToken !== '') {
        config.headers.Authorization = `Bearer ${authToken}`;
    } else if (config.headers.Authorization !== undefined) {
        delete config.headers.Authorization;
    }

    return config;
});

export const setAuthToken = (token: string | null): void => {
    authToken = token;
};

export const extractApiError = (error: unknown): ApiError => {
    const fallback: ApiError = {
        code: 'UNKNOWN',
        message: 'Unable to complete request.',
    };

    if (error instanceof ApiRequestError) {
        return {
            code: error.code,
            message: error.message,
        };
    }

    if (!(error instanceof AxiosError)) {
        if (error instanceof Error) {
            return {
                code: 'INTERNAL_ERROR',
                message: error.message,
            };
        }

        return fallback;
    }

    const payloadSchema = z.object({
        success: z.boolean(),
        data: z.unknown().nullable(),
        error: z
            .object({
                code: z.string(),
                message: z.string(),
            })
            .nullable(),
    });

    const payload = payloadSchema.safeParse(error.response?.data);

    if (payload.success && payload.data.error !== null) {
        return payload.data.error;
    }

    const plainPayload = z
        .object({
            message: z.string(),
            errors: z.record(z.string(), z.array(z.string())).optional(),
        })
        .passthrough()
        .safeParse(error.response?.data);

    if (plainPayload.success) {
        const firstValidationMessage =
            plainPayload.data.errors !== undefined
                ? Object.values(plainPayload.data.errors)
                      .flat()
                      .find((message): message is string => message !== '')
                : undefined;

        return {
            code: error.response?.status === 422 ? 'VALIDATION_ERROR' : 'INTERNAL_ERROR',
            message: firstValidationMessage ?? plainPayload.data.message,
        };
    }

    if (error.response?.status === 429) {
        return {
            code: 'RATE_LIMITED',
            message: 'Too many requests. Please try again shortly.',
        };
    }

    if (error.response?.status === 401) {
        return {
            code: 'UNAUTHORIZED',
            message: 'Authentication is required.',
        };
    }

    if (error.response?.status === 419) {
        return {
            code: 'UNAUTHORIZED',
            message: 'Your session has expired. Please sign in again.',
        };
    }

    return fallback;
};

export const requestData = async <T>(
    config: AxiosRequestConfig,
    schema: z.ZodSchema<T>,
): Promise<T> => {
    const response = await http.request(config);
    const envelope = apiEnvelopeSchema(schema).safeParse(response.data);

    if (envelope.success) {
        if (!envelope.data.success || envelope.data.data === null) {
            throw new ApiRequestError(
                envelope.data.error?.code ?? 'INTERNAL_ERROR',
                envelope.data.error?.message ?? 'Request failed.',
                response.status,
            );
        }

        return envelope.data.data;
    }

    const directPayload = schema.safeParse(response.data);

    if (directPayload.success) {
        return directPayload.data;
    }

    const plainMessage = z
        .object({
            message: z.string(),
        })
        .passthrough()
        .safeParse(response.data);

    throw new ApiRequestError(
        'INTERNAL_ERROR',
        plainMessage.success ? plainMessage.data.message : 'Unexpected API response format.',
        response.status,
    );
};
