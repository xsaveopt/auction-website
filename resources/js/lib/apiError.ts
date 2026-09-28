import { ApiError } from "./api";

export function apiError(error: unknown, ...fields: string[]): string | undefined {
    if (!(error instanceof ApiError)) return undefined;
    if (error.data.message) return error.data.message;
    for (const field of fields) {
        const value = error.data.errors?.[field]?.[0];
        if (value) return value;
    }
    return undefined;
}
