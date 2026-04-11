import type { User } from '.';

declare module '@inertiajs/vue3' {
    interface PageProps {
        auth: {
            user: User;
        };
        flash?: {
            success?: string;
            error?: string;
        };
    }
}
