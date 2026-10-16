<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

/** A form in a popup, like kipApp. The slot holds the fields; Enter or the button submits. */
withDefaults(defineProps<{
    open: boolean;
    title: string;
    description?: string;
    submitLabel?: string;
    processing?: boolean;
}>(), { submitLabel: 'Simpan', processing: false });

const emit = defineEmits<{ 'update:open': [value: boolean]; submit: [] }>();
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <form class="space-y-3" @submit.prevent="emit('submit')">
                <slot />
                <DialogFooter class="pt-2">
                    <Button type="button" variant="ghost" @click="emit('update:open', false)">Batal</Button>
                    <Button type="submit" :disabled="processing">{{ processing ? 'Menyimpan…' : submitLabel }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
