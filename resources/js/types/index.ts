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

export interface ResidentData {
    id: number;
    name: string;
    phone: string;
    occupation: string | null;
    emergency_name: string | null;
    emergency_relation: string | null;
    emergency_phone: string | null;
    is_active: boolean;
    status_label: string;
    status_tone: StatusTone;
    has_ktp: boolean;
    has_duplicate_phone: boolean;
}

export interface ResidentDetailData extends ResidentData {
    email: string | null;
    notes: string | null;
    consented_at: string | null;
    whatsapp_opted_out: boolean;
    created_at: string | null;
}

export interface AttachmentData {
    id: number;
    category: string;
    category_label: string;
    original_name: string;
    mime: string;
    size: number;
    is_sensitive: boolean;
    created_at: string | null;
    /** Tautan bertanda tangan berumur 5 menit. */
    url: string;
}

export interface LeaseDepositSummary {
    amount: number;
    status: 'pending' | 'held' | 'refunded' | 'partially_refunded' | 'forfeited';
    status_label: string;
    status_tone: StatusTone;
}

export interface LeaseData {
    id: number;
    status: 'active' | 'ended' | 'canceled';
    status_label: string;
    status_tone: StatusTone;
    room_id: number;
    room_number: string;
    property_name: string;
    primary_resident_id: number;
    primary_resident_name: string;
    start_date: string;
    end_date: string | null;
    days_remaining: number | null;
    is_overdue: boolean;
    expires_soon: boolean;
    rent_amount: number;
    due_day: number | null;
    ended_reason_label: string | null;
    deposit: LeaseDepositSummary | null;
    can: { check_out: boolean; renew: boolean; transfer: boolean };
}

export interface LeaseDetailData extends LeaseData {
    billing_cycle_label: string;
    checked_in_at: string | null;
    checkin_notes: string | null;
    checked_out_at: string | null;
    checkout_notes: string | null;
    notes: string | null;
    renewed_from_lease_id: number | null;
    residents: { id: number; name: string; phone: string; is_primary: boolean }[];
    deposit_detail: {
        amount: number;
        received_at: string | null;
        deduction_amount: number;
        deduction_note: string | null;
        refund_amount: number;
        refunded_at: string | null;
    } | null;
    transfers: {
        id: number;
        from_room_number: string;
        to_room_number: string;
        transferred_on: string;
        new_rent_amount: number | null;
        reason: string | null;
    }[];
}

export interface ResidentImportResult {
    created: number;
    failures: { row: number; name: string; reason: string }[];
    duplicates: { row: number; name: string }[];
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
            manageSubscription: boolean;
        };
    } | null;
    tenant: { name: string; status: string; suspended: boolean } | null;
    flash: {
        success: string | null;
        invitation_link: string | null;
        import_result: ImportResult | null;
        resident_import_result: ResidentImportResult | null;
    };
    [key: string]: unknown;
}

export interface InvoiceData {
    id: number;
    number: string;
    type: 'recurring' | 'manual';
    type_label: string;
    status: 'draft' | 'issued' | 'partial' | 'paid' | 'overdue' | 'void';
    status_label: string;
    status_tone: StatusTone;
    property_name: string;
    room_number: string | null;
    lease_id: number;
    resident_id: number;
    resident_name: string;
    /** Format YYYY-MM; null untuk tagihan manual. */
    period: string | null;
    issue_date: string;
    due_date: string;
    total: number;
    paid_total: number;
    outstanding: number;
    can: { adjust: boolean; void: boolean };
}

export interface InvoiceDetailData extends InvoiceData {
    period_start: string | null;
    period_end: string | null;
    subtotal: number;
    adjustment_total: number;
    notes: string | null;
    issued_at: string | null;
    paid_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    items: { id: number; type_label: string; description: string; quantity: number; unit_price: number; amount: number }[];
    adjustments: { id: number; amount: number; reason: string; created_by_name: string | null; created_at: string | null }[];
    can_void_now: boolean;
    can_adjust_now: boolean;
}

export interface PaymentAccountData {
    id: number;
    type: 'bank' | 'qris' | 'ewallet';
    type_label: string;
    property_id: number | null;
    property_name?: string | null;
    bank_name: string | null;
    account_number: string | null;
    account_name: string | null;
    instructions: string | null;
    is_default: boolean;
    is_active: boolean;
    label: string;
}

export interface PaymentData {
    id: number;
    receipt_number: string | null;
    resident_id: number;
    resident_name?: string;
    amount: number;
    paid_on: string;
    method: 'cash' | 'transfer' | 'qris' | 'ewallet' | 'other';
    method_label: string;
    reference: string | null;
    notes: string | null;
    account_label?: string | null;
    status: 'active' | 'voided';
    status_label: string;
    status_tone: StatusTone;
    void_reason: string | null;
    voided_at: string | null;
    recorded_by_name?: string | null;
    invoices?: { id: number; number: string | null; amount: number }[];
    can: { void: boolean };
}

export interface AttachmentFileData {
    id: number;
    category: string;
    category_label: string;
    original_name: string;
    mime: string;
    size: number;
    is_sensitive: boolean;
    created_at: string | null;
    /** Tautan bertanda tangan berumur pendek. */
    url: string;
}

export interface PaymentProofData {
    id: number;
    invoice_id: number;
    invoice_number?: string;
    invoice_outstanding?: number;
    room_number?: string | null;
    resident_name?: string;
    amount: number;
    paid_on: string | null;
    sender_note: string | null;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    status_tone: StatusTone;
    created_at: string;
    age_days: number;
    is_stale: boolean;
    reject_reason: string | null;
    reviewed_at: string | null;
    payment_id: number | null;
    file?: AttachmentFileData | null;
    can: { review: boolean };
}

export interface PaymentMethodOption {
    value: string;
    label: string;
}

export interface PaginationData {
    current_page: number;
    last_page: number;
    total: number;
    prev_url: string | null;
    next_url: string | null;
}

export interface ExpenseData {
    id: number;
    expense_category_id: number;
    category_name: string;
    property_id: number | null;
    property_name: string | null;
    amount: number;
    spent_on: string;
    description: string;
    vendor_name: string | null;
    created_by_name: string | null;
    receipts: AttachmentFileData[];
}

export interface ReportColumn {
    key: string;
    label: string;
    type: 'text' | 'date' | 'money' | 'number' | 'percent';
}

export type ReportCell = string | number | null;

export interface ReportTableData {
    key: string;
    title: string;
    columns: ReportColumn[];
    rows: Record<string, ReportCell>[];
    totals: Record<string, ReportCell> | null;
    summary: { label: string; value: ReportCell; type: ReportColumn['type'] }[];
}

export interface DashboardSummary {
    as_of: string;
    occupancy: { total: number; occupied: number; vacant: number; percent: number };
    expiring_leases: {
        count: number;
        within_days: number;
        items: { lease_id: number; resident_name: string; room_number: string; end_date: string; days_left: number }[];
    };
    finance: {
        period: string;
        period_label: string;
        income: number;
        income_count: number;
        arrears_total: number;
        arrears_count: number;
        arrears_buckets: { label: string; count: number; amount: number }[];
    } | null;
}

export interface ComplaintData {
    id: number;
    title: string;
    category: string;
    category_label: string;
    status: string;
    status_label: string;
    status_tone: StatusTone;
    room_id: number;
    room_number: string;
    property_name: string;
    resident_name: string;
    assigned_to: number | null;
    assignee_name: string | null;
    is_stale: boolean;
    created_at: string;
    closed_at: string | null;
}

export interface ComplaintCommentData {
    id: number;
    body: string;
    author_name: string | null;
    author_type: 'staff' | 'resident';
    created_at: string;
}

export interface ComplaintDetailData extends ComplaintData {
    description: string;
    resolved_at: string | null;
    allowed_statuses: { value: string; label: string }[];
    photos: AttachmentFileData[];
    comments: ComplaintCommentData[];
}

export interface MessageTemplateData {
    key: string;
    label: string;
    body: string;
    is_custom: boolean;
    available: boolean;
}

export interface MessageLogData {
    id: number;
    resident_name: string | null;
    /** Nomor sudah disamarkan di server. */
    recipient: string;
    template_name: string | null;
    status: string;
    status_label: string;
    status_tone: StatusTone;
    skip_reason_label: string | null;
    error_code: string | null;
    attempts: number;
    scheduled_for: string | null;
    sent_at: string | null;
    created_at: string;
    can_resend: boolean;
}

export interface PortalShell {
    resident: { name: string };
    business_name: string;
    room_number: string | null;
    property_name: string | null;
    read_only: boolean;
}

export interface PortalInvoiceData {
    id: number;
    number: string;
    period: string | null;
    due_date: string;
    status: string;
    status_label: string;
    status_tone: StatusTone;
    total: number;
    paid_total: number;
    outstanding: number;
    items?: { description: string; amount: number }[];
    has_pending_proof: boolean;
}

export interface PortalPaymentData {
    id: number;
    receipt_number: string;
    amount: number;
    paid_on: string;
    method_label: string;
    periods?: (string | null)[];
}

export interface PortalReceiptData {
    number: string;
    business_name: string | null;
    property_name: string | null;
    resident_name: string;
    room_number: string | null;
    amount: number;
    paid_on: string;
    method_label: string;
    reference: string | null;
    lines: { invoice_number: string | null; amount: number }[];
}

export interface PortalLeaseData {
    room_number: string | null;
    room_type: string | null;
    property_name: string | null;
    property_address: string | null;
    start_date: string;
    end_date: string | null;
    days_remaining: number | null;
    rent_amount: number;
    billing_cycle_label: string;
    due_day: number | null;
    deposit_amount: number | null;
}

export interface PortalComplaintData {
    id: number;
    title: string;
    description: string;
    category_label: string;
    status: string;
    status_label: string;
    status_tone: StatusTone;
    is_closed: boolean;
    created_at: string;
    closed_at: string | null;
    comments?: { id: number; body: string; from_resident: boolean; author_name: string; created_at: string }[];
}

export interface PlanData {
    id: number;
    key: string;
    name: string;
    price_monthly: number;
    price_yearly: number | null;
    max_properties: number | null;
    max_rooms: number | null;
    max_users: number | null;
    monthly_message_quota: number;
    features: string[];
    is_free: boolean;
}

export interface SubscriptionData {
    id: number;
    plan_key: string;
    plan_name: string;
    status: 'trialing' | 'active' | 'past_due' | 'canceled' | 'expired';
    status_label: string;
    billing_period: 'monthly' | 'yearly';
    billing_period_label: string;
    trial_ends_at: string | null;
    current_period_start: string;
    current_period_end: string;
    grace_ends_at: string | null;
}

export interface SubscriptionInvoiceData {
    id: number;
    number: string;
    period_start: string;
    period_end: string;
    amount: number;
    status: 'pending' | 'paid' | 'void';
    status_label: string;
    due_date: string;
    paid_at: string | null;
    payment_method: string | null;
    reference: string | null;
}

export interface TenantUsageData {
    properties: number;
    max_properties: number | null;
    rooms: number;
    max_rooms: number | null;
    users: number;
    max_users: number | null;
    messages: number;
    message_quota: number;
}

export interface AdminTenantRow {
    id: number;
    name: string;
    slug: string;
    status: 'trial' | 'active' | 'suspended' | 'closed';
    status_label: string;
    created_at: string | null;
    plan_name: string;
    subscription_status: SubscriptionData['status'] | null;
    subscription_status_label: string | null;
    period_end: string | null;
    trial_ends_at: string | null;
    grace_ends_at: string | null;
    usage: TenantUsageData;
}
