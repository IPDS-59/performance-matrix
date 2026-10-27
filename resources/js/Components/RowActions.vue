<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Ellipsis } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';

/** The actions of one table row behind a "..." menu, instead of a row of buttons. A destructive action goes last, after a divider. */
export interface RowAction {
    label: string;
    href?: string;
    onSelect?: () => void;
    destructive?: boolean;
    /** Leave the action out (no permission, already done). */
    hidden?: boolean;
}

const props = defineProps<{ items: RowAction[]; label?: string }>();
const shown = computed(() => props.items.filter((i) => !i.hidden));
const regular = computed(() => shown.value.filter((i) => !i.destructive));
const destructive = computed(() => shown.value.filter((i) => i.destructive));

function run(item: RowAction) {
    if (item.href) router.visit(item.href);
    else item.onSelect?.();
}
</script>

<template>
    <DropdownMenu v-if="shown.length">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8" :aria-label="label ?? 'Aksi'"><Ellipsis class="h-4 w-4" aria-hidden="true" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-for="item in regular" :key="item.label" @select="run(item)">{{ item.label }}</DropdownMenuItem>
            <DropdownMenuSeparator v-if="regular.length && destructive.length" />
            <DropdownMenuItem v-for="item in destructive" :key="item.label" variant="destructive" @select="run(item)">{{ item.label }}</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <span v-else class="text-gray-400">—</span>
</template>
