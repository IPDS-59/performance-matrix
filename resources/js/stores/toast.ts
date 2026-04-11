import { defineStore } from 'pinia';
import { ref } from 'vue';

export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: string;
    message: string;
    variant: ToastVariant;
}

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);

    function add(message: string, variant: ToastVariant = 'info') {
        const id = Math.random().toString(36).slice(2);
        toasts.value.push({ id, message, variant });
        setTimeout(() => remove(id), 4000);
    }

    function remove(id: string) {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    function success(message: string) {
        add(message, 'success');
    }

    function error(message: string) {
        add(message, 'error');
    }

    return { toasts, add, remove, success, error };
});
