import { Link, router, usePage } from '@inertiajs/react';
import {
    BankOutlined,
    BulbOutlined,
    BulbFilled,
    CalendarOutlined,
    DashboardOutlined,
    DollarOutlined,
    HomeOutlined,
    LogoutOutlined,
    SendOutlined,
    SettingOutlined,
    TagsOutlined,
} from '@ant-design/icons';
import { ProLayout } from '@ant-design/pro-layout';
import { Button, Dropdown, message } from 'antd';
import { useEffect, type ReactNode } from 'react';
import PropertySwitcher from '@/Components/PropertySwitcher';
import NotificationBell from '@/Components/NotificationBell';
import { useAuth } from '@/hooks/useAuth';
import { useTheme } from '@/hooks/useTheme';
import type { PageProps } from '@/types';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AuthenticatedLayout({ children, title }: AuthenticatedLayoutProps) {
    const { auth, currentHotel, availableHotels, flash } = usePage<PageProps>().props;
    const { can } = useAuth();
    const { isDark, toggleTheme } = useTheme();

    useEffect(() => {
        if (flash.success) {
            message.success(flash.success);
        }
        if (flash.error) {
            message.error(flash.error);
        }
    }, [flash]);

    const menuItems = [
        can('reservations.view') && {
            path: '/reservations',
            name: 'Reservations',
            icon: <CalendarOutlined />,
        },
        can('reservations.view') && {
            path: '/reservations/calendar',
            name: 'Calendar',
            icon: <CalendarOutlined />,
        },
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
        can('rates.manage') && {
            path: '/admin/rate-plans',
            name: 'Rate Plans',
            icon: <TagsOutlined />,
        },
        can('seasons.manage') && {
            path: '/admin/seasons',
            name: 'Seasons',
            icon: <TagsOutlined />,
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
                <Button
                    key="theme-toggle"
                    type="text"
                    icon={isDark ? <BulbFilled /> : <BulbOutlined />}
                    onClick={toggleTheme}
                    title={isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
                />,
                <Dropdown
                    key="user-menu"
                    menu={{
                        items: [
                            can('profile.telegram.view') && {
                                key: 'telegram',
                                icon: <SendOutlined />,
                                label: (
                                    <Link href="/profile/telegram">Telegram Link</Link>
                                ),
                            },
                            {
                                key: 'logout',
                                icon: <LogoutOutlined />,
                                label: 'Logout',
                                onClick: () => router.post('/logout'),
                            },
                        ].filter(Boolean),
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
