import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import theme from './theme';

createInertiaApp({
    title: (title) => `${title} | OT Safety Gate`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <App {...props} />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#00BCD4',
    },
});
