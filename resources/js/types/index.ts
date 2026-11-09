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

export interface RoomData {
    id: number;
    number: string;
    floor: number | null;
    price: number;
    status: 'vacant' | 'occupied' | 'maintenance';
    status_label: string;
    status_tone: StatusTone;
    notes: string | null;
    property_id: number;
    property_name: string;
    room_type_id: number | null;
    room_type_name: string | null;
}

export interface PropertyData {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    floors: number | null;
    notes: string | null;
    is_active: boolean;
    rooms_count?: number;
}

export interface RoomTypeData {
    id: number;
    property_id: number | null;
    property_name?: string | null;
    name: string;
    base_price: number;
    size_m2: number | null;
    facilities: string[];
    description: string | null;
    is_active: boolean;
    rooms_count?: number;
}

export interface ImportResult {
    created: number;
    failures: { row: number; number: string; reason: string }[];
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
    flash: { success: string | null; invitation_link: string | null; import_result: ImportResult | null };
    [key: string]: unknown;
}
