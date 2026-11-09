/**
 * Format tanggal untuk tampilan. Menerima "YYYY-MM-DD" (kolom date dari server) atau
 * timestamp ISO. Tanggal murni diurai manual supaya tidak bergeser oleh zona waktu.
 */
export function formatDate(value: string | null | undefined, style: 'long' | 'short' = 'long'): string {
    if (!value) return '-';

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(value);

    return date.toLocaleDateString('id-ID', { day: 'numeric', month: style === 'long' ? 'long' : 'short', year: 'numeric' });
}

/** Keterangan sisa masa sewa: "Sisa 7 hari", "Berakhir hari ini", "Lewat 3 hari", atau "Tanpa tanggal akhir". */
export function remainingLabel(days: number | null): string {
    if (days === null) return 'Tanpa tanggal akhir';
    if (days === 0) return 'Berakhir hari ini';
    return days > 0 ? `Sisa ${days} hari` : `Lewat ${Math.abs(days)} hari`;
}
