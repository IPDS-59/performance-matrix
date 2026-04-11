declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            auth: {
                user: {
                    id: number;
                    name: string;
                    email: string;
                    role: 'admin' | 'head' | 'staff';
                    email_verified_at?: string;
                };
            };
            flash?: {
                success?: string;
                error?: string;
            };
        };
    }
}
