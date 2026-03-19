import type { Auth } from '@/types/auth';

// Simplified route function types to avoid conflicts with Wayfinder
type RouteParams =
    | Record<string, unknown>
    | string[]
    | string
    | number
    | undefined;

declare global {
    interface Window {
        route?: {
            (name: string, params?: RouteParams, absolute?: boolean): string;
            current?: (name: string, params?: RouteParams) => boolean;
        };
    }
}

// Extend Vue ComponentCustomProperties
declare module 'vue' {
    interface ComponentCustomProperties {
        route?: {
            (name: string, params?: RouteParams, absolute?: boolean): string;
            current?: (name: string, params?: RouteParams) => boolean;
        };
    }
}

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
