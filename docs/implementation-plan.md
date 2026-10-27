# Implementation Plan Kostra untuk Claude Code CLI

## 1. Cara memakai rencana ini

Kerjakan Kostra satu fase per sesi Claude Code: rencanakan, tulis tes, implementasikan, periksa, commit, lalu mulai sesi bersih untuk fase berikutnya. Detail Claude Code di dokumen ini saya cek ke dokumentasi resmi pada 20 Okt 2026; versi Anda bisa berbeda, jadi jalankan `claude --version` dan `/help` bila ada perintah yang tidak dikenali.

### Alur satu fase

1. Buka terminal di folder proyek (`kostra-apps`) lalu jalankan `claude`.
2. Masuk plan mode: tekan `Shift+Tab` sampai status bar menampilkan plan mode, atau awali prompt dengan `/plan`. Di plan mode Claude membaca dan merencanakan tanpa mengubah kode sampai Anda menyetujui rencananya.
3. Tempel prompt dari bagian Fase 0 sampai Fase 12 sesuai fase yang dikerjakan, atau ketik `/fase N` setelah skill di bagian 2 terpasang.
4. Baca rencana Claude. Tekan `Ctrl+G` untuk menyunting rencana di editor. Pilih persetujuan manual untuk fase yang menyentuh migrasi atau uang, dan "No, keep planning" bila ada yang salah.
5. Setelah implementasi, jalankan `/cek-fase N`, baca `git diff` sendiri, dan jalankan tes.
6. Commit di cabang fase (push tetap Anda lakukan sendiri).
7. Ketik `/clear` sebelum fase berikutnya; CLAUDE.md dimuat ulang otomatis di sesi baru.

### Aturan main

| Aturan | Alasan |
| --- | --- |
| Satu fase, satu sesi, satu cabang git (misalnya `fase-5-tagihan`) | Diff mudah ditinjau dan dibatalkan |
| Tes lebih dulu untuk aturan uang dan isolasi tenant | Bug di dua area ini paling mahal |
| Jangan biarkan Claude mengubah `docs/*.md` | PRD dan desain database adalah sumber kebenaran; ubah dokumennya sendiri, lalu ekspor ulang |
| Pakai mode manual atau accept edits untuk migrasi dan kode uang | Di versi terbaru, auto mode bisa menjadi mode awal terminal; cek status bar sebelum mulai |
| Bila Claude mengulang kesalahan yang sama dua kali, tambahkan aturannya ke CLAUDE.md | CLAUDE.md dibaca di setiap sesi |
| `/compact` di tengah fase yang panjang, `/clear` di antara fase | Menjaga konteks tetap muat |
| Jangan pernah menaruh kunci API atau isi `.env` di prompt | Prompt tersimpan di riwayat sesi |

## 2. Persiapan repo

Siapkan lima hal sebelum Fase 0: dokumen rujukan di `docs/`, `CLAUDE.md`, izin proyek, dua skill perintah, dan satu subagent pemeriksa tenant. Semuanya berupa berkas teks yang bisa Anda buat sendiri atau minta Claude membuatnya; setelah itu commit agar ikut versi proyek.

### 2.1 Dokumen rujukan

Ekspor PRD, Desain Database, dan dokumen ini sebagai Markdown, lalu simpan di repo sebagai `docs/prd.md`, `docs/database.md`, dan `docs/implementation-plan.md`. Claude Code membaca berkas ini saat fase membutuhkannya; jangan di-import dari CLAUDE.md karena akan memenuhi konteks di setiap sesi.

### 2.2 CLAUDE.md

Simpan di `./CLAUDE.md`. Dokumentasi menyarankan di bawah 200 baris dan aturan yang bisa diverifikasi, bukan kalimat umum. Jalankan `/init` bila ingin Claude menyusun draf dari kode, lalu gabungkan dengan isi di bawah. Verifikasi dengan `/context` (lihat daftar Memory files).

```markdown
# Kostra: SaaS manajemen kos (Laravel 12)

Produk langganan untuk pemilik kos. Multi-tenant satu database (kolom tenant_id).
Sumber kebenaran: docs/prd.md (kebutuhan), docs/database.md (skema), docs/mockup/ (tampilan). Baca bagian yang relevan saja.

## Perintah
- Tes: `php artisan test` (atau `composer test`)
- Format: `./vendor/bin/pint`
- Dev: `composer run dev` (periksa composer.json bila skrip berbeda)
- Daftar route: `php artisan route:list`

## Konvensi wajib
- Uang: integer rupiah (unsignedBigInteger). Dilarang float atau decimal untuk uang.
- Tenant: setiap model milik pemilik memakai trait BelongsToTenant (global scope tenant_id). Dilarang query tanpa scope, withoutGlobalScopes, atau DB::table pada tabel tenant kecuali diminta eksplisit.
- Status: PHP Enum di app/Enums, disimpan sebagai string.
- Logika bisnis di app/Actions (satu kelas satu aksi). Controller dan komponen UI tipis.
- Validasi lewat Form Request; otorisasi lewat Policy. Tidak ada route tanpa middleware.
- Aksi yang mengubah beberapa tabel dibungkus DB::transaction.
- Penerbitan tagihan idempoten: unique (lease_id, period); nomor lewat number_sequences dengan lockForUpdate.
- Lampiran lewat tabel attachments, disk privat; foto KTP dienkripsi. Nomor KTP tidak disimpan.
- Kostra tidak menahan atau menyalurkan uang pemilik.
- Nama kolom dan tabel mengikuti docs/database.md persis. Bila perlu menyimpang, berhenti dan tanya.
- UI berbahasa Indonesia; identifier kode berbahasa Inggris.
- Tampilan mengikuti docs/mockup/. Baca docs/mockup/README.md, lalu hanya bagian layar terkait di panel.html, portal.html, atau auth.html (cari data-screen). Jangan baca panel.html utuh.
- Gaya lewat kelas komponen di resources/css/app.css dan komponen Blade (x-button, x-status-badge, x-room-plate, x-panel, x-modal). Warna dan tipografi mengikuti DESIGN.md (token di app.css). Jangan menambah warna baru.

## Alur kerja
- Mulai dengan rencana, tulis tes untuk aturan uang dan isolasi tenant lebih dulu.
- Setelah selesai: jalankan pint dan seluruh tes, ringkas file yang berubah dan penyimpangan dari docs.
- Jangan ubah berkas di docs/ kecuali diminta. Jangan commit .env. Jangan git push.
- Jangan pasang paket composer baru tanpa menyebut nama paket dan alasannya lebih dulu.

## Struktur
app/Actions, app/Enums, app/Models (+ Concerns/BelongsToTenant), app/Policies, app/Services,
app/Jobs, app/Http/Controllers/{Owner,Portal,Admin}, routes/{web,owner,portal,admin,console}.php
```

### 2.3 Izin proyek

Simpan di `.claude/settings.json` agar Claude tidak bertanya untuk perintah rutin. Aturan Bash mencocokkan teks perintah, jadi `deny` bukan batas keamanan mutlak (misalnya `git -C . push` tidak cocok); gunakan sebagai pagar tambahan, dan tetap jangan memberi kredensial produksi di mesin pengembangan.

```json
{
  "permissions": {
    "allow": [
      "Bash(php artisan test *)",
      "Bash(composer test)",
      "Bash(./vendor/bin/pint *)",
      "Bash(php artisan route:list *)",
      "Bash(git status *)",
      "Bash(git diff *)",
      "Bash(git log *)"
    ],
    "ask": [
      "Bash(php artisan migrate:fresh *)",
      "Bash(php artisan db:wipe *)",
      "Bash(composer require *)"
    ],
    "deny": [
      "Bash(git push *)",
      "Read(./.env)"
    ]
  }
}
```

Preferensi pribadi taruh di `.claude/settings.local.json` dan `CLAUDE.local.md`, lalu tambahkan keduanya ke `.gitignore`.

### 2.4 Skill `/fase` dan `/cek-fase`

Perintah kustom kini berupa skill: berkas `.claude/skills/<nama>/SKILL.md` membuat perintah `/<nama>`. Baris yang diawali `!` dijalankan lebih dulu dan keluarannya disisipkan ke prompt; `$ARGUMENTS` diganti dengan teks setelah nama perintah. Dengan `disable-model-invocation: true`, skill hanya berjalan bila Anda mengetiknya.

Berkas `.claude/skills/fase/SKILL.md`:

```markdown
---
description: Kerjakan satu fase implementation plan Kostra dari docs/implementation-plan.md
argument-hint: [nomor fase, misalnya 5]
disable-model-invocation: true
allowed-tools: Bash(git status *) Bash(git log *)
---

## Keadaan repo
!`git status --short`
!`git log --oneline -5`

## Tugas
Kerjakan Fase $ARGUMENTS.

1. Baca bagian "Fase $ARGUMENTS" di docs/implementation-plan.md, modul terkait di docs/prd.md, dan tabel terkait di docs/database.md.
2. Tulis rencana: urutan langkah, daftar file, dan tes yang akan dibuat. Tunggu persetujuan saya.
3. Tulis tes lebih dulu untuk aturan uang dan isolasi tenant, lalu implementasi.
4. Jalankan `php artisan test` dan `./vendor/bin/pint`.
5. Ringkas: file berubah, hasil tes, dan setiap penyimpangan dari docs.
6. Jangan commit sebelum saya setuju.
```

Berkas `.claude/skills/cek-fase/SKILL.md`:

```markdown
---
description: Periksa apakah fase yang baru dikerjakan memenuhi kriteria selesai
argument-hint: [nomor fase]
disable-model-invocation: true
allowed-tools: Bash(php artisan test *) Bash(./vendor/bin/pint *) Bash(git diff *) Bash(git status *)
---

Periksa Fase $ARGUMENTS terhadap bagian "Selesai bila" di docs/implementation-plan.md.

1. Jalankan `php artisan test` dan `./vendor/bin/pint --test`.
2. Pastikan setiap model tabel milik pemilik memakai BelongsToTenant.
3. Bandingkan migrasi dengan docs/database.md: nama kolom, tipe, indeks unik, foreign key.
4. Cari float atau decimal untuk uang, dan route tanpa middleware atau Policy.
5. Tampilkan tabel lulus atau gagal per kriteria. Jangan memperbaiki apa pun kecuali saya minta.
```

### 2.5 Subagent `tenant-auditor`

Subagent adalah berkas di `.claude/agents/`. Konteksnya terpisah dari sesi utama, jadi hasil audit panjang tidak memenuhi percakapan. Berkas `.claude/agents/tenant-auditor.md`:

```markdown
---
name: tenant-auditor
description: Mengaudit kode Laravel untuk kebocoran data antar tenant dan otorisasi yang hilang. Gunakan setelah menambah model, query, route, atau controller.
tools: Read, Grep, Glob
model: inherit
---

Anda auditor keamanan multi-tenant untuk Kostra. Temukan kemungkinan data satu pemilik kos terbaca atau berubah oleh pemilik lain.

Periksa:
- Model tabel milik pemilik tanpa BelongsToTenant.
- withoutGlobalScopes, DB::table, atau SQL mentah pada tabel tenant tanpa filter tenant_id.
- Route model binding atau find(id) tanpa pembatasan tenant.
- Controller, komponen UI, atau resource tanpa Policy.
- Endpoint portal yang memakai id tanpa memeriksa kepemilikan penghuni.
- Job antrean yang kehilangan konteks tenant.

Keluaran: daftar temuan berurut tingkat keparahan, masing-masing dengan file dan baris, penjelasan singkat, dan saran perbaikan. Jangan mengubah berkas.
```

Panggil dengan kalimat biasa, misalnya "Gunakan subagent tenant-auditor untuk memeriksa perubahan fase ini".

### 2.6 Mockup UI

Mockup HTML Kostra (`kostra-mockup.zip`) adalah acuan tampilan semua fase. Ekstrak ke `docs/mockup/` di repo, sejajar dengan `docs/prd.md` dan `docs/database.md`, lalu commit bersama dokumen lain:

Sumber desain visual adalah `DESIGN.md` (dibuat di Stitch): emerald `#0D7A5F`, Slate Navy `#1E293B`, Warm Amber `#D97706`, font Inter dan Plus Jakarta Sans. Mockup dan `app.css` sudah menerjemahkannya; bila keduanya berbeda, `DESIGN.md` yang benar. Letakkan `DESIGN.md` di akar repo, di samping `AGENTS.md` dan `ARCHITECTURE.md`.

```text
docs/mockup/
├── README.md     peta layar ke view Blade dan route
├── panel.html    panel pemilik dan staf (14 layar, 5 modal, 1 drawer bukti transfer)
├── portal.html   portal penghuni (mobile-first)
├── auth.html     masuk, daftar, lupa kata sandi
└── app.css       isi siap tempel untuk resources/css/app.css
```

Aturan memakainya:

1. `panel.html` berukuran sekitar 92 KB. Claude Code tidak perlu membacanya utuh: cari `data-screen="nama-layar"` (atau id modal) lalu baca bagian itu saja, dan baca `docs/mockup/README.md` untuk nama view dan route.
2. Tampilan mengikuti mockup (tata letak, kelas komponen, teks Indonesia). Data, nama route, dan perilaku mengikuti PRD dan `docs/database.md`; JavaScript di mockup hanya peraga.
3. Kelas komponen (`.btn`, `.badge-*`, `.pelat*`, `.panel`, `.tbl`) hidup di `resources/css/app.css` dan dibungkus komponen Blade; jangan menyalin kelas utilitas panjang ke tiap view.
4. Mockup memakai Blade dan Tailwind, bukan Filament. Panel pemilik dibangun dengan Blade, Alpine.js, dan Livewire. Bila Fase 0 memilih Filament, mockup tetap menjadi acuan tata letak dan warna, bukan salinan markup.

Peta fase ke layar mockup:

| Fase | Layar mockup | Berkas |
| --- | --- | --- |
| 0 | Tema, font, kerangka layout (sidebar, header, laci ponsel) | `app.css`, bagian shell `panel.html` |
| 1 | Masuk, daftar, lupa kata sandi; `#pengaturan` tab staf | `auth.html`, `panel.html` |
| 2 | `#kamar`, modal tambah banyak kamar, denah kamar di `#dashboard` | `panel.html` |
| 3 | `#penghuni`, `#penghuni-detail` | `panel.html` |
| 4 | `#kontrak`, modal check-in | `panel.html` |
| 5 | `#tagihan`, `#tagihan-detail`, `#pengaturan` tab penagihan | `panel.html` |
| 6 | `#bukti`, modal catat pembayaran, modal tolak bukti | `panel.html` |
| 7 | `#pengeluaran`, modal pengeluaran, `#laporan`, `#dashboard` (statistik dan grafik) | `panel.html` |
| 8 | `#komplain` (pemilik) dan Komplain di portal | `panel.html`, `portal.html` |
| 9 | `#pesan` | `panel.html` |
| 10 | Beranda, bayar, terkirim, riwayat, komplain | `portal.html` |
| 11 | `#langganan` | `panel.html` |
| 12 | Tidak ada. Periksa tampilan di lebar ponsel dan desktop | semua |

Super Admin (Fase 11) belum punya mockup. Minta Claude Code memakai kerangka, komponen, dan palet yang sama dengan panel pemilik.

## 3. Peta fase

Tiga belas fase (0 sampai 12) mencakup seluruh 33 tabel MVP dan mengikuti urutan migrasi di Desain Database. Kolom "Minggu" mengikuti rencana rilis di PRD; minggu 4 memuat lima fase sekaligus, jadi siapkan satu minggu cadangan bila Claude Code atau pilot menyita waktu.

| Fase | Fokus | Tabel baru | Minggu | Prasyarat |
| --- | --- | --- | --- | --- |
| 0 | Bootstrap proyek, tooling, keputusan UI | tidak ada | 1 | Repo Laravel 12 dan database tersedia |
| 1 | Fondasi: tenant, autentikasi, peran, audit | plans, tenants, users, invitations, audit_logs | 1 | Fase 0 |
| 2 | Properti dan kamar | properties, room_types, rooms | 1 | Fase 1 |
| 3 | Penghuni dan lampiran | residents, attachments | 2 | Fase 2 |
| 4 | Kontrak sewa | leases, lease_residents, lease_deposits, room_transfers | 2 | Fase 2 dan 3 |
| 5 | Tagihan dan penjadwal | billing_settings, number_sequences, invoices, invoice_items, invoice_adjustments | 3 | Fase 4 |
| 6 | Pembayaran dan bukti transfer | payment_accounts, payments, payment_allocations, payment_proofs | 3 | Fase 5 |
| 7 | Pengeluaran, laporan, dashboard | expense_categories, expenses | 4 | Fase 6 |
| 8 | Komplain | complaints, complaint_comments | 4 | Fase 3 dan 4 |
| 9 | Notifikasi WhatsApp dan kuota | message_templates, message_logs, usage_counters | 4 | Fase 5 dan 6 |
| 10 | Portal penghuni | portal_tokens | 4 | Fase 5, 6, 8, dan 9 |
| 11 | Langganan dan Super Admin | subscriptions, subscription_invoices | 4 | Fase 1 dan 9 |
| 12 | Hardening, deploy, persiapan pilot | tidak ada | sebelum pilot | Semua fase |

Fase 7 dan 11 tidak menjadi prasyarat fase lain, jadi boleh digeser ke akhir bila Anda ingin pengingat dan portal lebih cepat siap untuk pilot.

## Fase 0: Bootstrap proyek dan keputusan awal

Fase 0 menyiapkan proyek Laravel 12 yang bisa diuji, dengan keputusan teknis yang tidak boleh berubah di tengah jalan: database, UI, kerangka tes, antrean, dan penyimpanan berkas.

**Prasyarat**: PHP dan Composer sesuai syarat Laravel 12 (lihat `composer.json`), Node.js untuk Vite, MySQL 8 atau PostgreSQL yang berjalan, repo git, dan persiapan di bagian 2 selesai.

**Mockup**: ekstrak `kostra-mockup.zip` ke `docs/mockup/` (bagian 2.6). Gabungkan `docs/mockup/app.css` ke `resources/css/app.css`, pasang font Inter dan Plus Jakarta Sans, dan bangun `layouts/owner.blade.php`, `layouts/guest.blade.php`, serta komponen Blade dasar dari kerangka mockup.

**Keputusan yang dikunci sekarang**

| Keputusan | Rekomendasi | Alasan |
| --- | --- | --- |
| Database | Pilih satu (MySQL 8 atau PostgreSQL) dan samakan dengan produksi | Desain berjalan di keduanya; jangan campur |
| UI panel pemilik dan admin | Filament, setelah memeriksa versi yang mendukung Laravel 12 | CRUD, tabel, dan laporan lebih cepat; alternatifnya starter kit Livewire |
| UI portal penghuni | Blade dengan Livewire atau Alpine ringan | Halaman ringan di ponsel |
| Kerangka tes | Ikuti yang sudah ada di `tests/` (PHPUnit atau Pest) | Jangan memakai dua kerangka |
| Antrean dan sesi | Driver database untuk MVP | Tanpa Redis di awal |
| Berkas | Disk lokal privat; penyimpanan kompatibel S3 nanti | Foto KTP tidak boleh publik |

**Prompt untuk Claude Code**

```text
Kita memulai Fase 0 proyek Kostra (lihat CLAUDE.md dan docs/implementation-plan.md, bagian "Fase 0").
Jangan memasang paket apa pun sebelum saya menyetujui rencananya.

1. Periksa proyek ini: versi PHP dan Laravel, kerangka tes, isi composer.json dan package.json, dan .env.example.
2. Usulkan rencana untuk: koneksi database (saya memakai <MySQL atau PostgreSQL>), antrean dan sesi berbasis database, disk privat untuk lampiran, zona waktu aplikasi UTC dengan tampilan Asia/Makassar, Pint, dan CI sederhana yang menjalankan tes.
3. Usulkan folder app/Actions, app/Enums, app/Services, app/Models/Concerns, serta routes/owner.php, routes/portal.php, routes/admin.php yang didaftarkan di bootstrap/app.php lewat parameter `then` pada withRouting.
4. Buat config/kostra.php berisi batas paket bawaan dan nilai bawaan penagihan (hari terbit, hari jatuh tempo, jam kirim pesan).
5. Pastikan `php artisan test` hijau pada proyek kosong.
Tanyakan pilihan UI (Filament atau Livewire) kepada saya sebelum memasangnya.
```

**Hasil yang diharapkan**

- Commit awal; `.env` tidak ikut repo.
- `CLAUDE.md`, `docs/`, dan `.claude/` ter-commit.
- Folder `app/Actions`, `app/Enums`, `app/Services`, dan `app/Models/Concerns`.
- `config/kostra.php`.
- Tiga berkas route kosong yang termuat di `bootstrap/app.php`.

**Tes wajib**

- `php artisan test` hijau pada proyek kosong.
- `./vendor/bin/pint --test` bersih.
- `php artisan migrate` berjalan di database lokal.
- Halaman utama menjawab HTTP 200.

**Selesai bila**: semua tes di atas lulus, keputusan di tabel tercatat di `docs/` atau `CLAUDE.md`, dan `/context` menampilkan CLAUDE.md di Memory files.

**Commit**: `chore: bootstrap proyek kostra (fase 0)`

## Fase 1: Fondasi tenant, autentikasi, peran, dan audit

Fase 1 membuat setiap pemilik kos terisolasi, peran dan izin berfungsi, dan aksi penting tercatat. Semua fase lain bergantung padanya, jadi isolasi tenant harus lulus uji sebelum Anda lanjut.

**Prasyarat**: Fase 0. **Rujukan**: PRD modul Fondasi (FND-01 sampai FND-06); Desain Database bagian Platform dan tenant, ditambah tabel `audit_logs`. **Tabel baru**: plans, tenants, users (menyesuaikan tabel bawaan), invitations, audit_logs.

**Mockup**: `auth.html` (masuk, daftar, lupa kata sandi) dan tab staf di `#pengaturan` pada `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 1 Kostra: fondasi multi-tenant. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Fondasi" (FND-01 sampai FND-06), dan docs/database.md bagian "Platform dan tenant" serta tabel audit_logs.

Rencanakan dulu, lalu kerjakan dengan tes lebih dulu:
1. Migrasi plans, tenants, users (sesuaikan tabel users bawaan Laravel: tenant_id nullable, phone, role, permissions json, is_active, last_login_at, softDeletes), invitations, audit_logs. Ikuti docs/database.md persis.
2. Enum UserRole (super_admin, owner, staff) dan TenantStatus.
3. Trait BelongsToTenant dengan global scope tenant_id dan pengisian tenant_id otomatis saat create. Sediakan cara eksplisit untuk konteks tanpa tenant (queue, seeder) lewat TenantContext.
4. Middleware SetCurrentTenant dan EnsureTenantIsActive (tenant yang ditangguhkan hanya boleh membaca dan mengekspor).
5. Login, logout, dan reset kata sandi (tautan reset kedaluwarsa 60 menit). Pendaftaran pemilik lewat email atau nomor WhatsApp membuat tenant paket Gratis dan user owner dalam satu transaksi.
6. Undangan staf (token disimpan sebagai hash, kedaluwarsa 7 hari, sekali pakai), izin staf lewat kolom permissions, dan Policy dasar.
7. Pencatat audit untuk aksi penting, menyimpan data sebelum dan sesudah.
8. PlanSeeder (Gratis, Starter, Pro, Business) dan seeder satu Super Admin.
Mulai dari tes isolasi tenant: pengguna tenant A tidak bisa membaca atau mengubah data tenant B lewat query, URL, maupun route model binding.
```

**Hasil yang diharapkan**

- Migrasi lima tabel sesuai `docs/database.md`.
- Model Tenant, User, Plan, Invitation, AuditLog; trait `BelongsToTenant` dan `TenantScope`; `TenantContext`.
- Middleware `SetCurrentTenant` dan `EnsureTenantIsActive`.
- Action `RegisterOwner`, `InviteStaff`, `AcceptInvitation`, dan pencatat audit.
- Policy dasar, `PlanSeeder`, seeder Super Admin.
- Tes di `tests/Feature/Tenancy/` dan `tests/Feature/Auth/`.

**Tes wajib**

- Isolasi: `find(id)` data tenant lain mengembalikan kosong atau 404; ubah dan hapus ditolak.
- Model baru dengan trait mengisi `tenant_id` otomatis.
- Pendaftaran membuat tenant, owner, dan paket Gratis secara atomik; bila gagal tidak ada sisa data.
- Undangan yang lewat 7 hari atau sudah dipakai ditolak.
- Tenant ditangguhkan: menulis ditolak, membaca boleh.
- Perubahan data penting tercatat di audit dengan nilai lama dan baru.
- Tautan reset kata sandi kedaluwarsa setelah 60 menit.

**Selesai bila**: semua tes lulus; `tenant-auditor` tidak melaporkan temuan tinggi; tidak ada query tanpa scope pada tabel tenant; skema cocok dengan `docs/database.md`.

**Commit**: `feat(fondasi): tenant, peran, undangan, audit (fase 1)`

## Fase 2: Properti, tipe kamar, dan kamar

Fase 2 membuat pemilik bisa memasukkan seluruh kamarnya dalam beberapa menit, dengan batas paket yang dijaga di server. Kamar menjadi poros kontrak, tagihan, dan komplain di fase berikutnya.

**Prasyarat**: Fase 1. **Rujukan**: PRD modul Properti dan Kamar (PRP-01 sampai PRP-07); Desain Database bagian Properti dan kamar. **Tabel baru**: properties, room_types, rooms.

**Mockup**: `#kamar` dan modal tambah banyak kamar di `panel.html`. Denah kamar (`.pelat*`) di `#dashboard` dibuat sebagai komponen `x-room-plate` di fase ini.

**Prompt untuk Claude Code**

```text
Fase 2 Kostra: properti, tipe kamar, kamar. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Properti dan Kamar" (kerjakan PRP-01 sampai PRP-07), dan docs/database.md bagian "Properti dan kamar".

Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi properties, room_types, rooms persis sesuai docs/database.md. Model memakai BelongsToTenant; enum RoomStatus (vacant, occupied, maintenance); softDeletes pada properties dan rooms.
2. Validasi nomor kamar unik per properti dengan Rule::unique yang mengabaikan baris terhapus (indeks unik database tidak membedakan soft delete).
3. Action CreateRoomsInBulk (misalnya kamar 101 sampai 110 sekaligus) dan ImportRooms dari CSV atau Excel: baris gagal dilaporkan beserta alasannya, baris valid tetap masuk. Tanyakan paket impor yang akan dipakai sebelum memasangnya.
4. Service PlanLimits yang memeriksa batas properti dan kamar sesuai paket tenant di sisi server; penambahan di atas batas ditolak dengan tawaran naik paket.
5. Policy untuk ketiga model dan UI daftar dengan filter status, properti, dan tipe.
6. Status occupied tidak boleh diubah manual karena diatur oleh kontrak di Fase 4; pastikan form tidak menawarkannya.
```

**Hasil yang diharapkan**

- Migrasi, model, factory, dan Policy untuk tiga tabel; enum `RoomStatus`.
- Action `CreateRoomsInBulk` dan `ImportRooms`.
- Service `app/Services/Plans/PlanLimits`.
- UI daftar dan formulir untuk properti, tipe kamar, dan kamar.

**Tes wajib**

- Pembuatan massal 20 kamar dalam satu langkah.
- Nomor kamar sama di properti berbeda diizinkan, di properti yang sama ditolak, dan nomor kamar yang sudah terhapus boleh dipakai ulang.
- Impor: baris valid masuk dan baris gagal dilaporkan dengan alasan.
- Tenant paket Gratis tidak bisa membuat kamar ke-11 atau properti ke-2.
- Mengubah harga tipe kamar tidak mengubah harga kamar yang sudah ada.
- Isolasi tenant untuk ketiga model.
- Daftar 100 kamar tampil tanpa masalah N+1 (periksa jumlah query).

**Selesai bila**: semua tes lulus, `/cek-fase 2` tanpa kegagalan, dan seorang pemilik uji bisa memasukkan 20 kamar dalam kurang dari 5 menit.

**Commit**: `feat(properti): properti, tipe kamar, kamar, batas paket (fase 2)`

## Fase 3: Penghuni dan lampiran

Fase 3 menyimpan data penghuni seminimal mungkin dan membuat lampiran (terutama foto KTP) aman: terenkripsi, privat, dan setiap akses tercatat. Nomor KTP sengaja tidak disimpan sebagai kolom.

**Prasyarat**: Fase 2. **Rujukan**: PRD modul Penghuni (PNG-01 sampai PNG-06); Desain Database bagian Penghuni dan lampiran. **Tabel baru**: residents, attachments.

**Mockup**: `#penghuni` dan `#penghuni-detail` di `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 3 Kostra: penghuni dan lampiran. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Penghuni" (PNG-01 sampai PNG-06), dan docs/database.md bagian "Penghuni dan lampiran".

Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi residents dan attachments persis sesuai docs/database.md. Nomor KTP tidak boleh disimpan sebagai kolom.
2. Normalisasi nomor WhatsApp ke format internasional (bawaan +62) lewat satu helper yang dipakai di semua input dan impor.
3. Tabel attachments polimorfik di disk privat, validasi JPG, PNG, PDF maksimum 5 MB, dan Relation::enforceMorphMap agar nama kelas tidak tersimpan di database. Berkas is_sensitive (foto KTP) dienkripsi saat disimpan dan hanya dilayani lewat tautan bertanda tangan setelah Policy memeriksa tenant dan izin; setiap akses tercatat di audit_logs.
4. CRUD penghuni dengan pencarian dan filter (nama, nomor kamar, status), riwayat penghuni yang sudah check-out, dan kontak darurat sebagai kolom.
5. Impor penghuni dari CSV atau Excel; nomor WhatsApp ganda ditandai, bukan ditolak.
6. Kolom whatsapp_opt_out_at dan consented_at tersedia, tetapi belum ada logika pesan (itu Fase 9).
```

**Hasil yang diharapkan**

- Migrasi, model, factory, dan Policy untuk residents dan attachments.
- Helper normalisasi nomor telepon, `Action StoreAttachment`, dan rute unduh bertanda tangan.
- UI penghuni dengan pencarian, filter, dan unggah dokumen.

**Tes wajib**

- Nomor `0812...`, `+62812...`, dan `62812...` dinormalisasi menjadi nilai yang sama.
- Impor menandai nomor ganda dan tetap memasukkan baris yang valid.
- Unggahan di atas 5 MB atau bertipe lain ditolak.
- Isi berkas KTP di disk bukan byte gambar asli, tetapi terbaca benar lewat aplikasi.
- Pengguna tenant lain, atau staf tanpa izin, tidak bisa membuka lampiran; tautan bertanda tangan yang kedaluwarsa tidak bisa dibuka.
- Akses lampiran sensitif tercatat di audit log.
- Isolasi tenant untuk residents dan attachments.
- Pencarian di antara 500 penghuni selesai kurang dari 1 detik.

**Selesai bila**: semua tes lulus, `tenant-auditor` tidak melaporkan temuan tinggi pada rute unduh lampiran, dan tidak ada kolom nomor KTP di migrasi.

**Commit**: `feat(penghuni): penghuni dan lampiran terenkripsi (fase 3)`

## Fase 4: Kontrak sewa, check-in, dan check-out

Fase 4 menghubungkan penghuni, kamar, harga, dan masa sewa, dan menjamin satu kamar tidak pernah punya dua kontrak aktif. Aturan itu dijaga di database lewat kolom `active_room_id` yang unik, bukan hanya lewat validasi.

**Prasyarat**: Fase 2 dan 3. **Rujukan**: PRD modul Kontrak Sewa (KTR-01 sampai KTR-07); Desain Database bagian Kontrak sewa. **Tabel baru**: leases, lease_residents, lease_deposits, room_transfers.

**Mockup**: `#kontrak` dan modal check-in di `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 4 Kostra: kontrak sewa, check-in, dan check-out. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Kontrak Sewa" (KTR-01 sampai KTR-07) dan docs/database.md bagian "Kontrak sewa".

Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi leases, lease_residents, lease_deposits, room_transfers persis sesuai docs/database.md. Enum LeaseStatus dan DepositStatus.
2. leases.active_room_id diisi room_id selama kontrak aktif dan null bila tidak; unique di database. Inilah mekanisme satu kontrak aktif per kamar.
3. Action CheckInResident: dalam satu DB::transaction membuat kontrak, lease_residents (penanggung jawab is_primary), deposit, mengisi active_room_id, dan mengubah rooms.status menjadi occupied. Pelanggaran unique berarti kamar sudah terisi dan harus menghasilkan pesan yang jelas.
4. CheckOutResident (hitung sisa tagihan dan potongan deposit, tutup kontrak, kosongkan active_room_id, kamar menjadi vacant), RenewLease (kontrak baru menyambung dan mengisi renewed_from_lease_id), TransferRoom (catat room_transfers, perbarui room_id dan active_room_id; harga baru berlaku untuk tagihan berikutnya).
5. Sisa tagihan saat check-out dihitung lewat interface OutstandingBalance; di fase ini cukup implementasi sementara yang mengembalikan 0, diganti di Fase 5.
6. Scope Lease::expiringWithin($days) untuk pengingat 30, 14, dan 7 hari (pengiriman pesan nanti di Fase 9). Kontrak yang lewat end_date tetapi belum check-out ditandai "lewat masa sewa" sebagai nilai terhitung, bukan kolom.
7. UI kontrak, formulir check-in dan check-out, dan profil penghuni yang menampilkan riwayat kontrak.
```

**Hasil yang diharapkan**

- Empat migrasi, model, factory, dan Policy; enum `LeaseStatus` dan `DepositStatus`.
- Action `CheckInResident`, `CheckOutResident`, `RenewLease`, `TransferRoom`.
- Interface `OutstandingBalance` dengan implementasi sementara.
- UI kontrak dan riwayat kontrak di profil penghuni.

**Tes wajib**

- Check-in kedua pada kamar yang sama gagal oleh batasan unik, termasuk bila dua permintaan datang hampir bersamaan.
- Check-in mengubah status kamar menjadi occupied dalam transaksi yang sama; bila pembuatan deposit gagal, semuanya dibatalkan.
- Check-out mengosongkan `active_room_id`, mengembalikan kamar ke vacant, dan menghitung pengembalian deposit = jumlah deposit dikurangi potongan.
- Perpanjangan: tanggal mulai kontrak baru menyambung tanggal akhir kontrak lama dan rantainya tersimpan.
- Pindah kamar: riwayat tersimpan, kamar lama vacant, kamar baru occupied, harga baru dipakai kontrak.
- Dua penghuni dalam satu kontrak dengan tepat satu `is_primary`.
- `expiringWithin` benar di batas hari 30, 14, dan 7.
- Isolasi tenant untuk semua tabel baru.

**Selesai bila**: semua tes lulus, `/cek-fase 4` tanpa kegagalan, dan simulasi check-in sampai check-out untuk satu penghuni berjalan di UI tanpa mengedit data langsung.

**Commit**: `feat(kontrak): kontrak, check-in, check-out, perpanjangan, pindah kamar (fase 4)`

## Fase 5: Tagihan dan penjadwal

Fase 5 membuat tagihan bulanan terbit sendiri dari kontrak aktif, tanpa ganda dan tanpa terlewat. Ini inti produk dan menyangkut uang, jadi pakai persetujuan manual untuk setiap perubahan, dan jalankan tes pada database yang sama dengan produksi (bukan SQLite), karena perilaku penguncian baris dan batasan unik berbeda.

**Prasyarat**: Fase 4. **Rujukan**: PRD modul Tagihan (TGH-01 sampai TGH-07); Desain Database bagian Tagihan. **Tabel baru**: billing_settings, number_sequences, invoices, invoice_items, invoice_adjustments.

**Mockup**: `#tagihan`, `#tagihan-detail`, dan tab penagihan di `#pengaturan` pada `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 5 Kostra: tagihan dan penjadwal. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Tagihan" (TGH-01 sampai TGH-07) dan docs/database.md bagian "Tagihan".

Fase ini inti produk dan menyangkut uang. Rencanakan dengan teliti, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi billing_settings, number_sequences, invoices, invoice_items, invoice_adjustments persis sesuai docs/database.md, termasuk unique (lease_id, period) dan unique (tenant_id, number). Enum InvoiceStatus (draft, issued, partial, paid, overdue, void) dan InvoiceType.
2. Action GenerateInvoiceNumber: baca dan naikkan number_sequences dengan lockForUpdate di dalam transaksi; format INV-YYYY-MM-0001 mengikuti invoice_prefix tenant.
3. Action GenerateMonthlyInvoices (per tenant dan per kontrak aktif) yang idempoten: bila unique (lease_id, period) bentrok, lewati kontrak itu tanpa galat. Dukung pro rata bulan pertama dan terakhir sesuai billing_settings.
4. RecalculateInvoice (subtotal dari item, adjustment_total dari invoice_adjustments, total, paid_total, status), AddInvoiceAdjustment (alasan wajib, tercatat di audit), VoidInvoice (tagihan lunas tidak boleh dibatalkan), dan tagihan manual.
5. Ganti implementasi sementara OutstandingBalance dari Fase 4 dengan perhitungan dari invoices.
6. Penjadwal di routes/console.php: terbitkan tagihan harian per tenant sesuai issue_day, dan ubah issued atau partial yang lewat due_date menjadi overdue.
7. UI daftar tagihan (filter status, properti, periode), detail tagihan, dan tagihan manual.
```

**Hasil yang diharapkan**

- Lima migrasi, model, factory, dan Policy; enum `InvoiceStatus` dan `InvoiceType`.
- Action `GenerateInvoiceNumber`, `GenerateMonthlyInvoices`, `RecalculateInvoice`, `AddInvoiceAdjustment`, `VoidInvoice`.
- Penjadwal di `routes/console.php`.
- UI daftar dan detail tagihan.

**Tes wajib**

- Menjalankan generator dua kali pada hari yang sama tidak membuat tagihan ganda.
- Dua proses yang meminta nomor bersamaan tidak menghasilkan nomor kembar.
- Format nomor `INV-2026-10-0001` dan urutan per tenant per bulan.
- Pro rata untuk beberapa kasus (kontrak mulai di tengah bulan 28, 30, dan 31 hari; kontrak berakhir di tengah bulan).
- `total` selalu sama dengan `subtotal + adjustment_total`; tagihan lunas tidak bisa dibatalkan; penyesuaian wajib beralasan.
- Status overdue otomatis setelah `due_date` lewat, dan tagihan lunas tidak berubah.
- Check-out memakai sisa tagihan dari `invoices`.
- Isolasi tenant untuk semua tabel baru.

**Selesai bila**: semua tes lulus, `/cek-fase 5` tanpa kegagalan, menjalankan generator pada 100 kontrak uji menghasilkan 100 tagihan dan mengulanginya menghasilkan 0 tagihan tambahan.

**Commit**: `feat(tagihan): tagihan bulanan idempoten, penyesuaian, overdue (fase 5)`

## Fase 6: Pembayaran, bukti transfer, dan kuitansi

Fase 6 mencatat uang yang diterima pemilik di luar sistem dan menutup tagihan secara akurat. Kostra tidak menerima atau menahan dana; ia hanya mencatat. Seperti Fase 5, pakai persetujuan manual dan database yang sama dengan produksi untuk tes.

**Prasyarat**: Fase 5. **Rujukan**: PRD modul Pembayaran (PBY-01 sampai PBY-07); Desain Database bagian Pembayaran. **Tabel baru**: payment_accounts, payments, payment_allocations, payment_proofs.

**Mockup**: `#bukti`, modal catat pembayaran, dan modal tolak bukti di `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 6 Kostra: pembayaran, bukti transfer, dan kuitansi. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Pembayaran" (PBY-01 sampai PBY-07) dan docs/database.md bagian "Pembayaran".

Kostra tidak menerima atau menahan dana; hanya mencatat. Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi payment_accounts, payments, payment_allocations, payment_proofs persis sesuai docs/database.md (kuitansi berupa kolom di payments). Enum PaymentMethod, PaymentStatus, ProofStatus.
2. Action RecordPayment: dalam satu DB::transaction, kunci baris invoices yang terlibat (lockForUpdate), buat payment dan payment_allocations, pastikan jumlah alokasi tidak melebihi payments.amount maupun sisa tagihan (kelebihan bayar ditolak), lalu panggil RecalculateInvoice untuk paid_total dan status. Dukung pembayaran sebagian dan beberapa tagihan sekaligus.
3. VoidPayment: status voided dengan alasan wajib, hitung ulang tagihan terkait, catat audit. Pembayaran tidak pernah dihapus.
4. payment_proofs: SubmitPaymentProof (di fase ini lewat staf; portal menyusul di Fase 10), ApprovePaymentProof (membuat payment) dan RejectPaymentProof (alasan wajib). Berkas disimpan di attachments dengan kategori transfer_proof.
5. Nomor kuitansi lewat number_sequences (key receipt); halaman kuitansi yang bisa dicetak atau diunduh sebagai PDF (tanyakan paket PDF yang akan dipakai sebelum memasangnya).
6. payment_accounts: bank atau QRIS statis, boleh lebih dari satu, satu sebagai bawaan.
7. UI: catat pembayaran dari detail tagihan, antrean bukti transfer, dan daftar pembayaran.
```

**Hasil yang diharapkan**

- Empat migrasi, model, factory, dan Policy; tiga enum.
- Action `RecordPayment`, `VoidPayment`, `SubmitPaymentProof`, `ApprovePaymentProof`, `RejectPaymentProof`.
- Halaman kuitansi dan UI antrean bukti transfer.

**Tes wajib**

- Pembayaran sebagian membuat status partial; melunasi sisanya membuat status paid dan mengisi `paid_at`.
- Satu pembayaran melunasi dua tagihan lewat alokasi; alokasi melebihi `payments.amount` ditolak.
- Alokasi melebihi sisa tagihan ditolak.
- `VoidPayment` mengembalikan status tagihan, termasuk menjadi overdue bila sudah lewat jatuh tempo.
- Dua pencatatan bersamaan pada tagihan yang sama tidak bisa melewati sisa tagihan.
- Persetujuan bukti membuat pembayaran dan menutup tagihan; penolakan wajib beralasan; bukti pending lebih dari 3 hari muncul lewat scope pengingat.
- Nomor kuitansi unik per tenant.
- Pembatalan pembayaran hanya untuk owner atau staf berizin; isolasi tenant untuk semua tabel baru.

**Selesai bila**: semua tes lulus, `/cek-fase 6` tanpa kegagalan, dan satu siklus penuh (tagihan terbit, bukti diunggah, disetujui, kuitansi tercetak, laporan tunggakan berubah) berjalan di UI.

**Commit**: `feat(pembayaran): pembayaran, alokasi, bukti transfer, kuitansi (fase 6)`

## Fase 7: Pengeluaran, laporan, dan dashboard

Fase 7 menjawab tiga pertanyaan pemilik: berapa pemasukan bulan ini, siapa yang menunggak, dan berapa kamar yang terisi. Dashboard dan laporan harus memakai satu sumber query yang sama agar angkanya tidak pernah berbeda.

**Prasyarat**: Fase 6. **Rujukan**: PRD modul Keuangan dan Laporan (KEU-01 sampai KEU-07); Desain Database tabel expense_categories dan expenses. **Tabel baru**: expense_categories, expenses.

**Mockup**: `#pengeluaran`, modal pengeluaran, `#laporan`, dan statistik serta grafik batang di `#dashboard` pada `panel.html`. Pertahankan tooltip `data-tip` dan tabel "Lihat sebagai tabel" di grafik.

**Prompt untuk Claude Code**

```text
Fase 7 Kostra: pengeluaran, laporan, dashboard. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Keuangan dan Laporan" (KEU-01 sampai KEU-07) dan docs/database.md bagian "Pengeluaran dan komplain" (hanya expense_categories dan expenses).

Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi expense_categories dan expenses persis sesuai docs/database.md; softDeletes pada expenses. Kategori bawaan (listrik, air, gaji penjaga, perbaikan, kebersihan, lainnya) dibuat per tenant saat pendaftaran.
2. CRUD pengeluaran dengan lampiran nota (attachments, kategori expense_receipt) dan filter per properti dan kategori.
3. Kelas query laporan di app/Services/Reports: pemasukan per periode dan per properti (dasar: payments.paid_on dengan status active, lewat payment_allocations dan invoices.property_id), tunggakan dengan umur (1-7, 8-30, lebih dari 30 hari), okupansi per tanggal, dan rekap bulanan pemasukan dan pengeluaran dengan saldo bersih. Semua memakai zona waktu tenant.
4. Dashboard: okupansi, pemasukan bulan berjalan, total tunggakan, dan kontrak segera habis. Angkanya harus berasal dari kelas query yang sama dengan laporan.
5. Ekspor laporan ke Excel dan PDF memuat nama properti, periode, dan tanggal cetak (tanyakan paket ekspor yang akan dipakai sebelum memasangnya).
6. Izin: staf tidak melihat laporan keuangan kecuali memiliki izin finance.view.
```

**Hasil yang diharapkan**

- Dua migrasi, model, factory, Policy, dan seeder kategori.
- Kelas query di `app/Services/Reports` dan halaman laporan.
- Dashboard pemilik dan ekspor Excel dan PDF.

**Tes wajib**

- Angka dashboard sama dengan laporan untuk periode yang sama.
- Pemasukan hanya menghitung pembayaran berstatus active pada `paid_on` di dalam periode; pembayaran voided tidak masuk.
- Umur tunggakan benar di batas 7 dan 8 hari serta 30 dan 31 hari.
- Okupansi pada tanggal tertentu benar berdasarkan riwayat kontrak.
- Saldo bersih sama dengan pemasukan dikurangi pengeluaran.
- Staf tanpa `finance.view` mendapat penolakan pada laporan dan bagian keuangan dashboard.
- Ekspor memuat baris yang sama dengan laporan di layar.
- Isolasi tenant; dengan data uji 100 kamar dan 12 bulan, dashboard terbuka kurang dari 2 detik (periksa indeks).

**Selesai bila**: semua tes lulus, `/cek-fase 7` tanpa kegagalan, dan Anda bisa mencocokkan satu laporan bulanan dengan hitungan manual dari data uji.

**Commit**: `feat(keuangan): pengeluaran, laporan, dashboard (fase 7)`

## Fase 8: Komplain

Fase 8 membuat keluhan penghuni terlacak sampai selesai, bukan tenggelam di chat. Di MVP cukup tiket sederhana; portal penghuni (Fase 10) dan notifikasi (Fase 9) menyusul memakai event yang disiapkan di sini.

**Prasyarat**: Fase 3 dan 4. **Rujukan**: PRD modul Komplain dan Perawatan (KMP-01 sampai KMP-05); Desain Database tabel complaints dan complaint_comments. **Tabel baru**: complaints, complaint_comments.

**Mockup**: `#komplain` di `panel.html` dan layar komplain di `portal.html`.

**Prompt untuk Claude Code**

```text
Fase 8 Kostra: komplain. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Komplain dan Perawatan" (KMP-01 sampai KMP-05) dan docs/database.md tabel complaints dan complaint_comments.

Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi complaints dan complaint_comments persis sesuai docs/database.md. Enum ComplaintStatus (new, in_progress, done, rejected) dan ComplaintCategory.
2. Action CreateComplaint (di fase ini oleh staf atas nama penghuni; portal menyusul), ChangeComplaintStatus (done atau rejected mengisi resolved_at dan closed_at), AssignComplaint (hanya ke pengguna dari tenant yang sama), dan AddComplaintComment (tepat satu dari user_id atau resident_id terisi).
3. Setiap perubahan status memicu event ComplaintStatusChanged. Jangan mengirim pesan di fase ini; Fase 9 yang akan mendengarkan event tersebut.
4. Foto lewat attachments (kategori complaint_photo).
5. Scope Complaint::openLongerThan(days) untuk tiket terbuka lebih dari 3 hari, dan tanda di daftar.
6. UI daftar dengan filter status, kamar, kategori, dan penanggung jawab, plus halaman detail dengan komentar. Tiket tidak dihapus, hanya ditutup.
```

**Hasil yang diharapkan**

- Dua migrasi, model, factory, dan Policy; dua enum.
- Action `CreateComplaint`, `ChangeComplaintStatus`, `AssignComplaint`, `AddComplaintComment`; event `ComplaintStatusChanged`.
- UI daftar dan detail tiket.

**Tes wajib**

- Transisi status yang sah berjalan; `done` dan `rejected` mengisi `closed_at`.
- Tiket terbuka lebih dari 3 hari tertangkap oleh scope `openLongerThan`.
- Komentar menolak data tanpa penulis atau dengan dua penulis sekaligus.
- Tidak ada rute atau Action penghapusan tiket.
- Penugasan ke pengguna tenant lain ditolak.
- Event `ComplaintStatusChanged` terkirim pada setiap perubahan status.
- Isolasi tenant untuk kedua tabel.

**Selesai bila**: semua tes lulus, `/cek-fase 8` tanpa kegagalan, dan satu tiket bisa dibuat, ditugaskan, dikomentari, dan ditutup di UI.

**Commit**: `feat(komplain): tiket komplain dan komentar (fase 8)`

## Fase 9: Notifikasi WhatsApp dan kuota

Fase 9 mengirim pengingat tagihan dan kuitansi lewat WhatsApp dengan biaya terkendali, tanpa terkunci pada satu penyedia. Penyedia sungguhan belum dipasang di sini: bangun lapisan abstraksi dan penyedia palsu dulu, supaya seluruh logika kuota, jam kirim, dan opt-out teruji tanpa mengirim pesan nyata.

**Prasyarat**: Fase 5 dan 6 (Fase 8 untuk event komplain). **Rujukan**: PRD modul Notifikasi WhatsApp (NTF-01 sampai NTF-08); Desain Database tabel message_templates, message_logs, dan usage_counters. **Tabel baru**: message_templates, message_logs, usage_counters.

**Mockup**: `#pesan` di `panel.html`.

**Prompt untuk Claude Code**

```text
Fase 9 Kostra: notifikasi WhatsApp dan kuota. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Notifikasi WhatsApp" (NTF-01 sampai NTF-08) dan docs/database.md bagian "Notifikasi, portal, dan audit" (message_templates, message_logs) serta tabel usage_counters.

Jangan mengintegrasikan penyedia WhatsApp sungguhan di fase ini; bangun lapisan abstraksi dan penyedia palsu. Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi message_templates, message_logs, usage_counters persis sesuai docs/database.md.
2. Interface WhatsAppProvider (kirim pesan, baca status webhook) dengan FakeWhatsAppProvider untuk tes dan lokal; penyedia dipilih lewat config/kostra.php.
3. MessageTemplateRenderer dengan variabel {{nama}}, {{jumlah}}, {{jatuh_tempo}}, {{link}} (variabel tak dikenal ditolak); seeder template sistem (invoice_issued, reminder_before_due, reminder_due_today, reminder_overdue, receipt, lease_expiring). Pemilik boleh mengedit isi dalam batas variabel itu, dengan category selalu utility.
4. Job SendWhatsAppMessage lewat antrean dengan percobaan ulang terbatas; setiap pesan dicatat di message_logs dengan status queued, sent, delivered, failed, atau skipped (skip_reason: opted_out, quota_exceeded, outside_window).
5. Kuota atomik: usage_counters dinaikkan dengan pembaruan bersyarat (used = used + 1 hanya bila used di bawah batas paket) supaya pengiriman paralel tidak melewati kuota.
6. Jam kirim: pesan di luar send_window di billing_settings ditunda ke jam kirim berikutnya, bukan dibuang. Penghuni dengan whatsapp_opt_out_at tidak menerima pesan otomatis.
7. Penjadwal pengingat memakai reminder_offsets di billing_settings (misalnya -3, 0, 3), hanya untuk tagihan yang belum lunas, dan tidak mengirim dua kali untuk kombinasi tagihan, template, dan offset yang sama (periksa lewat message_logs; bila butuh indeks tambahan, tanyakan dulu karena menyimpang dari docs/database.md).
8. Listener untuk penerbitan tagihan (Fase 5), pembayaran disetujui (Fase 6), dan ComplaintStatusChanged (Fase 8) yang mengantrekan pesan.
9. UI: template, log pengiriman dengan kirim ulang pesan gagal, dan sisa kuota bulan berjalan.
```

**Hasil yang diharapkan**

- Tiga migrasi, model, factory, dan Policy.
- `WhatsAppProvider`, `FakeWhatsAppProvider`, `MessageTemplateRenderer`, job `SendWhatsAppMessage`.
- Penjadwal pengingat, listener event, dan UI template, log, kuota.

**Tes wajib**

- Penghuni yang opt-out dicatat `skipped` dengan alasan `opted_out`, tidak dikirim.
- Kuota habis menghasilkan `skipped` dengan alasan `quota_exceeded`.
- Dua puluh job paralel dengan sisa kuota 5 menghasilkan tepat 5 pesan terkirim.
- Pesan di luar jam kirim ditunda ke jam berikutnya.
- Pengiriman gagal dicoba ulang sampai batas, lalu berstatus `failed` dengan kode galat.
- Penjadwal yang berjalan dua kali tidak mengirim pengingat ganda, dan tagihan lunas tidak diingatkan.
- Renderer menolak variabel tak dikenal; template tenant tidak bisa mengubah category menjadi bukan utility.
- Isolasi tenant untuk template, log, dan penghitung.

**Selesai bila**: semua tes lulus, `/cek-fase 9` tanpa kegagalan, dan seluruh alur (tagihan terbit, pengingat, kuitansi) berjalan dengan penyedia palsu serta tercatat di log.

**Keputusan sebelum penyedia nyata**: pilih penyedia WhatsApp resmi dan periksa tabel tarif Meta untuk Indonesia. Meta menagih per pesan template yang terkirim, dan pesan pengingat termasuk template utility; tarif Indonesia belum saya verifikasi. Setelah itu jalankan prompt kecil terpisah: "Buat adapter \<penyedia> untuk interface WhatsAppProvider beserta penanganan webhook status, dengan kredensial dari .env dan tanpa mengubah modul lain."

**Commit**: `feat(notifikasi): lapisan pesan, kuota, pengingat dengan penyedia palsu (fase 9)`

## Fase 10: Portal penghuni

Fase 10 memberi penghuni halaman tagihan lewat tautan di WhatsApp, tanpa akun dan tanpa aplikasi. Portal adalah satu-satunya permukaan yang terbuka untuk publik, jadi keamanan lebih penting daripada tampilan: token tidak boleh bisa ditebak, dan satu penghuni tidak boleh bisa melihat data penghuni lain.

**Prasyarat**: Fase 5, 6, 8, dan 9. **Rujukan**: PRD modul Portal Penghuni (PRT-01 sampai PRT-08); Desain Database tabel portal_tokens. **Tabel baru**: portal_tokens.

**Mockup**: seluruh `portal.html` (beranda, bayar dengan tombol salin rekening dan unggah bukti, terkirim, riwayat, komplain). Layout mobile-first dengan tabbar bawah.

**Prompt untuk Claude Code**

```text
Fase 10 Kostra: portal penghuni tanpa akun. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Portal Penghuni" (PRT-01 sampai PRT-08) dan docs/database.md tabel portal_tokens.

Portal adalah permukaan publik, jadi keamanan adalah prioritas. Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi portal_tokens persis sesuai docs/database.md. Token acak panjang; hanya hash SHA-256 yang disimpan, token asli hanya ada di tautan.
2. Middleware ResolvePortalToken: memvalidasi token (ada, belum dicabut, belum kedaluwarsa), mengisi last_used_at, menaruh resident di request, dan memulai sesi terbatas. Satu token = satu penghuni = satu tenant. Semua kegagalan memakai pesan yang sama agar token tidak bisa ditebak.
3. routes/portal.php di bawah /p/{token}: beranda (tagihan aktif dan status kontrak), riwayat tagihan dan pembayaran, rekening pembayaran dengan tombol salin, unggah bukti transfer, kuitansi, ringkasan kontrak, serta pengajuan dan pemantauan komplain.
4. Semua akses data lewat resident dari token, tidak pernah lewat id mentah dari URL tanpa pemeriksaan kepemilikan (cegah IDOR). Portal hanya menampilkan ringkasan kontrak, bukan data internal pemilik.
5. Rate limit pada endpoint token dan unggahan; unggahan bukti divalidasi (JPG, PNG, PDF, maksimum 5 MB) dan masuk ke payment_proofs berstatus pending, tidak langsung menutup tagihan.
6. Pemilik dapat membuat, mengirim ulang, dan mencabut tautan portal dari profil penghuni (kirim lewat Fase 9).
7. Tampilan mobile-first (lebar 360 px ke atas), ringan, berbahasa Indonesia.
```

**Hasil yang diharapkan**

- Migrasi, model, dan factory `portal_tokens`; middleware `ResolvePortalToken`.
- `routes/portal.php`, controller atau komponen di `app/Http/Controllers/Portal`, dan tampilan di `resources/views/portal`.
- Tombol buat, kirim ulang, dan cabut tautan di profil penghuni.

**Tes wajib**

- Token tidak valid, dicabut, atau kedaluwarsa menghasilkan respons yang sama dan tidak membocorkan alasannya.
- Penghuni A tidak bisa membuka tagihan, kuitansi, atau komplain penghuni B walau mengetahui id-nya.
- Token dari tenant lain tidak membuka data tenant ini.
- Unggahan bukti membuat `payment_proofs` berstatus pending beserta lampirannya dan tidak mengubah status tagihan.
- Validasi ukuran dan tipe berkas; rate limit memblokir percobaan berulang.
- Pencabutan token langsung menutup akses.
- Sesi portal tidak bisa mengakses rute pemilik atau admin.
- Beranda portal tidak melebihi jumlah query yang wajar (tidak ada N+1).

**Selesai bila**: semua tes lulus, `tenant-auditor` tidak melaporkan temuan tinggi pada `routes/portal.php`, dan Anda membuka portal dari ponsel sungguhan untuk satu penghuni uji sampai bukti transfer masuk antrean.

**Commit**: `feat(portal): portal penghuni berbasis token (fase 10)`

## Fase 11: Langganan dan panel Super Admin

Fase 11 membuat Kostra sendiri bisa dijual: paket, batas pemakaian, masa percobaan, dan panel internal untuk mengelola tenant. Penagihan langganan boleh semi-manual di MVP (transfer yang dicatat Super Admin) agar peluncuran tidak tertunda.

**Prasyarat**: Fase 1 dan 9. **Rujukan**: PRD modul Langganan Kostra dan Super Admin (LGN-01 sampai LGN-07); Desain Database tabel subscriptions dan subscription_invoices. **Tabel baru**: subscriptions, subscription_invoices.

**Mockup**: `#langganan` di `panel.html`. Panel Super Admin belum punya mockup; pakai kerangka dan komponen yang sama.

**Prompt untuk Claude Code**

```text
Fase 11 Kostra: langganan dan panel Super Admin. Baca CLAUDE.md, docs/prd.md bagian "PRD modul Langganan Kostra dan Super Admin" (LGN-01 sampai LGN-07) dan docs/database.md tabel subscriptions dan subscription_invoices.

Pembayaran langganan Kostra semi-manual di MVP (transfer, dicatat Super Admin). Rencanakan, tulis tes lebih dulu, lalu implementasikan:
1. Migrasi subscriptions dan subscription_invoices persis sesuai docs/database.md. Enum SubscriptionStatus.
2. Pendaftaran tenant (Fase 1) membuat subscription paket Gratis; tenants.plan_id disinkronkan dengan subscription aktif.
3. Masa percobaan 14 hari untuk paket berbayar. Penjadwal harian mengembalikan tenant ke paket Gratis bila tidak berlangganan setelah masa percobaan, dan memberi masa tenggang 7 hari sebelum penangguhan karena tunggakan langganan.
4. Penegakan batas paket di semua titik pembuatan data lewat PlanLimits (properti, kamar, staf, kuota pesan) dan pembatasan fitur lewat plans.features; pelanggaran menampilkan tawaran naik paket, bukan galat teknis.
5. Naik dan turun paket; turun paket diperiksa terhadap jumlah properti, kamar, dan staf yang ada.
6. Tagihan langganan (subscription_invoices) bulanan atau tahunan dengan nomor tersendiri; Super Admin mencatat pembayaran manual.
7. Panel Super Admin di routes/admin.php: daftar tenant (cari dalam satu langkah), status langganan, pemakaian kamar dan pesan, tangguhkan dan aktifkan tenant. Tenant yang ditangguhkan hanya bisa membaca dan mengekspor.
8. Ekspor seluruh data tenant (Excel atau CSV) untuk pemilik, tersedia juga saat langganan berakhir.
```

**Hasil yang diharapkan**

- Dua migrasi, model, factory, dan Policy; enum `SubscriptionStatus`.
- Action untuk mulai percobaan, ganti paket, tangguhkan, aktifkan, dan catat pembayaran langganan.
- Penjadwal masa percobaan dan masa tenggang.
- `routes/admin.php` dan tampilan panel Super Admin; ekspor data tenant.

**Tes wajib**

- Pendaftaran menghasilkan subscription Gratis, dan batas 1 properti serta 10 kamar ditegakkan.
- Setelah 14 hari percobaan tanpa pembayaran tenant kembali ke Gratis; penangguhan baru terjadi setelah masa tenggang 7 hari.
- Turun paket ditolak bila jumlah kamar melebihi batas paket tujuan.
- Tenant ditangguhkan: menulis ditolak, membaca dan ekspor tetap boleh.
- Naik paket langsung membuka fitur dan batas baru.
- Hanya `super_admin` yang bisa mengakses `/admin`; owner dan staf mendapat penolakan.
- Ekspor hanya memuat data tenant yang bersangkutan.
- Nomor `subscription_invoices` unik.

**Selesai bila**: semua tes lulus, `/cek-fase 11` tanpa kegagalan, dan satu tenant uji menjalani siklus daftar, percobaan, naik paket, bayar manual, dan tangguhan tanpa mengedit database langsung.

**Commit**: `feat(langganan): paket, percobaan, penegakan batas, panel admin (fase 11)`

## Fase 12: Hardening, deploy, dan persiapan pilot

Fase 12 memastikan MVP aman dan stabil sebelum pemilik kos sungguhan memakainya. Tidak ada fitur baru di sini: yang dikerjakan adalah audit tenant, performa, penjadwal dan antrean, konfigurasi produksi, data demo, dan panduan deploy.

**Prasyarat**: Fase 0 sampai 11 selesai dan ter-commit. **Rujukan**: PRD bagian Kebutuhan non-fungsional (NFR-01 sampai NFR-12); Desain Database bagian Indeks, constraint, dan aturan integritas. **Tabel baru**: tidak ada.

**Prompt untuk Claude Code**

```text
Fase 12 Kostra: hardening sebelum pilot. Jangan menambah fitur. Baca CLAUDE.md, docs/prd.md bagian "Kebutuhan non-fungsional" dan docs/database.md bagian "Indeks, constraint, dan aturan integritas". Rencanakan dulu.

1. Jalankan subagent tenant-auditor pada seluruh app/ dan routes/, laporkan temuan berurut tingkat keparahan, dan simpan di docs/audit-fase-12.md. Perbaiki temuan tinggi dengan tes yang membuktikannya.
2. Audit performa dengan data uji 100 kamar serta 12 bulan tagihan dan pembayaran: periksa N+1 pada daftar utama dan dashboard, lalu tambahkan eager loading atau indeks sesuai docs/database.md.
3. Periksa penjadwal dan antrean: semua pekerjaan idempoten, percobaan ulang terbatas, dan pekerjaan gagal terlihat.
4. Periksa konfigurasi produksi: APP_DEBUG=false, cookie aman, HTTPS, rate limit login dan portal, dan log yang tidak memuat data pribadi (isi KTP, token portal, nomor telepon utuh).
5. Buat perintah artisan demo:seed yang membuat tenant demo dengan data realistis untuk mendemokan ke calon pelanggan.
6. Tulis docs/deploy.md: variabel .env yang dibutuhkan, urutan migrasi, worker antrean, cron untuk `php artisan schedule:run` setiap menit, pencadangan harian, dan langkah pemulihan.
```

**Hasil yang diharapkan**

- `docs/audit-fase-12.md` dan perbaikan temuan tinggi beserta tesnya.
- Perintah `demo:seed`.
- `docs/deploy.md`.
- Perubahan indeks atau eager loading bila audit performa menemukan masalah.

**Pemeriksaan wajib**

- Seluruh suite tes hijau dan `pint --test` bersih.
- `tenant-auditor` tidak menyisakan temuan tinggi atau menengah yang belum dicatat.
- Penjadwal dijalankan dua kali berturut-turut tanpa tagihan atau pesan ganda.
- Uji pemulihan: pulihkan cadangan ke database baru lalu jalankan uji asap.
- Dashboard dan daftar 100 kamar terbuka kurang dari 2 detik pada data uji.
- Log dan galat produksi tidak memuat data pribadi.

**Di luar kode, siapkan sebelum pilot**

- Draf kebijakan privasi dan ketentuan layanan; minta penasihat hukum meninjaunya. Ini bukan nasihat hukum.
- Pencatatan persetujuan penghuni atas pengumpulan datanya.
- Daftar 3 sampai 5 pemilik kos pilot, bantuan input data awal, dan kanal dukungan WhatsApp.
- Metrik pilot yang sama dengan PRD: tagihan terbit otomatis, tunggakan, waktu onboarding, pemilik aktif setelah 8 minggu, dan kesediaan membayar.

**Selesai bila**: semua pemeriksaan di atas lulus, `docs/deploy.md` sudah dicoba pada server uji, dan satu tenant demo berjalan dari pendaftaran sampai laporan tanpa intervensi manual di database.

**Commit**: `chore: hardening dan persiapan pilot (fase 12)`, lalu beri tag `v0.1.0-pilot`.

## Panduan saat Claude Code macet

Masalah yang paling mungkin muncul adalah Claude menyimpang dari skema, tes yang gagal berulang, dan konteks yang penuh. Tabel ini memasangkan tiap masalah dengan tindakan dan prompt yang bisa langsung ditempel.

| Masalah | Tindakan | Prompt atau perintah |
| --- | --- | --- |
| Kode menyimpang dari skema | Berhenti dan minta perbandingan, jangan dilanjutkan | "Bandingkan migrasi yang baru dibuat dengan docs/database.md tabel demi tabel. Daftarkan semua perbedaan nama kolom, tipe, dan indeks. Jangan mengubah apa pun." |
| Tes gagal berulang | Batasi ke satu tes dan minta penyebab sebelum perbaikan | "Jalankan hanya tes \<nama>. Jelaskan penyebabnya sebelum mengubah kode. Jangan mengubah tes agar lulus kecuali tesnya yang salah, dan sebutkan alasannya." |
| Rencana tidak sesuai | Pilih "No, keep planning" dan beri koreksi, atau tekan `Ctrl+G` untuk menyunting rencana | Sebutkan bagian yang salah dan acuannya di docs |
| Konteks penuh atau jawaban melantur | `/compact` di tengah fase, `/clear` di antara fase; periksa dengan `/context` bahwa CLAUDE.md termuat | Lanjutkan dengan "Lanjutkan Fase N dari langkah \<nomor>" |
| Aturan dilanggar berulang | Tambahkan ke CLAUDE.md; untuk larangan mutlak pakai aturan izin `deny` atau hook, karena CLAUDE.md hanya konteks, bukan penegakan | Perbaiki dengan satu kalimat yang bisa diverifikasi |
| Instruksi tidak dipatuhi | Cari instruksi yang saling bertentangan | `/doctor prompt-audit` (butuh Claude Code yang cukup baru) |
| Dicurigai kebocoran data antar tenant | Panggil subagent pemeriksa | "Gunakan subagent tenant-auditor untuk memeriksa perubahan di cabang ini dibanding main." |
| Diff terlalu besar untuk ditinjau | Minta dipecah per tabel atau per Action dan commit kecil | "Pecah perubahan ini menjadi commit kecil per migrasi, per Action, dan per UI." |
| Perlu membatalkan | Kembali lewat git di cabang fase; `/rewind` juga tersedia, tetapi git tetap sumber kebenaran | `git restore` atau `git reset` pada cabang fase |
| Melanjutkan di hari lain | Lanjutkan sesi sebelumnya atau mulai bersih dari catatan commit | `claude --resume`, atau sesi baru dengan `/fase N` |

### Checklist akhir tiap fase

- [ ] Seluruh tes hijau dan `./vendor/bin/pint --test` bersih
- [ ] `/cek-fase N` tanpa kegagalan
- [ ] Anda sudah membaca `git diff` sendiri, terutama migrasi dan kode uang
- [ ] `tenant-auditor` dijalankan setelah menambah rute, model, atau lampiran (wajib di Fase 3, 10, dan 12)
- [ ] Tidak ada perubahan di `docs/` yang tidak Anda minta
- [ ] Commit di cabang fase, lalu `/clear` sebelum fase berikutnya
