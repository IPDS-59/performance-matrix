import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { TOURS } from '@/data/tours';

/** The tour of the current page: whether there is one, how to open it, and opening it once by itself. */
export function usePageTour() {
    const page = usePage();
    const key = computed(() => (route().current() as string | undefined) ?? '');
    const tour = computed(() => TOURS[key.value] ?? null);
    const doneNow = ref<string[]>([]);
    const done = computed(() => [...((page.props.tours_done as string[] | undefined) ?? []), ...doneNow.value].includes(key.value));

    function start() {
        if (!tour.value) return;
        const steps = tour.value.steps
            .filter((s) => document.querySelector(s.element) !== null)
            .map((s) => ({ element: s.element, popover: { title: s.title, description: s.description } }));
        if (steps.length === 0) return;

        const tourKey = key.value;
        driver({
            showProgress: true,
            progressText: '{{current}} dari {{total}}',
            nextBtnText: 'Lanjut',
            prevBtnText: 'Kembali',
            doneBtnText: 'Selesai',
            allowClose: true,
            steps,
            onDestroyed: () => {
                if (!doneNow.value.includes(tourKey)) {
                    doneNow.value.push(tourKey);
                    axios.post(route('tour.complete', tourKey)).catch(() => {});
                }
            },
        }).drive();
    }

    // The first visit opens the tour by itself, a moment after the page has drawn. Someone looking at the app as
    // another user ("Lihat sebagai") does not trigger it.
    onMounted(() => {
        if (tour.value && !done.value && !page.props.impersonating) window.setTimeout(start, 900);
    });

    return { hasTour: computed(() => tour.value !== null), start };
}
