import { describe, it, expect } from 'vitest';
import { useAsync } from '@/composables/useAsync';

describe('useAsync', () => {
    it('starts with loading false and no error', () => {
        const { loading, error } = useAsync();

        expect(loading.value).toBe(false);
        expect(error.value).toBeNull();
    });

    it('sets loading true while running and false after', async () => {
        const { loading, run } = useAsync<string>();

        const promise = run(() => Promise.resolve('done'));
        expect(loading.value).toBe(true);

        await promise;
        expect(loading.value).toBe(false);
    });

    it('returns the resolved value', async () => {
        const { run } = useAsync<number>();

        const result = await run(() => Promise.resolve(42));
        expect(result).toBe(42);
    });

    it('captures Error message on failure', async () => {
        const { error, run } = useAsync();

        await run(() => Promise.reject(new Error('something went wrong')));

        expect(error.value).toBe('something went wrong');
    });

    it('uses fallback message for non-Error rejections', async () => {
        const { error, run } = useAsync();

        await run(() => Promise.reject('raw string error'));

        expect(error.value).toBe('Terjadi kesalahan.');
    });

    it('clears error on subsequent successful run', async () => {
        const { error, run } = useAsync<string>();

        await run(() => Promise.reject(new Error('first error')));
        expect(error.value).toBe('first error');

        await run(() => Promise.resolve('ok'));
        expect(error.value).toBeNull();
    });

    it('returns undefined when the fn rejects', async () => {
        const { run } = useAsync<string>();

        const result = await run(() => Promise.reject(new Error('fail')));
        expect(result).toBeUndefined();
    });
});
