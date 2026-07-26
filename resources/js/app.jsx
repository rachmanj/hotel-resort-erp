import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, theme as antTheme } from 'antd';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider, useTheme } from './hooks/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Hotel ERP';

export { useTheme };

function AppWrapper({ children }) {
    const { isDark } = useTheme();

    return (
        <ConfigProvider
            theme={{
                algorithm: isDark ? antTheme.darkAlgorithm : antTheme.defaultAlgorithm,
                token: {
                    colorPrimary: '#1677ff',
                    borderRadius: 6,
                },
            }}
        >
            {children}
        </ConfigProvider>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider>
                <AppWrapper>
                    <App {...props} />
                </AppWrapper>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#1677ff',
    },
});