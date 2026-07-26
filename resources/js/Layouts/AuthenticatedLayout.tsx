import { Link, router, usePage } from '@inertiajs/react';
import {
    BankOutlined,
    DashboardOutlined,
    DollarOutlined,
    HomeOutlined,
    LogoutOutlined,
    SettingOutlined,
} from '@ant-design/icons';
import { ProLayout } from '@ant-design/pro-layout';
import { Dropdown, message } from 'antd';
import { useEffect, type ReactNode } from 'react';
import PropertySwitcher from '@/Components/PropertySwitcher';
import NotificationBell from '@/Components/NotificationBell';
import { useAuth } from '@/hooks/useAuth';
import type { PageProps } from '@/types';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AuthenticatedLayout({ children, title }: AuthenticatedLayoutProps) {
    const { auth, currentHotel, availableHotels, flash } = usePage<PageProps>().props;
    const { can } = useAuth();

    useEffect(() => {
        if (flash.success) {
            message.success(flash.success);
        }
        if (flash.error) {
            message.error(flash.error);
        }
    }, [flash]);

    const menuItems = [
        can('rooms.view') && {
            path: '/rooms',
            name: 'Rooms',
            icon: <HomeOutlined />,
        },
        can('rooms.manage') && {
            path: '/room-types',
            name: 'Room Types',
            icon: <SettingOutlined />,
        },
        can('floors.manage') && {
            path: '/floors',
            name: 'Floors',
            icon: <SettingOutlined />,
        },
        can('hotels.manage') && {
            path: '/admin/hotels',
            name: 'Hotels',
            icon: <BankOutlined />,
        },
        can('currencies.manage') && {
            path: '/admin/currencies',
            name: 'Currencies',
            icon: <DollarOutlined />,
        },
    ].filter(Boolean) as Array<{ path: string; name: string; icon: ReactNode }>;

    return (
        <ProLayout
            title={currentHotel?.name ?? 'Hotel ERP'}
            logo={false}
            layout="mix"
            fixSiderbar
            location={{ pathname: window.location.pathname }}
            route={{
                path: '/',
                routes: [
                    {
                        path: '/dashboard',
                        name: 'Dashboard',
                        icon: <DashboardOutlined />,
                    },
                    ...menuItems,
                ],
            }}
            menuItemRender={(item, dom) => (
                <Link href={item.path || '/dashboard'}>{dom}</Link>
            )}
            actionsRender={() => [
                <PropertySwitcher
                    key="property-switcher"
                    currentHotel={currentHotel}
                    availableHotels={availableHotels}
                />,
                <NotificationBell key="notifications" />,
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
