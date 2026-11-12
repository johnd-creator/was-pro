export function haulingPlanStatus(status: string): string {
    return (
        (
            {
                pending_approval: 'Menunggu persetujuan rencana',
                approved: 'Rencana disetujui',
                rejected: 'Rencana ditolak',
                closed: 'Rencana ditutup',
            } as Record<string, string>
        )[status] ?? status
    );
}
export function haulingActualStatus(status: string): string {
    return (
        (
            {
                pending_approval: 'Realisasi menunggu persetujuan',
                approved: 'Realisasi disetujui',
                rejected: 'Realisasi ditolak',
                cancelled: 'Realisasi dibatalkan',
            } as Record<string, string>
        )[status] ?? status
    );
}
export function actualQuantityLimit(
    record: { available_quantity?: number; remaining_quantity: number },
    plan?: { remaining_plan_quantity: number },
): number {
    return Math.max(
        0,
        plan?.remaining_plan_quantity ??
            record.available_quantity ??
            record.remaining_quantity,
    );
}

export function initialActualDate(
    localDate: string,
    planId?: string | null,
    serverDateLimit?: string,
): string {
    if (!planId) return localDate;
    return serverDateLimit ?? '';
}

export function availablePlanRecords<T extends { available_quantity: number }>(
    records: T[],
): T[] {
    return records.filter((record) => record.available_quantity > 0);
}

export function planRecordsForType<
    T extends { available_quantity: number; waste_type_id: string },
>(records: T[], wasteTypeId: string): T[] {
    return availablePlanRecords(records).filter(
        (record) => record.waste_type_id === wasteTypeId,
    );
}

export function availablePlanWasteTypes<
    T extends {
        available_quantity: number;
        waste_type_id: string;
        waste_type: { name: string | null; code: string | null } | null;
    },
>(records: T[]): { id: string; label: string }[] {
    const types = new Map<string, string>();
    for (const record of availablePlanRecords(records)) {
        types.set(
            record.waste_type_id,
            [record.waste_type?.name, record.waste_type?.code]
                .filter(Boolean)
                .join(' • ') || record.waste_type_id,
        );
    }
    return Array.from(types, ([id, label]) => ({ id, label }));
}
