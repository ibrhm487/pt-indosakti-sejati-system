import '../css/app.css';
import './bootstrap';

import { installGlobalToastListeners } from '@/lib/toast';
import { createInertiaApp } from '@inertiajs/react';
import { StrictMode, type ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'IEMS';

installGlobalToastListeners();

const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const loader = pages[`./Pages/${name}.tsx`];

        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return loader().then((page) => page.default);
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: '#D91E2A',
    },
});
