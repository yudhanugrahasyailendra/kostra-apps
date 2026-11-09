/** Bentuk data yang dikirim server lewat API Resource. Uang selalu integer rupiah. */

export interface UserData {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: 'super_admin' | 'owner' | 'staff';
    role_label: string;
    is_active: boolean;
    last_login_at: string | null;
    permissions: string[];
}

export type StatusTone = 'lunas' | 'menunggu' | 'jatuh-tempo' | 'kosong';

/** Props bersama dari HandleInertiaRequests. `can` hanya untuk menyembunyikan menu. */
export interface SharedProps {
    auth: {
        user: UserData;
        can: {
            manageStaff: boolean;
            viewAudit: boolean;
            viewFinance: boolean;
            manageSettings: boolean;
            exportData: boolean;
        };
    } | null;
    tenant: { name: string; status: string; suspended: boolean } | null;
    flash: { success: string | null; invitation_link: string | null };
    [key: string]: unknown;
}
