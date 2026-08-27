import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, message, theme as antTheme, Empty } from 'antd';
import enUS from 'antd/locale/en_US';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider, useTheme } from './hooks/useTheme';
import { useEffect } from 'react';
import dayjs from 'dayjs';
import 'dayjs/locale/en';

dayjs.locale('en');

const appName = import.meta.env.VITE_APP_NAME || 'Pratasaba ERP';

export { useTheme };

function AppWrapper({ children }) {
    const { isDark } = useTheme();

    useEffect(() => {
        const handleOffline = () => {
            message.warning('Offline — changes will sync when connection returns.');
        };

        const handleOnline = () => {
            message.success('Back online — syncing…');
        };

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, []);

    return (
        <ConfigProvider
            locale={enUS}
            theme={{
                algorithm: isDark ? antTheme.darkAlgorithm : antTheme.defaultAlgorithm,
                token: {
                    colorPrimary: '#1677ff',
                    borderRadius: 6,
                    controlHeight: 44,
                },
            }}
            renderEmpty={() => (
                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="No records yet" />
            )}
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