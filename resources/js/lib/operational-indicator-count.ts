/** Prefer the authoritative server total; never add its constituent counters again. */
export function operationalIndicatorCount(
    total: number | undefined,
    fallback: number[],
): number {
    if (typeof total === 'number' && Number.isInteger(total) && total >= 0)
        return total;
    return fallback.reduce(
        (sum, count) =>
            sum + (Number.isFinite(count) && count > 0 ? Math.floor(count) : 0),
        0,
    );
}
