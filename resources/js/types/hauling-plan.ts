export interface HaulingPlanRecord {
    waste_type_id: string;
    waste_type: { name: string | null; code: string | null } | null;
    id: string;
    record_number: string;
    unit: string;
    quantity: number;
    remaining_quantity: number;
    available_quantity: number;
}
export interface HaulingPlan {
    id: string;
    waste_record_id: string;
    record_number: string;
    planned_date: string;
    quantity: number;
    unit: string;
    notes: string | null;
    status: string;
    created_by: { id: string | number; name: string } | null;
    approved_by: { id: string | number; name: string } | null;
    submitted_at: string | null;
    approved_at: string | null;
    approval_notes: string | null;
    rejection_reason: string | null;
    closed_at: string | null;
    closed_by?: { id: string | number; name: string } | null;
    closure_reason: string | null;
    approved_actual_quantity: number;
    pending_actual_quantity: number;
    remaining_plan_quantity: number;
    abilities: {
        can_approve: boolean;
        can_reject: boolean;
        can_close: boolean;
        can_realize: boolean;
    };
    actuals: {
        id: string;
        hauling_number: string;
        can_view?: boolean;
        hauling_date: string;
        quantity: number;
        unit: string;
        status: string;
    }[];
}

export interface HaulingWasteType {
    name: string | null;
    code: string | null;
    category: { name: string | null } | null;
}

export interface HaulingRecord {
    id: string;
    record_number: string;
    date: string;
    expiry_date: string | null;
    quantity: number;
    unit: string;
    source: string | null;
    approved_hauled_quantity: number;
    remaining_quantity: number;
    reserved_quantity: number;
    pending_actual_quantity?: number;
    plan_reserved_quantity?: number;
    available_quantity?: number;
    waste_type: HaulingWasteType;
}

export interface HaulingActualFormProps {
    record: HaulingRecord;
    metadataAvailable?: boolean;
    plansEnabled?: boolean;
    actualDateLimit?: string;
    actualDateTimezone?: string;
    approvedPlans?: HaulingPlan[];
    selectedPlanId?: string | null;
    vendors?: {
        id: string;
        name: string;
        license_number?: string | null;
        license_expiry_date?: string | null;
    }[];
}
