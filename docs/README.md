# Mockup Kostra untuk Laravel 12

Sepuluh layar dari Stitch (login dan panel pemilik), ditambah `app.css` Tailwind v4 yang diterjemahkan dari konfigurasi Stitch. Buka berkas di `screens/` langsung di browser (perlu internet untuk Tailwind CDN, font, ikon, dan foto contoh). Menu samping di tiap layar sudah saling terhubung.

## 1. Isi folder

```
kostra-mockup/
├── README.md
├── app.css                        isi siap tempel untuk resources/css/app.css
├── screens/                       sumber tampilan (dari Stitch)
│   ├── 00-login.html
│   ├── 01-dashboard.html
│   ├── 02-properti-kamar.html
│   ├── 03-penghuni-kontrak.html
│   ├── 04-tagihan-pembayaran.html
│   ├── 05-komplain-perawatan.html
│   ├── 06-laporan-keuangan.html
│   ├── 07-pengaturan-whatsapp.html
│   ├── 08-pengaturan-kos.html
│   └── 09-paket-langganan.html
```

Sepuluh layar Stitch memakai konfigurasi Tailwind yang sama persis (47 warna, 9 spasi bernama, 12 ukuran huruf). Itu sebabnya satu `app.css` cukup.

Dua perubahan kecil dari berkas asli Stitch: tautan menu `href="#"` diganti ke berkas layar yang sesuai, dan di `08-pengaturan-kos.html` empat panel tab selain "Profil" diberi `hidden` supaya hanya satu tab tampil saat dibuka (aslinya kelima panel tampil sekaligus).

## 2. Memasang di Laravel 12

1. Salin isi `app.css` mulai dari `@theme { ... }` sampai akhir ke `resources/css/app.css`. Jangan menimpa baris `@import 'tailwindcss'` dan `@source` bawaan proyek.
2. Pasang font di `resources/views/layouts/owner.blade.php`:
   ```html
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
   ```
   Ikon memakai Material Symbols (`<span class="material-symbols-outlined">dashboard</span>`). Untuk menyajikan font sendiri lewat Vite, usulkan paket npm dulu (aturan AGENTS.md); `@fontsource-variable/inter` dan `@material-symbols/font-400` adalah kandidatnya.
3. Hapus `<script src="https://cdn.tailwindcss.com">` dan blok `tailwind.config` dari tiap layar. Di Laravel keduanya digantikan `app.css` lewat Vite (`@vite(['resources/css/app.css','resources/js/app.js'])`).
4. Angka uang memakai kelas `.angka` (angka tabular). Format `Rp 1.850.000` hanya di lapisan view (aturan uang di AGENTS.md).
5. Foto contoh (`lh3.googleusercontent.com`) dan logo hanya peraga. Ganti dengan lampiran dari tabel `attachments` lewat tautan bertanda tangan, dan logo Kostra dari `public/`.

## 3. Peta layar ke view, route, dan modul PRD

| Layar | View Blade | Route (usulan) | Modul PRD |
| --- | --- | --- | --- |
| `00-login` | `auth/login.blade.php` (layout tamu, tanpa sidebar) | `GET /login` | FND (masuk), LGN |
| `01-dashboard` | `owner/dashboard.blade.php` | `GET /owner` | KEU, TGH, PBY, KTR, KMP |
| `02-properti-kamar` | `owner/rooms/index.blade.php` + drawer detail kamar | `GET /owner/rooms` | PRP |
| `03-penghuni-kontrak` | `owner/residents/index.blade.php` + modal tambah penghuni + wizard check-out | `GET /owner/residents` | PNG, KTR |
| `04-tagihan-pembayaran` | `owner/invoices/index.blade.php` + modal audit bukti | `GET /owner/invoices` | TGH, PBY |
| `05-komplain-perawatan` | `owner/complaints/index.blade.php` (daftar dan detail satu halaman) | `GET /owner/complaints` | KMP |
| `06-laporan-keuangan` | `owner/reports/index.blade.php` + modal catat pengeluaran | `GET /owner/reports`, `POST /owner/expenses` | KEU |
| `07-pengaturan-whatsapp` | `owner/messages/index.blade.php` (log, template, jadwal) + modal beli kuota dan uji kirim | `GET /owner/messages` | NTF |
| `08-pengaturan-kos` | `owner/settings/index.blade.php` (5 tab) | `GET /owner/settings` | PRP, PBY, TGH, FND, LGN |
| `09-paket-langganan` | `owner/billing/index.blade.php` | `GET /owner/billing` | LGN (paket, kuota, add-on) |

Menu "Verifikasi Pembayaran" di sidebar menuju layar 04 (antrean bukti ada di atas tabel tagihan). Menu "Kuota WhatsApp" menuju layar 07, dan "Upgrade" menuju layar 09. Tombol "Keluar Akun" menuju layar 00. Tombol masuk di layar 00 langsung membuka layar 01 (peraga).

Layar yang belum ada di Stitch: daftar akun (uji coba), lupa kata sandi, portal penghuni (tanpa akun, lewat tautan token), detail penghuni, detail tagihan, check-in, daftar pengeluaran lengkap, panel Super Admin. Rancang dengan token `app.css` yang sama.

## 4. Pola yang diulang dan sebaiknya jadi komponen Blade

Tidak ada kelas komponen di `app.css`; gaya ada di kelas utilitas. Bungkus pola berikut supaya view bersih:

| Komponen | Pola kelas (ringkas) |
| --- | --- |
| `<x-status-badge>` | `inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-label-sm font-bold` + warna status di bawah |
| `<x-kpi-card>` | `p-space-md rounded-xl bg-surface-container-lowest shadow-sm`, label `font-label-sm text-label-sm text-secondary uppercase tracking-wider`, nilai `font-currency-stat text-currency-stat` |
| `<x-room-cell>` | sel `p-2 rounded-lg text-center cursor-pointer`; nomor `font-label-md font-bold`, nama `font-label-sm` |
| `<x-button>` | primer `bg-primary text-on-primary rounded-xl px-4 py-2 font-label-md`; sekunder `bg-surface-container text-on-surface`; bahaya `bg-error-container text-on-error-container` |
| `<x-input>` | `rounded-lg bg-surface-container-low px-3 h-10 focus:ring-2 focus:ring-primary/20` |
| `<x-modal>` dan `<x-drawer>` | modal `fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm`; drawer kanan `fixed right-0 h-full max-w-md translate-x-full` |
| `<x-table>` | `thead` `bg-surface-container-low text-secondary font-label-sm uppercase tracking-wider`, baris `hover:bg-surface-container-low/50` |

Warna status yang dipakai di layar (satu pemetaan dari enum ke kelas, taruh di `badgeClass()` pada enum):

| Status | Latar | Teks |
| --- | --- | --- |
| Lunas, terisi, aktif | `bg-primary-fixed` | `text-on-primary-fixed-variant` |
| Verifikasi, belum bayar, menunggu | `bg-tertiary-fixed` | `text-on-tertiary-fixed` |
| Menunggak, lewat tempo, urgensi tinggi | `bg-error-container` | `text-on-error-container` |
| Netral, kosong, segera tempo | `bg-secondary-container` atau `bg-surface-container` | `text-on-secondary-container` |

Status selalu memakai teks dan warna sekaligus (aturan UI di AGENTS.md).

## 5. Yang perlu diputuskan sebelum diimplementasikan

Layar Stitch menampilkan beberapa hal yang tidak ada di PRD MVP atau bertentangan dengan aturan produk. Jangan diimplementasikan apa adanya.

**Bertentangan dengan aturan "Kostra tidak menahan atau menyalurkan uang"**

| Di layar | Masalah | Saran |
| --- | --- | --- |
| 03 "Total Deposit Escrow", "Dana Jaminan Terkunci 100%" | Deposit hanya dicatat, tidak ditahan oleh Kostra | Ganti menjadi "Total Deposit Tercatat" |
| 08 "Penarikan Saldo" pada akses pemilik | Tidak ada saldo atau penarikan di Kostra | Hapus kalimatnya |
| 04 "Lunas Auto-QRIS", 07 dan 08 "Auto Reconcile MDR 0%", "Terverifikasi Bank Indonesia", VA "auto-detect" | Pembayaran online QRIS dan VA adalah PBY-08 dan PBY-09 (tahap berikutnya). MVP hanya menampilkan rekening dan QRIS statis (PBY-06) | Tampilkan QRIS sebagai gambar statis; hapus label auto |

**Layar baru (00 dan 09)**

| Di layar | Masalah | Saran |
| --- | --- | --- |
| 09 "Auto-debit BCA VA", "Pembayaran Otomatis QRIS & Virtual Account", "QRIS Dinamis" untuk langganan Kostra | Penagihan langganan oleh Kostra ke pemilik adalah hal terpisah dari uang sewa, tetapi PRD belum memuatnya (LGN-09 dan NTF-09 hanya menyebut add-on kuota). Perlu gerbang pembayaran | Putuskan: MVP cukup transfer manual dan konfirmasi admin |
| 09 empat paket (Gratis 10 kamar, Starter 30, Pro 100, Business) dan harga | Harga final dan batas kamar masih keputusan terbuka. Paket Business, Dedicated Account Manager, SLA 99,9%, custom domain, Faktur Pajak tidak ada di PRD | Samakan dengan PRD, mis. tiga paket |
| 09 "Laporan Aging Tunggakan & SP Otomatis", "Prioritas Antrean WhatsApp CS", "Slot Properti Tambahan" | Tidak ada di PRD | Sembunyikan atau tambahkan ke PRD |
| 09 add-on "tanpa kadaluwarsa" dan kuota reset tanggal 1 vs "Reset 1 Des 2026" pada kartu | Aturan reset tidak konsisten (periode langganan atau tanggal 1) | Pilih satu, simpan di `whatsapp_quotas` |
| 00 "Verifikasi pembayaran otomatis", "mutasi bank tanpa rekonsiliasi manual", "Terhubung langsung dengan BCA VA, Mandiri Bill, BNI" | Tidak ada integrasi bank di MVP | Hapus |
| 00 "SaaS Manajemen Kos #1 di Indonesia", "Dilindungi UU PDP", "Enkripsi AES 256-bit", server "Jakarta", "respons 42ms", "Versi 3.4.2", testimoni dengan foto | Klaim yang tidak bisa dibuktikan atau data karangan | Hapus atau ganti teks netral |
| 00 uji coba 14 hari | PRD menyebut masa uji; samakan angkanya | Cek bagian Langganan di PRD |
| 00 "Magic Link OTP via WhatsApp" dan tab "WhatsApp OTP" | Masuk lewat OTP WhatsApp ada di FND-01, tetapi memakai kuota pesan; jangan dipakai dari lingkungan dev (gunakan `FakeProvider`) | Biarkan, dengan batas laju |
| Halaman lain: "Pusat Bantuan", "Status Server", "Syarat & Ketentuan", "Kebijakan Privasi" | Belum ada layarnya | Tautan sementara |
| 09 Starter "28/30 kamar" dan Gratis "maks 10 kamar" | Kamar terdaftar (28) vs ditampilkan di layar lain (24) | Lihat bagian 6 |

**Tidak ada di PRD atau database**

| Di layar | Keterangan |
| --- | --- |
| 03 dan 07 "Sinkron Dukcapil", "KTP Sah PDP", "IDP-290192 (Dukcapil OK)" | Tidak ada integrasi Dukcapil. Nomor KTP tidak boleh disimpan atau ditampilkan; yang ada hanya foto KTP terenkripsi |
| 05 rating penghuni, "SLA: 4.8 Jam", "Kepuasan penghuni 98%" | SLA dan penilaian layanan adalah tahap berikutnya (PRD bagian Komplain) |
| 08 paket Pro "Integrasi Smart Lock & Meteran IoT" | Tidak ada di PRD |
| 07 "Meta Cloud API", "Tier 2 Verified" | Penyedia WhatsApp belum diputuskan (keputusan terbuka nomor 3 di ARCHITECTURE.md) |

**Fitur tahap berikutnya yang tampil di layar MVP**

| Di layar | Kode PRD | Saran |
| --- | --- | --- |
| 02 Pencatatan Meteran Listrik, kolom meteran di drawer kamar | TGH-09 | Sembunyikan |
| 02 Jadwal Perawatan Unit, 05 Jadwal Servis Rutin | Jadwal perawatan (tahap berikutnya) | Sembunyikan |
| 08 Aturan Tagihan: Denda Keterlambatan | TGH-08 (kolom `late_fee_*` disiapkan, belum dipakai) | Tampilkan nonaktif atau sembunyikan |
| 03 Template Kontrak, "Kontrak Digital" | KTR-09 | Sembunyikan |
| 05 daftar teknisi dan vendor | KMP-08 (di MVP `vendor_name` teks bebas) | Pakai isian teks |
| 04 dan 07 "Listrik: Rp 150.000 (32 kWh)" pada tagihan | TGH-09 | Pakai item tagihan manual |

Impor CSV (layar 03) dan ekspor (layar 04, 06) memang MVP (PNG-06, PRP-05, KEU-06).

## 6. Data contoh yang tidak konsisten antar layar

Angka dan nama di layar hanyalah peraga. Jangan dijadikan seeder, dan rapikan sebelum dipakai untuk screenshot atau demo.

- Jumlah kamar: 28 terdaftar, tetapi Lantai 1 (10) + Lantai 2 (14) = 24 di dashboard; layar tagihan menulis "32 kamar aktif".
- Tanggal: dashboard 24 Okt 2026; layar komplain "24 Okt 2024"; kontrak di layar penghuni berakhir Mar 2025 tetapi tertulis "5 Hari Tersisa".
- Zona waktu: dashboard "WITA", pengaturan "WIB (Asia/Jakarta)". Bawaan di ARCHITECTURE.md adalah Asia/Makassar.
- Kamar yang sama dihuni orang berbeda di tiap layar (mis. kamar 102).
- Layar 06 dan 08 memakai sidebar 250 px, enam layar lain 288 px (`w-72`). Samakan ke satu layout `layouts/owner` (disarankan `w-72`).

## 7. Catatan teknis

- Tailwind CDN di berkas Stitch adalah v3 (Play CDN); `app.css` untuk v4. Utilitas yang dipakai sama di kedua versi. `py-0.2` pada beberapa lencana bukan nilai valid di kedua versi, jadi tidak berefek.
- Skala tipografi dipakai berpasangan, mis. `font-headline-md text-headline-md`. Di `app.css`, `--text-*` membawa ukuran, tinggi baris, dan bobot, sedangkan `--font-*` membawa jenis huruf.
- JavaScript di layar (tab, modal, drawer, toast, simulasi kirim) hanya peraga. Di Laravel gunakan route sungguhan dan Alpine.js atau Livewire.
- Semua animasi (`animate-ping`, `animate-pulse`, `animate-bounce`) sebaiknya dibungkus `motion-safe:` (aturan di AGENTS.md).
