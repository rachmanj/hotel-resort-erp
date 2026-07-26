import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChartOutlined,
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
    TeamOutlined,
    UserOutlined,
    ClearOutlined,
    HeartOutlined,
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
        can('housekeeping.view') && {
            path: '/housekeeping',
            name: 'HK Status Board',
            icon: <ClearOutlined />,
        },
        can('housekeeping.view') && {
            path: '/housekeeping/assignments',
            name: 'HK Assignments',
            icon: <ClearOutlined />,
        },
        can('fb.view') && {
            path: '/fb/menu',
            name: 'F&B Menu',
            icon: <TagsOutlined />,
        },
        can('fb.view') && {
            path: '/fb/orders',
            name: 'F&B Orders',
            icon: <TagsOutlined />,
        },
        can('fb.view') && {
            path: '/fb/kds',
            name: 'Kitchen Display',
            icon: <TagsOutlined />,
        },
        can('spa.view') && {
            path: '/spa/appointments',
            name: 'Spa Appointments',
            icon: <HeartOutlined />,
        },
        can('spa.view') && {
            path: '/spa/treatments',
            name: 'Spa Treatments',
            icon: <HeartOutlined />,
        },
        can('spa.view') && {
            path: '/spa/therapists',
            name: 'Spa Therapists',
            icon: <HeartOutlined />,
        },
        can('spa.view') && {
            path: '/spa/therapists/schedules',
            name: 'Therapist Schedules',
            icon: <HeartOutlined />,
        },
        can('inventory.view') && {
            path: '/inventory',
            name: 'Inventory',
            icon: <SettingOutlined />,
        },
        can('purchasing.view') && {
            path: '/purchasing/requisitions',
            name: 'Purchasing',
            icon: <SettingOutlined />,
        },
        can('maintenance.view') && {
            path: '/maintenance/requests',
            name: 'Maintenance',
            icon: <SettingOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/chart-of-accounts',
            name: 'Chart of Accounts',
            icon: <BankOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/journal-entries',
            name: 'Journal Entries',
            icon: <BankOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/general-ledger',
            name: 'General Ledger',
            icon: <BankOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/reports/trial-balance',
            name: 'Trial Balance',
            icon: <DollarOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/reports/income-statement',
            name: 'Income Statement',
            icon: <DollarOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/reports/balance-sheet',
            name: 'Balance Sheet',
            icon: <DollarOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/reports/cash-flow',
            name: 'Cash Flow',
            icon: <DollarOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/receivables',
            name: 'AR Invoices',
            icon: <BankOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/payables',
            name: 'AP Invoices',
            icon: <BankOutlined />,
        },
        can('accounting.manage') && {
            path: '/accounting/bank-reconciliation',
            name: 'Bank Reconciliation',
            icon: <BankOutlined />,
        },
        can('accounting.manage') && {
            path: '/accounting/fixed-assets',
            name: 'Fixed Assets',
            icon: <BankOutlined />,
        },
        can('accounting.manage') && {
            path: '/accounting/budgets',
            name: 'Budgets',
            icon: <DollarOutlined />,
        },
        can('accounting.view') && {
            path: '/accounting/tax',
            name: 'Tax Reports',
            icon: <DollarOutlined />,
        },
        can('reports.view') && {
            path: '/reports/daily-revenue',
            name: 'Daily Revenue',
            icon: <BarChartOutlined />,
        },
        can('reports.view') && {
            path: '/reports/occupancy',
            name: 'Occupancy',
            icon: <BarChartOutlined />,
        },
        can('reports.view') && {
            path: '/reports/adr-revpar',
            name: 'ADR / RevPAR',
            icon: <BarChartOutlined />,
        },
        can('reports.fb_sales') && {
            path: '/reports/fb-sales',
            name: 'F&B Sales',
            icon: <BarChartOutlined />,
        },
        can('reports.view') && {
            path: '/reports/hk-efficiency',
            name: 'HK Efficiency',
            icon: <BarChartOutlined />,
        },
        can('guests.view') && {
            path: '/guests',
            name: 'Guests',
            icon: <UserOutlined />,
        },
        can('companies.view') && {
            path: '/companies',
            name: 'Companies',
            icon: <TeamOutlined />,
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
        can('tax.manage') && {
            path: '/admin/tax-rules',
            name: 'Tax Rules',
            icon: <DollarOutlined />,
        },
        can('admin.manage') && {
            path: '/admin/hotel-settings',
            name: 'Hotel Settings',
            icon: <SettingOutlined />,
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
