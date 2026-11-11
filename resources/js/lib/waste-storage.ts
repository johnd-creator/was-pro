export type WasteStorageStatus =
    | 'completed'
    | 'expired'
    | 'expiring_soon'
    | 'fresh'
    | 'na';

interface WasteStorageState {
    status: WasteStorageStatus;
    label: string;
}

export function getWasteStorageState(
    expiryDate: string | null,
    remainingQuantity?: number,
    isSimulated = false,
    referenceDate = new Date(),
): WasteStorageState {
    if (
        remainingQuantity !== undefined &&
        Number.isFinite(remainingQuantity) &&
        remainingQuantity <= 0
    ) {
        return {
            status: 'completed',
            label: isSimulated
                ? 'Selesai (SIMULASI) · stok nol'
                : 'Selesai · stok nol',
        };
    }

    const parts = expiryDate?.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!parts) {
        return { status: 'na', label: 'N/A' };
    }

    const [year, month, day] = parts.slice(1).map(Number);
    const deadline = new Date(Date.UTC(year, month - 1, day));
    if (
        deadline.getUTCFullYear() !== year ||
        deadline.getUTCMonth() !== month - 1 ||
        deadline.getUTCDate() !== day
    ) {
        return { status: 'na', label: 'N/A' };
    }

    const today = Date.UTC(
        referenceDate.getFullYear(),
        referenceDate.getMonth(),
        referenceDate.getDate(),
    );
    const days = (deadline.getTime() - today) / 86_400_000;

    if (days < 0) {
        return {
            status: 'expired',
            label: `Melewati batas simpan (${Math.abs(days)} hari)`,
        };
    }

    if (days <= 7) {
        return {
            status: 'expiring_soon',
            label: `Mendekati batas simpan (${days} hari)`,
        };
    }

    return { status: 'fresh', label: `Masih aman (${days} hari)` };
}
