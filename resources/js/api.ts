const CSRF_HEADER = "X-XSRF-TOKEN";

export interface ApiErrorData {
    message?: string;
    errors?: Record<string, string[]>;
    [key: string]: unknown;
}

export class ApiError extends Error {
    status: number;
    data: ApiErrorData;

    constructor(status: number, data: ApiErrorData) {
        super(data.message ?? "Request failed");
        this.name = "ApiError";
        this.status = status;
        this.data = data;
    }
}

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
    return match ? decodeURIComponent(match[2]) : null;
}

export async function api<T = unknown>(url: string, options: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
        Accept: "application/json",
        [CSRF_HEADER]: getCookie("XSRF-TOKEN") ?? "",
        ...(options.headers as Record<string, string> | undefined),
    };

    if (!(options.body instanceof FormData)) {
        headers["Content-Type"] = "application/json";
    }

    const response = await fetch(`/api${url}`, {
        ...options,
        headers,
        credentials: "same-origin",
    });

    if (response.status === 419) {
        window.location.reload();
        return undefined as T;
    }

    const data = await response.json();

    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data as T;
}
