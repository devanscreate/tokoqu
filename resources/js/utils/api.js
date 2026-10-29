function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return match ? decodeURIComponent(match[3]) : null;
}

export async function ensureCsrfCookie() {
    if (!getCookie('XSRF-TOKEN')) {
        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        } catch {
            // Ignore when offline
        }
    }
}

export async function apiRequest(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
        await ensureCsrfCookie();
    }

    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };

    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    const response = await fetch(`/api/v1${path}`, {
        method,
        credentials: 'same-origin',
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    const isJson = response.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await response.json() : null;

    if (!response.ok) {
        const error = new Error(
            data?.message ||
                (data?.errors ? Object.values(data.errors).flat().join(' ') : `HTTP Error ${response.status}`)
        );
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}

