import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useSidebarStore } from '@/stores/sidebar';

describe('useSidebarStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('is open by default', () => {
        const sidebar = useSidebarStore();
        expect(sidebar.isOpen).toBe(true);
    });

    it('toggle() closes when open', () => {
        const sidebar = useSidebarStore();
        sidebar.toggle();
        expect(sidebar.isOpen).toBe(false);
    });

    it('toggle() opens when closed', () => {
        const sidebar = useSidebarStore();
        sidebar.toggle(); // close
        sidebar.toggle(); // open
        expect(sidebar.isOpen).toBe(true);
    });

    it('close() sets isOpen to false', () => {
        const sidebar = useSidebarStore();
        sidebar.close();
        expect(sidebar.isOpen).toBe(false);
    });

    it('close() is idempotent', () => {
        const sidebar = useSidebarStore();
        sidebar.close();
        sidebar.close();
        expect(sidebar.isOpen).toBe(false);
    });

    it('open() sets isOpen to true', () => {
        const sidebar = useSidebarStore();
        sidebar.close();
        sidebar.open();
        expect(sidebar.isOpen).toBe(true);
    });

    it('open() is idempotent', () => {
        const sidebar = useSidebarStore();
        sidebar.open();
        sidebar.open();
        expect(sidebar.isOpen).toBe(true);
    });
});
