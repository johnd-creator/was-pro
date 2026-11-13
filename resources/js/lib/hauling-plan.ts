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

export function planQuantityError(
    value: string | number,
    record?: { available_quantity: number; unit: string },
): string {
    if (!record) return 'Pilih batch catatan limbah terlebih dahulu.';
    const input = String(value).trim();
    if (!input) return 'Jumlah rencana wajib diisi.';
    if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(input)) {
        return 'Jumlah rencana harus berupa angka positif dengan maksimal dua angka desimal.';
    }
    const amount = Math.round(Number(input) * 100);
    if (amount < 1) return 'Jumlah rencana minimal 0,01.';
    const available = Math.max(0, Math.round(record.available_quantity * 100));
    if (!Number.isFinite(available) || amount > available) {
        const limit = Number.isFinite(available) ? available / 100 : 0;
        return `Jumlah rencana melebihi sisa yang dapat dialokasikan (${limit.toLocaleString('id-ID', { maximumFractionDigits: 2 })} ${record.unit}). Periksa batch dan satuannya.`;
    }
    return '';
}
