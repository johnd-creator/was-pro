export interface OperationalAlert {
    id: string;
    title: string;
    description: string;
    count: number;
    source_url: string;
    tone: 'red' | 'orange' | 'blue' | 'green';
}

export interface NotificationSummary {
    pending_hauling_plans_count?: number;
    pending_hauling_approvals_count?: number;
    total_count: number;
    expired_waste_count: number;
    expiring_soon_waste_count: number;
    pending_waste_approvals_count: number;
    pending_faba_approvals_count: number;
    faba_warnings_count: number;
}

export interface NotificationContext {
    month: string;
    as_of: string;
    organization_id: string | null;
}
