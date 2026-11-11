export function vendorExpiryStatus(
    expiry: string | null | undefined,
    reference: string,
) {
    const date = expiry?.slice(0, 10);
    if (
        !date ||
        !/^\d{4}-\d{2}-\d{2}$/.test(date) ||
        !/^\d{4}-\d{2}-\d{2}$/.test(reference)
    ) {
        return {
            status: 'unknown',
            text: 'Tanggal belum dicatat',
            class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }
    return date < reference
        ? {
              status: 'expired',
              text: 'Kedaluwarsa',
              class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
          }
        : {
              status: 'not_expired',
              text: 'Belum kedaluwarsa',
              class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
          };
}
