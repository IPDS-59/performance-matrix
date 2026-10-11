<script setup lang="ts">
import { provide } from "vue"
import { collectingKey } from "./context"

// Renders the items hidden so the trigger knows each value's label while the list is closed.
provide(collectingKey, true)
</script>

<template>
  <div hidden aria-hidden="true">
    <slot />
  </div>
</template>
