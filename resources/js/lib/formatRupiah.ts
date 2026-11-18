/**
 * Satu-satunya tempat format uang di lapisan tampilan.
 * Menerima integer rupiah, mengembalikan mis. "Rp 1.850.000".
 * Nilai negatif (penyesuaian) tampil sebagai "-Rp 50.000".
 */
export function formatRupiah(amount: number): string {
    if (!Number.isInteger(amount)) {
        throw new RangeError('formatRupiah hanya menerima bilangan bulat rupiah.');
    }

    const digits = Math.abs(amount)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${amount < 0 ? '-' : ''}Rp ${digits}`;
}

/**
 * Bentuk ringkas untuk label grafik yang sempit, mis. "Rp 12,5 jt". Di luar grafik pakai formatRupiah.
 */
export function formatRupiahShort(amount: number): string {
    if (!Number.isInteger(amount)) {
        throw new RangeError('formatRupiahShort hanya menerima bilangan bulat rupiah.');
    }

    const abs = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';
    const units: [number, string][] = [
        [1_000_000_000, 'M'],
        [1_000_000, 'jt'],
        [1_000, 'rb'],
    ];
    const unit = units.find(([size]) => abs >= size);

    if (!unit) {
        return formatRupiah(amount);
    }

    return `${sign}Rp ${(abs / unit[0]).toLocaleString('id-ID', { maximumFractionDigits: 1 })} ${unit[1]}`;
}
