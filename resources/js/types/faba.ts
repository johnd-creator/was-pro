export interface FabaUserRef {
    id: string;
    name: string;
}

export interface FabaMonthlyApproval {
    id?: string | null;
    year: number;
    month: number;
    period_label?: string;
    status: 'draft' | 'submitted' | 'approved' | 'rejected';
    rejection_note?: string | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    submitted_by_user?: FabaUserRef | null;
    approved_by_user?: FabaUserRef | null;
    rejected_by_user?: FabaUserRef | null;
    can_submit?: boolean;
    can_approve?: boolean;
    can_reject?: boolean;
    can_review?: boolean;
    can_reopen?: boolean;
}

export interface FabaWarning {
    code: string;
    message: string;
}

export interface FabaProductionEntry {
    id: string;
    entry_number: string;
    transaction_date: string;
    material_type: 'fly_ash' | 'bottom_ash';
    entry_type: 'production' | 'pok' | 'workshop' | 'reject';
    quantity: number;
    unit: string;
    note: string | null;
    approval_status: 'draft' | 'submitted' | 'approved' | 'rejected';
    period_label?: string;
    created_by_user?: FabaUserRef | null;
}

export interface FabaVendor {
    id: string;
    name: string;
}

export interface FabaUtilizationEntry {
    id: string;
    entry_number: string;
    transaction_date: string;
    material_type: 'fly_ash' | 'bottom_ash';
    utilization_type: 'external' | 'internal';
    vendor_id: string | null;
    vendor?: FabaVendor | null;
    quantity: number;
    unit: string;
    document_number: string | null;
    document_date: string | null;
    attachment_path: string | null;
    note: string | null;
    approval_status: 'draft' | 'submitted' | 'approved' | 'rejected';
    period_label?: string;
    created_by_user?: FabaUserRef | null;
}

export interface FabaMonthlyRecap {
    year: number;
    month: number;
    period_label: string;
    production_fly_ash: number;
    production_bottom_ash: number;
    utilization_fly_ash: number;
    utilization_bottom_ash: number;
    opening_fly_ash: number;
    opening_bottom_ash: number;
    closing_fly_ash: number;
    closing_bottom_ash: number;
    total_production: number;
    total_utilization: number;
    opening_balance: number;
    closing_balance: number;
    warning_negative_balance: boolean;
    warning_utilization_without_production: boolean;
    warning_missing_opening_balance: boolean;
    production_entries_count: number;
    utilization_entries_count: number;
    approval?: FabaMonthlyApproval | null;
    warnings: FabaWarning[];
}

export interface FabaPeriodSummary {
    year: number;
    month: number;
    period_label: string;
    status: 'draft' | 'submitted' | 'approved' | 'rejected';
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_review: boolean;
    can_reopen: boolean;
    recap: FabaMonthlyRecap;
}

export interface FabaAuditLog {
    id: string;
    action: string;
    module: string;
    summary: string;
    details?: Record<string, unknown> | null;
    actor?: FabaUserRef | null;
    created_at?: string | null;
}
