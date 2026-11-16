import { afterEach, describe, expect, it } from 'vitest';
import { csrfToken, rememberCsrfToken, withCsrfHeader } from '@/composables/useCsrf';

afterEach(() => document.head.querySelectorAll('meta[name="csrf-token"]').forEach((m) => m.remove()));

describe('csrf token without reading the cookie', () => {
    it('keeps the token of the latest page and creates the meta tag when it is missing', () => {
        expect(csrfToken()).toBe('');
        rememberCsrfToken('first');
        expect(csrfToken()).toBe('first');
        rememberCsrfToken('after-login');
        expect(csrfToken()).toBe('after-login');
        expect(document.head.querySelectorAll('meta[name="csrf-token"]')).toHaveLength(1);
    });

    it('ignores a missing or empty token', () => {
        rememberCsrfToken('kept');
        rememberCsrfToken(undefined);
        rememberCsrfToken('');
        expect(csrfToken()).toBe('kept');
    });

    it('adds the header to same-origin requests only, and never overrides one already set', () => {
        rememberCsrfToken('tok');
        expect(withCsrfHeader({ url: '/sinkron-saya', headers: {} }).headers).toEqual({ 'X-CSRF-TOKEN': 'tok' });
        expect(withCsrfHeader<{ url: string; headers?: Record<string, unknown> }>({ url: `${window.location.origin}/x` }).headers).toEqual({ 'X-CSRF-TOKEN': 'tok' });
        expect(withCsrfHeader({ url: 'https://kipapp.bps.go.id/api', headers: {} }).headers).toEqual({});
        expect(withCsrfHeader({ url: '/x', headers: { 'x-csrf-token': 'own' } }).headers).toEqual({ 'x-csrf-token': 'own' });
    });
});
