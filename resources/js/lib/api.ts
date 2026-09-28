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
        throw new ApiError(419, { message: "Your session expired. Reloading the page." });
    }

    const data = await parseBody(response);

    if (!response.ok) {
        throw new ApiError(
            response.status,
            data ?? { message: `Request failed (${response.status}).` },
        );
    }

    if (data === null) {
        throw new ApiError(response.status, { message: "The server sent an unexpected response." });
    }

    return data as T;
}

async function parseBody(response: Response): Promise<ApiErrorData | null> {
    const text = await response.text().catch(() => "");
    if (text.trim() === "") return {};
    try {
        const parsed: unknown = JSON.parse(text);
        return typeof parsed === "object" && parsed !== null ? (parsed as ApiErrorData) : null;
    } catch {
        return null;
    }
}
