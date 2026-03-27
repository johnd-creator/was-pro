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
    operational_status?: 'draft' | 'open' | 'ready_to_submit' | 'submitted' | 'approved';
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

export interface FabaVendor {
    id: string;
    name: string;
}

export interface FabaProductionMovement {
    id: string;
    display_number: string;
    transaction_date: string;
    material_type: 'fly_ash' | 'bottom_ash';
    movement_type: 'production' | 'disposal_pok' | 'workshop' | 'reject';
    quantity: number;
    unit: string;
    note: string | null;
    approval_status: 'draft' | 'submitted' | 'approved' | 'rejected';
    period_label?: string;
    created_by_user?: FabaUserRef | null;
}

export interface FabaInternalDestination {
    id: string;
    name: string;
}

export interface FabaPurpose {
    id: string;
    name: string;
}

export interface FabaUtilizationMovement {
    id: string;
    display_number: string;
    transaction_date: string;
    material_type: 'fly_ash' | 'bottom_ash';
    movement_type: 'utilization_external' | 'utilization_internal';
    vendor_id: string | null;
    vendor?: FabaVendor | null;
    internal_destination_id: string | null;
    internal_destination?: FabaInternalDestination | null;
    purpose_id: string | null;
    purpose?: FabaPurpose | null;
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
    production_movements_count: number;
    utilization_movements_count: number;
    movement_summary?: {
        inflow_fly_ash: number;
        inflow_bottom_ash: number;
        outflow_fly_ash: number;
        outflow_bottom_ash: number;
    };
    approval?: FabaMonthlyApproval | null;
    warnings: FabaWarning[];
}

export interface FabaMovement {
    id: string;
    transaction_date: string;
    material_type: 'fly_ash' | 'bottom_ash';
    movement_type: string;
    stock_effect: 'in' | 'out';
    quantity: number;
    unit: string;
    vendor?: FabaVendor | null;
    internal_destination?: FabaInternalDestination | null;
    purpose?: FabaPurpose | null;
    document_number: string | null;
    document_date: string | null;
    reference_type: string | null;
    reference_id: string | null;
    note: string | null;
    display_number?: string | null;
}

export interface FabaStockCardRow {
    id: string;
    transaction_date: string;
    display_number: string;
    material_type: 'fly_ash' | 'bottom_ash';
    movement_type: string;
    stock_effect: 'in' | 'out';
    quantity: number;
    unit: string;
    vendor_name?: string | null;
    internal_destination_name?: string | null;
    purpose_name?: string | null;
    running_balance: number;
    document_number?: string | null;
}

export interface FabaAnomalyItem {
    year: number;
    month: number;
    period_label: string;
    code: string;
    message: string;
}

export interface FabaClosingSnapshot {
    id: string;
    year: number;
    month: number;
    status: string;
    approved_at: string | null;
    approved_by_user?: FabaUserRef | null;
    warning_summary?: FabaWarning[] | null;
    snapshot_payload?: Record<string, unknown> | null;
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

export interface FabaChartData {
    label: string;
    month: number;
    year: number;
    production: number;
    utilization: number;
    closing_balance: number;
}

export interface WasteChartData {
    label: string;
    month: number;
    year: number;
    records_count: number;
    approved_count: number;
    transport_delivered_count: number;
}
