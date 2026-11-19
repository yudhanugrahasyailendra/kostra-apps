<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useSidebar } from '@/lib/sidebar';
import type { SharedProps } from '@/types';

export interface SidebarItem {
    key: string;
    label: string;
    icon: string;
    href: string;
    /** Jumlah hal yang menunggu di menu ini (dari hitungan notifikasi); 0 atau kosong berarti tanpa lencana. */
    count?: number;
}

// Sidebar desktop pemilik: panel terang dengan logo Kostra, label bagian bergaris emas ("— MENU"),
// menu aktif berupa pil teal dengan ikon emas dan garis emas di tepi kiri panel, dan kartu paket di dasar.
// Bisa diciutkan menjadi kolom ikon.
// Menu disembunyikan lewat `can` hanya demi kerapian; otorisasi tetap diperiksa di server.
defineProps<{
    menu: SidebarItem[];
    general: SidebarItem[];
    activeKey: string;
    showPlan?: boolean;
}>();

const { collapsed, toggle } = useSidebar();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join(''),
);

const badge = (count: number) => (count > 99 ? '99+' : String(count));

// Garis emas menu aktif menempel di tepi kiri panel (nav memakai bantalan space-md).
const activeBar =
    'before:absolute before:-left-space-md before:top-1/2 before:h-8 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-secondary-container dark:before:bg-secondary';

function itemClass(active: boolean) {
    const shape = collapsed.value ? 'mx-auto size-10 justify-center rounded-md tall:size-11' : 'gap-space-sm rounded-md px-space-md py-1.5 roomy:py-2';

    return [
        shape,
        active
            ? `bg-primary-container font-semibold text-on-primary shadow-low ${activeBar}`
            : 'text-text-secondary hover:bg-primary-container/10 hover:text-primary-container',
    ];
}

function iconClass(active: boolean) {
    return active ? 'icon-fill text-secondary-container dark:text-on-primary' : 'text-primary-container';
}

function badgeClass(active: boolean) {
    return active ? 'bg-on-primary text-primary-container' : 'bg-primary-container text-on-primary';
}
</script>

<template>
    <aside
        class="panel-tint scrollbar-none fixed inset-y-space-md left-space-md z-50 hidden flex-col overflow-y-auto overflow-x-hidden rounded-xl print:!hidden motion-safe:transition-[width] motion-safe:duration-300 lg:flex"
        :class="collapsed ? 'w-20' : 'w-64'"
    >
        <!-- Kepala: logo dan tombol ciutkan/lebarkan -->
        <div
            class="flex items-center gap-space-sm pb-space-sm pt-space-md roomy:pb-space-md"
            :class="collapsed ? 'flex-col px-0' : 'px-space-md'"
        >
            <Link href="/app" class="flex min-w-0 items-center" aria-label="Kostra, Kost Rapi. Ke dashboard">
                <img
                    v-if="!collapsed"
                    src="/images/kostra-logo-sidebar.png"
                    alt=""
                    width="758"
                    height="160"
                    class="h-8 w-auto roomy:h-9 dark:rounded-lg dark:bg-inverse-surface dark:px-space-sm dark:py-1"
                />
                <span v-else class="flex size-11 items-center justify-center rounded-full bg-bg-surface ring-2 ring-primary-container dark:bg-white">
                    <img src="/images/kostra-mark.png" alt="" width="190" height="140" class="w-7" />
                </span>
            </Link>
            <button
                type="button"
                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-bg-surface text-primary-container shadow-low ring-1 ring-primary-container/10 transition-colors hover:bg-primary-container hover:text-on-primary dark:bg-surface-container-high"
                :class="collapsed ? '' : 'ml-auto'"
                :aria-label="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                :aria-expanded="!collapsed"
                @click="toggle"
            >
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">{{ collapsed ? 'chevron_right' : 'chevron_left' }}</span>
            </button>
        </div>

        <!-- Kartu pengguna di bawah logo: nama pemilik (atau staf), peran, dan nama kos. Mode ciut: inisial saja. -->
        <div v-if="user" class="shrink-0 pb-space-sm roomy:pb-space-md" :class="collapsed ? 'flex justify-center' : 'px-space-md'">
            <div
                v-if="collapsed"
                class="flex size-10 items-center justify-center rounded-full bg-secondary-fixed text-title-md font-semibold text-on-secondary-fixed tall:size-11"
                :title="`${user.name} · ${user.role_label}${tenant ? ` · ${tenant.name}` : ''}`"
            >
                <span aria-hidden="true">{{ initials }}</span>
                <span class="sr-only">{{ user.name }}, {{ user.role_label }}<template v-if="tenant">, {{ tenant.name }}</template></span>
            </div>
            <div
                v-else
                class="flex items-center gap-space-sm rounded-lg bg-bg-surface px-space-sm py-1.5 shadow-low ring-1 ring-primary-container/[0.06] roomy:py-space-sm dark:bg-surface-container-high"
            >
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary-fixed text-title-md font-semibold text-on-secondary-fixed" aria-hidden="true">
                    {{ initials }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-title-md font-semibold text-text-primary" :title="user.name">{{ user.name }}</span>
                    <span class="block truncate text-body-sm text-text-secondary" :title="tenant?.name">
                        {{ user.role_label }}<template v-if="tenant"> · {{ tenant.name }}</template>
                    </span>
                </span>
            </div>
        </div>

        <nav class="flex flex-1 flex-col gap-1 px-space-md" aria-label="Menu utama">
            <template v-for="(section, index) in [{ label: 'Menu', items: menu }, { label: 'Umum', items: general }]" :key="section.label">
                <template v-if="section.items.length">
                    <p
                        v-if="!collapsed"
                        class="flex items-center gap-space-sm px-space-sm pb-space-xs text-label-sm uppercase tracking-[0.2em] text-primary-container"
                        :class="index === 0 ? 'roomy:pt-space-xs' : 'pt-space-sm roomy:pt-space-md'"
                    >
                        <span class="h-0.5 w-4 rounded-full bg-secondary-container dark:bg-secondary" aria-hidden="true" />{{ section.label }}
                    </p>
                    <span v-else-if="index > 0" class="mx-auto my-space-xs h-px w-8 bg-border-subtle" aria-hidden="true" />
                    <Link
                        v-for="item in section.items"
                        :key="item.key"
                        :href="item.href"
                        class="relative flex shrink-0 items-center text-body-lg transition-colors"
                        :class="itemClass(item.key === activeKey)"
                        :title="collapsed ? item.label : undefined"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                    >
                        <span class="material-symbols-outlined shrink-0 !text-[22px]" :class="iconClass(item.key === activeKey)" aria-hidden="true">{{ item.icon }}</span>
                        <span :class="collapsed ? 'sr-only' : 'min-w-0 flex-1 truncate'">{{ item.label }}</span>
                        <template v-if="item.count">
                            <span class="sr-only">, {{ item.count }} perlu ditindaklanjuti</span>
                            <span
                                class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-3"
                                :class="[badgeClass(item.key === activeKey), collapsed ? 'absolute -right-1 -top-1' : 'shrink-0']"
                                aria-hidden="true"
                                >{{ badge(item.count) }}</span
                            >
                        </template>
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
                <span class="material-symbols-outlined shrink-0 !text-[22px]" :class="iconClass(false)" aria-hidden="true">logout</span>
                <span :class="collapsed ? 'sr-only' : 'truncate'">Keluar</span>
            </Link>
        </nav>

        <!-- Kartu paket di dasar sidebar: teal gelap bergaris lengkung, ikon dan tombol emas. Selalu gelap di kedua tema.
             Selalu tampil; supaya sidebar muat tanpa digulir, menu dan kartu memadat di layar pendek:
             < 46rem: kepala ±60 + kartu pengguna ±56 + menu ±368 + kartu ringkas ±144 = ±628px (muat mulai layar ±660px)
             46-50rem: kepala ±72 + kartu pengguna ±68 + menu ±412 + kartu ringkas ±148 = ±700px (tersedia ≥ 704px)
             ≥ 50rem: kepala ±72 + kartu pengguna ±68 + menu ±412 + kartu penuh ±214 = ±766px (tersedia ≥ 768px) -->
        <div v-if="showPlan && !collapsed" class="mt-auto px-space-md pb-space-md tall:pt-space-md">
            <div class="panel-sidebar relative isolate overflow-hidden rounded-lg p-3 text-white tall:p-space-md">
                <svg class="pointer-events-none absolute inset-0 -z-10 size-full opacity-25" viewBox="0 0 220 200" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
                    <ellipse v-for="n in 14" :key="n" cx="200" cy="200" :rx="n * 13" :ry="n * 7" transform="rotate(-20 200 200)" stroke="currentColor" stroke-width="0.7" />
                </svg>
                <!-- Layar pendek: ikon di samping judul. Layar tinggi: ikon di atas judul seperti desain.
                     Keterangan selalu utuh (dua baris selebar kartu), tidak dipotong. -->
                <div class="flex items-center gap-space-sm tall:flex-col tall:items-start">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary-fixed-dim text-on-secondary-fixed" aria-hidden="true">
                        <span class="material-symbols-outlined icon-fill !text-[16px]">workspace_premium</span>
                    </span>
                    <p class="min-w-0 truncate text-title-lg font-medium">Paket langganan</p>
                </div>
                <p class="mt-1 text-body-sm text-white/80">Tambah kamar dan kuota WhatsApp sesuai kebutuhan.</p>
                <Link
                    href="/app/langganan"
                    class="mt-space-sm flex items-center justify-center rounded-full bg-secondary-fixed-dim px-space-md py-1.5 text-title-md text-on-secondary-fixed transition-colors hover:bg-secondary-fixed roomy:py-2 tall:mt-space-md tall:py-2.5"
                    >Lihat paket</Link
                >
            </div>
        </div>
    </aside>
</template>
