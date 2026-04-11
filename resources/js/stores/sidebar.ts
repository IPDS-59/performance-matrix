import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useSidebarStore = defineStore('sidebar', () => {
    const isOpen = ref(true);

    function toggle() {
        isOpen.value = !isOpen.value;
    }

    function close() {
        isOpen.value = false;
    }

    function open() {
        isOpen.value = true;
    }

    return { isOpen, toggle, close, open };
});
