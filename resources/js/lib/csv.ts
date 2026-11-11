export function encodeCsvRow(
    values: (string | number | null | undefined)[],
): string {
    return values
        .map((value) => {
            const safe =
                typeof value === 'string' &&
                /^(?:\s*[=+@-]|[\t\r\n])/u.test(value)
                    ? `'${value}`
                    : String(value ?? '');
            return /[,"\r\n]/u.test(safe)
                ? `"${safe.replaceAll('"', '""')}"`
                : safe;
        })
        .join(',');
}
