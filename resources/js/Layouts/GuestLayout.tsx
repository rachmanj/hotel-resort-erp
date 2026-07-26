import type { ReactNode } from 'react';

interface GuestLayoutProps {
    children: ReactNode;
}

export default function GuestLayout({ children }: GuestLayoutProps) {
    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'linear-gradient(135deg, #f0f5ff 0%, #ffffff 100%)',
                padding: 24,
            }}
        >
            <div style={{ width: '100%', maxWidth: 420 }}>{children}</div>
        </div>
    );
}
