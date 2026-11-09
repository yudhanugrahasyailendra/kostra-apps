import { usePage } from '@inertiajs/vue3';

/**
 * Awalan URL portal ("/p/<token>") diturunkan dari alamat halaman yang sedang dibuka, jadi token tidak
 * perlu dikirim server sebagai props (token hanya ada di tautan WhatsApp dan di bilah alamat penghuni).
 */
export function portalBase(): string {
    const [, prefix, token] = usePage().url.split('?')[0].split('/');

    return `/${prefix}/${token}`;
}
