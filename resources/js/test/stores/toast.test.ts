import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useToastStore } from '@/stores/toast';

describe('useToastStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts with no toasts', () => {
        const toast = useToastStore();
        expect(toast.toasts).toHaveLength(0);
    });

    it('add() pushes a toast with the given message and variant', () => {
        const toast = useToastStore();
        toast.add('Hello', 'success');

        expect(toast.toasts).toHaveLength(1);
        expect(toast.toasts[0].message).toBe('Hello');
        expect(toast.toasts[0].variant).toBe('success');
        expect(toast.toasts[0].id).toBeTruthy();
    });

    it('add() defaults variant to info', () => {
        const toast = useToastStore();
        toast.add('Info message');

        expect(toast.toasts[0].variant).toBe('info');
    });

    it('remove() removes a toast by id', () => {
        const toast = useToastStore();
        toast.add('to remove', 'error');
        const id = toast.toasts[0].id;

        toast.remove(id);
        expect(toast.toasts).toHaveLength(0);
    });

    it('remove() ignores unknown ids', () => {
        const toast = useToastStore();
        toast.add('keep me');

        toast.remove('nonexistent-id');
        expect(toast.toasts).toHaveLength(1);
    });

    it('auto-removes after 4 seconds', () => {
        const toast = useToastStore();
        toast.add('temporary');
        expect(toast.toasts).toHaveLength(1);

        vi.advanceTimersByTime(4000);
        expect(toast.toasts).toHaveLength(0);
    });

    it('does not remove before 4 seconds', () => {
        const toast = useToastStore();
        toast.add('still here');

        vi.advanceTimersByTime(3999);
        expect(toast.toasts).toHaveLength(1);
    });

    it('success() adds a success toast', () => {
        const toast = useToastStore();
        toast.success('Saved!');

        expect(toast.toasts[0].message).toBe('Saved!');
        expect(toast.toasts[0].variant).toBe('success');
    });

    it('error() adds an error toast', () => {
        const toast = useToastStore();
        toast.error('Failed!');

        expect(toast.toasts[0].message).toBe('Failed!');
        expect(toast.toasts[0].variant).toBe('error');
    });

    it('multiple toasts stack and auto-remove independently', () => {
        const toast = useToastStore();
        toast.add('first');
        toast.add('second');
        expect(toast.toasts).toHaveLength(2);

        vi.advanceTimersByTime(4000);
        expect(toast.toasts).toHaveLength(0);
    });
});
