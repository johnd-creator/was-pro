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
    return serverDateLimit
        ? localDate <= serverDateLimit
            ? localDate
            : serverDateLimit
        : '';
}
