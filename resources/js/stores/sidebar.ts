import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useSidebarStore = defineStore('sidebar', () => {
    // Desktop: expanded (labels) vs collapsed (icons only).
    const isOpen = ref(true);
    // Mobile: off-canvas drawer visibility.
    const mobileOpen = ref(false);

    function toggle() {
        isOpen.value = !isOpen.value;
    }

    function close() {
        isOpen.value = false;
    }

    function open() {
        isOpen.value = true;
    }

    function openMobile() {
        mobileOpen.value = true;
    }

    function closeMobile() {
        mobileOpen.value = false;
    }

    return { isOpen, mobileOpen, toggle, close, open, openMobile, closeMobile };
});
