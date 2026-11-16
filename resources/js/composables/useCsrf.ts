/**
 * The CSRF token for requests to Kinetik itself.
 *
 * Why not the XSRF-TOKEN cookie: the BPS webhosting load balancer marks every cookie HttpOnly, so no script can read
 * it. The server puts the token in a meta tag and in each Inertia page (props.csrf_token); this keeps the meta tag
 * current (the token changes at login) and adds the X-CSRF-TOKEN header to same-origin requests.
 */

const META = 'meta[name="csrf-token"]';

export function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>(META)?.content ?? '';
}

/** Store the token of the page just shown. Ignores anything that is not a non-empty string. */
export function rememberCsrfToken(token: unknown): void {
    if (typeof token !== 'string' || token === '') return;
    let meta = document.querySelector<HTMLMetaElement>(META);
    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'csrf-token';
        document.head.appendChild(meta);
    }
    meta.content = token;
}

export function isSameOrigin(url: string | URL | undefined): boolean {
    if (url === undefined) return true;
    try {
        return new URL(url.toString(), window.location.href).origin === window.location.origin;
    } catch {
        return false;
    }
}

/** Adds X-CSRF-TOKEN to a same-origin request config that does not carry one yet. */
export function withCsrfHeader<T extends { url?: string | URL; headers?: Record<string, unknown> }>(config: T): T {
    const headers = config.headers ?? {};
    const token = csrfToken();
    const already = Object.keys(headers).some((h) => h.toLowerCase() === 'x-csrf-token');
    if (!token || already || !isSameOrigin(config.url)) return config;

    return { ...config, headers: { ...headers, 'X-CSRF-TOKEN': token } };
}
