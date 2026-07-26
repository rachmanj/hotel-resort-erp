import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

export function useAuth() {
    const { auth } = usePage<PageProps>().props;

    const can = (permission: string): boolean => auth.permissions.includes(permission);

    const hasRole = (role: string): boolean => auth.roles.includes(role);

    return { auth, can, hasRole };
}
