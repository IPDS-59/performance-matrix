<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MeetingSummaryTable, { type MeetingSummaryData } from '@/Components/Kinetik/MeetingSummaryTable.vue';
import { Head } from '@inertiajs/vue3';

defineProps<MeetingSummaryData>();
</script>

<template>
    <Head title="Ringkasan Rapat" />
    <AppLayout>
        <template #title>Ringkasan Rapat</template>

        <MeetingSummaryTable :year="year" :month="month" :weeks="weeks" :teams="teams" route-name="team-recap.meetings" />
    </AppLayout>
</template>
