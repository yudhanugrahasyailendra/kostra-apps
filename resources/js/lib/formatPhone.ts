/**
 * Format tampilan nomor internasional tersimpan, mis. "+6281238991204" menjadi "+62 812-3899-1204".
 * Nilai yang tidak dikenal dikembalikan apa adanya. Nomor tidak pernah disimpan dalam bentuk ini.
 */
export function formatPhone(phone: string | null | undefined): string {
    if (!phone) {
        return '';
    }

    const match = /^\+62(\d{3})(\d{3,4})(\d{2,6})$/.exec(phone);

    return match ? `+62 ${match[1]}-${match[2]}-${match[3]}` : phone;
}

/** Tautan wa.me dari nomor tersimpan (+62...). */
export function whatsappLink(phone: string): string {
    return `https://wa.me/${phone.replace(/\D/g, '')}`;
}
