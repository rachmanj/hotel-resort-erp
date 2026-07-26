export interface User {
    id: number;
    name: string;
    email: string;
}

export interface Hotel {
    id: number;
    name: string;
    logo_path?: string | null;
    currency: string;
    code?: string;
}

export interface Auth {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export interface PageProps {
    auth: Auth;
    currentHotel: Hotel | null;
    availableHotels: Hotel[];
    currencies: Array<{ code: string; symbol: string; name: string }>;
    flash: {
        success?: string | null;
        error?: string | null;
    };
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
