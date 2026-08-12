import { Link, router, usePage } from '@inertiajs/react';
import { CalendarOutlined, LogoutOutlined } from '@ant-design/icons';
import { ProLayout } from '@ant-design/pro-layout';
import { Button, Dropdown } from 'antd';
import type { ReactNode } from 'react';
import type { PageProps } from '@/types';

interface AgentPortalLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AgentPortalLayout({ children, title }: AgentPortalLayoutProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <ProLayout
            title="Agent Portal"
            logo={false}
            layout="mix"
            fixSiderbar
            location={{ pathname: window.location.pathname }}
            route={{
                path: '/',
                routes: [
                    {
                        path: '/agent-portal/bookings',
                        name: 'My Bookings',
                        icon: <CalendarOutlined />,
                    },
                ],
            }}
            menuItemRender={(item, dom) => <Link href={item.path || '/agent-portal/bookings'}>{dom}</Link>}
            actionsRender={() => [
                <Dropdown
                    key="user-menu"
                    menu={{
                        items: [
                            {
                                key: 'logout',
                                icon: <LogoutOutlined />,
                                label: 'Logout',
                                onClick: () => router.post('/logout'),
                            },
                        ],
                    }}
                >
                    <span style={{ cursor: 'pointer', padding: '0 12px' }}>{auth.user?.name}</span>
                </Dropdown>,
            ]}
        >
            <div style={{ padding: 24 }}>
                {title && <h1 style={{ marginBottom: 24 }}>{title}</h1>}
                {children}
            </div>
        </ProLayout>
    );
}
