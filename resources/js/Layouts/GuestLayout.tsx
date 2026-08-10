import type { ReactNode } from 'react';
import { useTheme } from '@/hooks/useTheme';

interface GuestLayoutProps {
    children: ReactNode;
}

export default function GuestLayout({ children }: GuestLayoutProps) {
    const { isDark } = useTheme();

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: isDark
                    ? 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)'
                    : 'linear-gradient(135deg, #f0f5ff 0%, #ffffff 100%)',
                padding: 24,
            }}
        >
            <div style={{ width: '100%', maxWidth: 420 }}>{children}</div>
        </div>
    );
}
