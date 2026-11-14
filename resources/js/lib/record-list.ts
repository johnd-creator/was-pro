export type RecordSort = 'latest' | 'oldest' | 'quantity_high' | 'quantity_low';
export interface RecordListState {
    month: string;
    attention: string;
    search: string;
    sort: RecordSort;
    page: number;
}
export interface RecordPaginator<T> {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
}
interface SearchableRecord {
    id: string;
    record_number: string;
    date: string;
    created_at: string;
    quantity: number | string;
    source?: string | null;
    source_name?: string | null;
    waste_type?: { name: string; code: string };
}
export const recordContractHeaders = { 'X-Waspro-Records-Contract': '2' };
const sorts: RecordSort[] = [
    'latest',
    'oldest',
    'quantity_high',
    'quantity_low',
];
const attentions = [
    'all',
    'expired',
    'expiring_soon',
    'critical',
    'backlog',
    'pending',
    'fresh',
    'completed',
];

// The URL is authoritative even when old servers omit search and sort props.
export function parseRecordListState(
    url: string,
    filters: { month: string; attention?: string | null },
): RecordListState {
    const query = new URL(url, 'http://localhost').searchParams;
    const sort = query.get('sort') as RecordSort;
    const attention = query.get('attention') ?? filters.attention ?? 'all';
    const page = Number(query.get('page') ?? 1);
    return {
        month: query.get('month') || filters.month,
        attention: attentions.includes(attention) ? attention : 'all',
        search: query.get('search') ?? '',
        sort: sorts.includes(sort) ? sort : 'latest',
        page: Number.isSafeInteger(page) && page > 0 ? page : 1,
    };
}
export function recordListUrl(base: string, state: RecordListState): string {
    const url = new URL(base, 'http://localhost');
    url.searchParams.set('month', state.month);
    url.searchParams.set('sort', state.sort);
    url.searchParams.set('page', String(state.page));
    if (state.attention !== 'all')
        url.searchParams.set('attention', state.attention);
    else url.searchParams.delete('attention');
    if (state.search.trim())
        url.searchParams.set('search', state.search.trim());
    else url.searchParams.delete('search');
    return `${url.pathname}${url.search}${url.hash}`;
}
export function changeRecordListState(
    state: RecordListState,
    changes: Partial<RecordListState>,
): RecordListState {
    return { ...state, page: 1, ...changes };
}

// Both server generations apply month, attention and access scope before serialization.
// Never slice a paginator again; legacy arrays must be searched before pagination.
export function recordListPage<T extends SearchableRecord>(
    records: T[] | RecordPaginator<T>,
    state: RecordListState,
    perPage = 10,
): RecordPaginator<T> {
    if (!Array.isArray(records)) return records;
    const search = state.search.trim().toLowerCase();
    const filtered = records.filter((record) =>
        [
            record.record_number,
            record.source,
            record.source_name,
            record.waste_type?.name,
            record.waste_type?.code,
        ].some((value) => value?.toLowerCase().includes(search)),
    );
    const compare = (a: string, b: string) => (a < b ? -1 : a > b ? 1 : 0);
    filtered.sort((a, b) => {
        const date = compare(a.date, b.date);
        if (state.sort === 'quantity_high' || state.sort === 'quantity_low') {
            const quantity = Number(a.quantity) - Number(b.quantity);
            return (
                (state.sort === 'quantity_high' ? -quantity : quantity) ||
                -date ||
                compare(a.id, b.id)
            );
        }
        const order = date || compare(a.created_at, b.created_at);
        return (
            (state.sort === 'oldest' ? order : -order) || compare(a.id, b.id)
        );
    });
    const total = filtered.length;
    const last_page = Math.max(1, Math.ceil(total / perPage));
    const current_page = Math.min(state.page, last_page);
    const offset = (current_page - 1) * perPage;
    return {
        data: filtered.slice(offset, offset + perPage),
        total,
        current_page,
        last_page,
        from: total ? offset + 1 : null,
        to: total ? Math.min(offset + perPage, total) : null,
    };
}

export function recordNavigationHeaders(
    href: string | { url: string; method: string } | undefined,
    indexUrl: string,
    origin: string,
    method = 'get',
    headers?: Record<string, string>,
): Record<string, string> | undefined {
    if (!href) return headers;
    const effectiveMethod = typeof href === 'string' ? method : href.method;
    if (effectiveMethod.toLowerCase() !== 'get') return headers;
    try {
        const target = new URL(
            typeof href === 'string' ? href : href.url,
            origin,
        );
        const index = new URL(indexUrl, origin);
        if (
            target.origin !== new URL(origin).origin ||
            target.pathname !== index.pathname
        )
            return headers;
        if (
            Object.keys(headers ?? {}).some(
                (key) => key.toLowerCase() === 'x-waspro-records-contract',
            )
        )
            return headers;
        return { ...headers, ...recordContractHeaders };
    } catch {
        return headers;
    }
}
