/**
 * Appels JSON hors Inertia — le protocole de dépôt par morceaux. Le jeton CSRF
 * est relu dans le cookie XSRF-TOKEN que Laravel pose à chaque réponse.
 */
function csrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function request(url, { method = 'GET', body, headers = {}, signal } = {}) {
    const response = await fetch(url, {
        method,
        body,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
            ...headers,
        },
    });

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;

        throw new HttpError(
            response.status,
            firstError ?? data.message ?? `Erreur ${response.status}`,
            data.errors ?? {},
        );
    }

    return data;
}

export function postJson(url, payload, options = {}) {
    return request(url, {
        method: 'POST',
        body: JSON.stringify(payload),
        headers: { 'Content-Type': 'application/json' },
        ...options,
    });
}
