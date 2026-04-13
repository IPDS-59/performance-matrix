<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'destructive' | 'warning';
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
    cancel: [];
}>();

function onConfirm() {
    emit('confirm');
    emit('update:open', false);
}

function onCancel() {
    emit('cancel');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-4">
                    <!-- Warning icon -->
                    <div :class="[
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        variant === 'warning' ? 'bg-yellow-100' : 'bg-red-100',
                    ]">
                        <svg
                            :class="['h-5 w-5', variant === 'warning' ? 'text-yellow-600' : 'text-red-600']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <DialogTitle class="text-base font-semibold leading-tight">
                            {{ title ?? 'Konfirmasi' }}
                        </DialogTitle>
                        <DialogDescription class="mt-1 text-sm text-gray-500">
                            {{ description ?? 'Tindakan ini tidak dapat dibatalkan. Apakah Anda yakin?' }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="onCancel">
                    {{ cancelLabel ?? 'Batal' }}
                </Button>
                <Button
                    type="button"
                    :variant="variant === 'warning' ? 'default' : 'destructive'"
                    @click="onConfirm"
                >
                    {{ confirmLabel ?? 'Hapus' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
