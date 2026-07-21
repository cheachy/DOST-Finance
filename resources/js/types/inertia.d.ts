import type { User } from './index';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            auth: {
                user: User | null;
            };
            status?: string | null;
        };
    }
}
