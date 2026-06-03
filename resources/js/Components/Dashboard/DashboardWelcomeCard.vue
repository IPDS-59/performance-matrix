<script setup lang="ts">
import type { Employee } from '@/types';

defineProps<{
    employee: Employee;
    periodLabel: string;
}>();
</script>

<template>
    <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
            {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
        </div>
        <div>
            <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
            <p class="text-sm text-gray-500">{{ periodLabel }}</p>
        </div>
    </div>
</template>
