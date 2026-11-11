interface WasteQuantity {
    unit: string;
    remaining_quantity: number;
}

export function summarizeWasteQuantities(
    records: WasteQuantity[],
): { unit: string; quantity: number }[] {
    const totals = new Map<string, number>();
    for (const record of records) {
        totals.set(
            record.unit,
            (totals.get(record.unit) ?? 0) + record.remaining_quantity,
        );
    }
    return [...totals.entries()]
        .sort(([left], [right]) => left.localeCompare(right, 'id'))
        .map(([unit, quantity]) => ({
            unit,
            quantity: Math.round((quantity + Number.EPSILON) * 100) / 100,
        }));
}

export function compareWasteQuantities(
    left: WasteQuantity,
    right: WasteQuantity,
): number {
    return (
        left.unit.localeCompare(right.unit, 'id') ||
        right.remaining_quantity - left.remaining_quantity
    );
}
