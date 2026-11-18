<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useSidebar } from '@/lib/sidebar';

export interface SidebarItem {
    key: string;
    label: string;
    icon: string;
    href: string;
}

// Sidebar desktop pemilik: panel teal gelap yang bisa diciutkan menjadi kolom ikon.
// Menu aktif berupa tab berwarna kanvas yang menempel ke tepi kanan, sehingga menyatu dengan area konten.
// Menu disembunyikan lewat `can` hanya demi kerapian; otorisasi tetap diperiksa di server.
defineProps<{
    menu: SidebarItem[];
    general: SidebarItem[];
    activeKey: string;
    showPlan?: boolean;
}>();

const { collapsed, toggle } = useSidebar();

function itemClass(active: boolean) {
    if (collapsed.value) {
        return active
            ? 'mx-auto size-11 justify-center rounded-full bg-white text-on-primary-fixed-variant shadow-mid'
            : 'mx-auto size-11 justify-center rounded-full bg-white/10 text-white ring-1 ring-white/10 hover:bg-white/20';
    }

    return active
        ? 'sidebar-tab-notch -mr-space-md gap-space-sm rounded-l-full bg-bg-canvas py-1.5 pl-1.5 pr-space-md font-semibold text-primary-container'
        : 'gap-space-sm rounded-full bg-white/[0.06] py-1.5 pl-1.5 pr-space-md text-white/90 ring-1 ring-white/10 hover:bg-white/15 hover:text-white';
}

// Wadah bulat ikon (mode lebar). Ikon dibungkus terpisah karena kelas `material-symbols-outlined` memaksa inline-block.
function iconWrapClass(active: boolean) {
    if (collapsed.value) {
        return '';
    }

    return active ? 'size-8 bg-primary-container/10' : 'size-8 bg-white/10 ring-1 ring-white/15';
}
</script>

<template>
    <aside
        class="panel-sidebar scrollbar-none fixed inset-y-space-md left-space-md z-50 hidden flex-col overflow-y-auto overflow-x-hidden rounded-xl print:!hidden motion-safe:transition-[width] motion-safe:duration-300 lg:flex"
        :class="collapsed ? 'w-20' : 'w-64'"
    >
        <!-- Kepala: logo bulat dan tombol ciutkan/lebarkan -->
        <div
            class="flex items-center gap-space-sm pb-space-sm pt-space-md tall:pb-space-md tall:pt-space-lg"
            :class="collapsed ? 'flex-col px-0' : 'px-space-md'"
        >
            <Link href="/app" class="flex min-w-0 items-center gap-space-sm" aria-label="Kostra, Kost Rapi. Ke dashboard">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/90 ring-4 ring-white/15">
                    <img src="/images/kostra-mark.png" alt="" width="190" height="140" class="w-7" />
                </span>
                <span v-if="!collapsed" class="flex min-w-0 flex-col leading-tight">
                    <span class="text-title-lg font-semibold text-white">Kostra</span>
                    <span class="flex items-center gap-1 text-label-sm uppercase tracking-[0.25em] text-white/60">
                        <span class="h-0.5 w-3 rounded-full bg-secondary-fixed-dim" aria-hidden="true" />Kost Rapi
                    </span>
                </span>
            </Link>
            <button
                type="button"
                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/20 transition-colors hover:bg-white/25"
                :class="collapsed ? '' : 'ml-auto'"
                :aria-label="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                :aria-expanded="!collapsed"
                @click="toggle"
            >
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">{{ collapsed ? 'chevron_right' : 'chevron_left' }}</span>
            </button>
        </div>

        <nav class="flex flex-1 flex-col gap-space-sm px-space-md" aria-label="Menu utama">
            <template v-for="(section, index) in [{ label: 'Menu', items: menu }, { label: 'Umum', items: general }]" :key="section.label">
                <template v-if="section.items.length">
                    <p
                        v-if="!collapsed"
                        class="flex items-center gap-space-sm px-space-sm text-label-sm uppercase tracking-[0.2em] text-white/60"
                        :class="index === 0 ? 'pt-space-xs' : 'pt-space-sm tall:pt-space-md'"
                    >
                        <span class="h-0.5 w-4 rounded-full bg-secondary-fixed-dim" aria-hidden="true" />{{ section.label }}
                    </p>
                    <span v-else-if="index > 0" class="mx-auto my-space-xs h-px w-8 bg-white/20" aria-hidden="true" />
                    <Link
                        v-for="item in section.items"
                        :key="item.key"
                        :href="item.href"
                        class="relative flex shrink-0 items-center text-body-lg transition-colors"
                        :class="itemClass(item.key === activeKey)"
                        :title="collapsed ? item.label : undefined"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                    >
                        <span class="flex shrink-0 items-center justify-center rounded-full" :class="iconWrapClass(item.key === activeKey)" aria-hidden="true">
                            <span class="material-symbols-outlined" :class="{ '!text-[18px]': !collapsed, 'icon-fill': item.key === activeKey }">{{ item.icon }}</span>
                        </span>
                        <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
                    </Link>
                </template>
            </template>

            <Link
                href="/keluar"
                method="post"
                as="button"
                class="relative flex shrink-0 items-center text-body-lg transition-colors"
                :class="itemClass(false)"
                :title="collapsed ? 'Keluar' : undefined"
            >
                <span class="flex shrink-0 items-center justify-center rounded-full" :class="iconWrapClass(false)" aria-hidden="true">
                    <span class="material-symbols-outlined" :class="{ '!text-[18px]': !collapsed }">logout</span>
                </span>
                <span :class="collapsed ? 'sr-only' : 'truncate'">Keluar</span>
            </Link>
        </nav>

        <div v-if="showPlan && !collapsed" class="hidden p-space-md roomy:block">
            <div class="rounded-lg bg-white/[0.08] p-space-md text-white ring-1 ring-white/15">
                <span class="flex size-7 items-center justify-center rounded-full bg-secondary-fixed-dim text-on-secondary-fixed" aria-hidden="true">
                    <span class="material-symbols-outlined icon-fill !text-[16px]">workspace_premium</span>
                </span>
                <p class="mt-space-sm text-title-md font-medium">Paket langganan</p>
                <p class="mt-space-xs text-body-sm text-white/70">Tambah kamar dan kuota WhatsApp sesuai kebutuhan.</p>
                <Link
                    href="/app/langganan"
                    class="mt-space-md flex items-center justify-center rounded-full bg-secondary-fixed-dim px-space-md py-2 text-title-md text-on-secondary-fixed transition-colors hover:bg-secondary-fixed"
                    >Lihat paket</Link
                >
            </div>
        </div>
    </aside>
</template>
