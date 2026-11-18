import type { NotificationItem } from '@/Components/NotificationBell.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { OwnerNotificationCounts } from '@/types';

/** Kuota WhatsApp dianggap hampir habis bila sisanya paling banyak 10% dari batas paket. */
const QUOTA_LOW_PERCENT = 10;

/**
 * Mengubah hitungan notifikasi dari server menjadi butir lonceng. Tautan menuju halaman yang sudah terfilter.
 * Izin dipakai hanya untuk memilih tautan; data keuangan sudah null dari server bagi yang tidak berizin.
 */
export function buildNotifications(counts: OwnerNotificationCounts, can: { manageSubscription?: boolean; manageSettings?: boolean } = {}): NotificationItem[] {
    const items: NotificationItem[] = [];
    const { pending_proofs: proofs, overdue_invoices: overdue, open_complaints: complaints, expiring_leases: expiring, quota } = counts;

    if (proofs) {
        items.push({
            key: 'proofs',
            icon: 'fact_check',
            title: `${proofs} bukti transfer menunggu verifikasi`,
            description: 'Cocokkan dengan mutasi rekening lalu catat pembayarannya.',
            href: '/app/bukti-transfer',
            count: proofs,
            tone: 'attention',
        });
    }

    if (overdue && overdue.count > 0) {
        items.push({
            key: 'overdue',
            icon: 'receipt_long',
            title: `${overdue.count} tagihan lewat jatuh tempo`,
            description: `Total tunggakan ${formatRupiah(overdue.amount)}.`,
            href: '/app/tagihan?status=overdue',
            count: overdue.count,
            tone: 'urgent',
        });
    }

    if (complaints > 0) {
        items.push({
            key: 'complaints',
            icon: 'build',
            title: `${complaints} komplain belum selesai`,
            description: 'Tinjau dan tugaskan ke staf.',
            href: '/app/komplain?status=open',
            count: complaints,
            tone: 'attention',
        });
    }

    if (expiring.count > 0) {
        items.push({
            key: 'expiring',
            icon: 'event_busy',
            title: `${expiring.count} kontrak berakhir dalam ${expiring.within_days} hari`,
            description: 'Tanyakan perpanjangan atau siapkan check-out.',
            href: '/app/kontrak',
            count: expiring.count,
            tone: 'info',
        });
    }

    if (quota.limit > 0 && quota.remaining <= Math.ceil((quota.limit * QUOTA_LOW_PERCENT) / 100)) {
        items.push({
            key: 'quota',
            icon: 'chat',
            title: quota.remaining > 0 ? `Kuota WhatsApp tinggal ${quota.remaining} pesan` : 'Kuota WhatsApp habis',
            description: 'Pengingat otomatis berhenti saat kuota habis.',
            href: can.manageSubscription ? '/app/langganan' : can.manageSettings ? '/app/pesan' : '/app',
            count: 1,
            tone: quota.remaining > 0 ? 'attention' : 'urgent',
        });
    }

    return items;
}
