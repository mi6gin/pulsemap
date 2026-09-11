function cookie(name) {
    const prefix = `${name}=`;
    const value = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(prefix));

    return value ? decodeURIComponent(value.slice(prefix.length)) : null;
}

export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

export async function csrf() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
}

export async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...options.headers,
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const xsrfToken = cookie('XSRF-TOKEN');
    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers,
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new ApiError(
            payload.message || 'Не удалось выполнить запрос.',
            response.status,
            payload.errors || {},
        );
    }

    return payload;
}
