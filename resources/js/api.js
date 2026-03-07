const CSRF_HEADER = "X-XSRF-TOKEN";

function getCookie(name) {
    const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
    return match ? decodeURIComponent(match[2]) : null;
}

export async function api(url, options = {}) {
    const headers = {
        Accept: "application/json",
        [CSRF_HEADER]: getCookie("XSRF-TOKEN") ?? "",
        ...options.headers,
    };

    // Only set Content-Type for non-FormData bodies
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
        return;
    }

    const data = await response.json();

    if (!response.ok) {
        const error = new Error(data.message || "Request failed");
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}
