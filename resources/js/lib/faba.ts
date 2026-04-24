const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function parseDateValue(value: string): Date | null {
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-').map(Number);

        return new Date(year, month - 1, day);
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatFabaDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const parsed = parseDateValue(value);

    return parsed ? dateFormatter.format(parsed) : value;
}

export function formatFabaDateTime(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const parsed = parseDateValue(value);

    return parsed ? dateTimeFormatter.format(parsed) : value;
}

export function formatFabaStatus(value: string): string {
    const labels: Record<string, string> = {
        draft: 'Draft',
        submitted: 'Diajukan',
        pending_approval: 'Menunggu Persetujuan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    };

    return labels[value] ?? value;
}

export function formatFabaMaterial(value: string): string {
    const labels: Record<string, string> = {
        fly_ash: 'Fly Ash',
        bottom_ash: 'Bottom Ash',
    };

    return labels[value] ?? value;
}

export function formatFabaEntryType(value: string): string {
    const labels: Record<string, string> = {
        production: 'Produksi',
        pok: 'POK',
        disposal_pok: 'Disposal / POK',
        workshop: 'Workshop',
        reject: 'Reject',
    };

    return labels[value] ?? value;
}

export function formatFabaUtilizationType(value: string): string {
    const labels: Record<string, string> = {
        internal: 'Internal',
        external: 'Eksternal',
        utilization_internal: 'Internal',
        utilization_external: 'Eksternal',
    };

    return labels[value] ?? value;
}

export function formatFabaMovementType(value: string): string {
    const labels: Record<string, string> = {
        opening_balance: 'Opening Balance',
        production: 'Produksi',
        workshop: 'Workshop',
        utilization_external: 'Pemanfaatan Eksternal',
        utilization_internal: 'Pemanfaatan Internal',
        reject: 'Reject',
        disposal_pok: 'Disposal / POK',
        adjustment_in: 'Adjustment In',
        adjustment_out: 'Adjustment Out',
    };

    return labels[value] ?? value;
}
