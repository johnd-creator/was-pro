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

export function planRecordsForPeriod<T extends { date: string | null }>(
    records: T[],
    period: string,
): T[] {
    return period
        ? records.filter((record) => record.date?.slice(0, 7) === period)
        : records;
}

export function planRecordsForDateRange<T extends { date?: string | null }>(
    records: T[],
    start: string,
    end: string,
): T[] {
    if (start && end && start > end) return [];
    if (!start && !end) return records;
    return records.filter(
        (record) =>
            !!record.date &&
            (!start || record.date >= start) &&
            (!end || record.date <= end),
    );
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
