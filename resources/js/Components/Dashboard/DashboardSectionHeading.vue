<script setup lang="ts">
// Decorative centered section heading with primary dividers on both sides.
</script>

<template>
    <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
        <span class="h-px flex-1 bg-primary/20"></span>
        <slot />
        <span class="h-px flex-1 bg-primary/20"></span>
    </h2>
</template>
