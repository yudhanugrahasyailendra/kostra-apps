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
