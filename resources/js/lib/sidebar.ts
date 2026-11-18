import { ref } from 'vue';

// Status sidebar pemilik (lebar atau ciut). Hanya kenyamanan per browser: bila penyimpanan diblokir, sidebar tetap lebar.
const STORAGE_KEY = 'kostra-sidebar-collapsed';

function stored(): boolean {
    try {
        return typeof window !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

const collapsed = ref(stored());

function toggle(): void {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Abaikan: status hanya bertahan sampai halaman dimuat ulang.
    }
}

export function useSidebar() {
    return { collapsed, toggle };
}
