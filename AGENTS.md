# AGENTS.md

Panduan untuk agen AI (Claude Code, Codex, Cursor, dan sejenisnya) yang bekerja di repo Kostra. Manusia juga boleh membacanya. Bila `CLAUDE.md` ada, isinya cukup `@AGENTS.md` ditambah catatan khusus Claude Code, supaya aturan tidak ganda.

## Produk

Kostra adalah SaaS manajemen kos untuk pemilik kos kecil sampai menengah (5 sampai 50 kamar). Fungsi inti: tagihan bulanan otomatis, pencatatan pembayaran dan bukti transfer, pengingat WhatsApp, portal penghuni tanpa akun, laporan keuangan dasar. Dijual dengan langganan bulanan. Pengembang tunggal.

Kostra tidak menahan, menerima, atau menyalurkan uang pemilik atau penghuni. Semua pembayaran dicatat setelah terjadi di luar sistem.

## Sumber kebenaran

Baca bagian yang relevan saja, jangan seluruh berkas.

| Berkas | Isi |
| --- | --- |
| `docs/plan/PRD.md` | Kebutuhan per modul, ID kebutuhan (FND-01, TGH-02, dan seterusnya), aturan bisnis |
| `docs/plan/database.md` | Skema 33 tabel, indeks, constraint, aturan transaksi |
| `docs/plan/implementation-plan.md` | Fase 0 sampai 12, prompt dan kriteria selesai tiap fase |
| `docs/plan/DESIGN.md` | Sistem desain (warna, tipografi, komponen). Sumber kebenaran visual; menang atas mockup bila berbeda |
| `docs/plan/mockup/` | Acuan tampilan. Satu folder per layar (`<nama-layar>/code.html` dan `screen.png`); buka hanya folder layar terkait. Peta fase ke layar: `implementation-plan.md` bagian 2.6. `mockup/prd.md` dan `mockup/kostra_rapi/DESIGN.md` adalah salinan lama dari Stitch dan tidak dipakai |
| `ARCHITECTURE.md` | Bentuk sistem, lapisan, alur kritis, keputusan terbuka |

Bila berkas-berkas ini bertentangan dengan kode atau dengan instruksi Anda saat ini, berhenti dan tanya. Jangan memilih diam-diam.

## Perintah

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev            # server, queue, vite (periksa composer.json bila skrip berbeda)
npm run build               # bangun aset Vue; lint dan cek tipe: lihat skrip di package.json
php artisan test            # atau: composer test
./vendor/bin/pint           # format, jalankan sebelum selesai
php artisan route:list      # periksa route dan middleware
```

## Struktur singkat

```
app/Actions/        satu kelas satu aksi bisnis
app/Enums/          status dan tipe (PHP Enum, disimpan sebagai string)
app/Models/         Eloquent; Concerns/BelongsToTenant
app/Policies/       otorisasi per model
app/Services/       integrasi luar (WhatsApp, penyimpanan)
app/Jobs/           pekerjaan antrean
app/Http/Controllers/{Owner,Portal,Admin}/
app/Http/Resources/  bentuk data yang dikirim ke halaman (API Resource)
routes/{web,owner,portal,admin,console}.php
resources/js/Pages/{Auth,Owner,Portal,Admin}/   satu halaman Vue per layar
resources/js/Components/                         komponen bersama (Button, StatusBadge, RoomPlate, Panel, Modal, Drawer, DataTable)
resources/js/Layouts/                            OwnerLayout, PortalLayout, GuestLayout, AdminLayout
resources/js/lib/                                helper (formatRupiah, tanggal)
resources/views/app.blade.php                    satu-satunya view Blade (akar Inertia)
```

## Aturan wajib

### Uang
- Simpan dan hitung sebagai integer rupiah (`unsignedInteger` untuk harga katalog, `unsignedBigInteger` atau `bigInteger` untuk total dan saldo, sesuai `docs/plan/database.md`). Dilarang `float` dan `decimal` untuk uang.
- Kirim ke halaman sebagai angka bulat. Format `Rp 1.850.000` hanya di lapisan tampilan, lewat satu helper `formatRupiah` di `resources/js/lib`.

### Tenant
- Setiap model milik pemilik kos memakai trait `BelongsToTenant` (global scope `tenant_id`, isi otomatis saat membuat).
- Dilarang `withoutGlobalScopes`, `DB::table` pada tabel tenant, atau query mentah tanpa `tenant_id`, kecuali diminta eksplisit dan diberi komentar alasannya.
- Pekerjaan antrean dan perintah artisan harus menetapkan tenant secara eksplisit. Jangan mengandalkan pengguna yang login.
- Setiap fitur baru wajib punya tes isolasi: pengguna tenant A tidak bisa membaca atau mengubah data tenant B lewat UI maupun URL.

### Lapisan
- Logika bisnis di `app/Actions`. Controller dan halaman Vue tipis: validasi, otorisasi, panggil Action, kembalikan `Inertia::render` atau redirect.
- Validasi lewat Form Request. Otorisasi lewat Policy. Tidak ada route tanpa middleware.
- Aksi yang mengubah lebih dari satu tabel dibungkus `DB::transaction`.
- Status memakai PHP Enum di `app/Enums`. Jangan menulis string status langsung di kode.

### Aturan domain yang mudah salah
- **Satu kontrak aktif per kamar:** dijaga unique `leases.active_room_id` di database, bukan hanya validasi.
- **Tagihan bulanan idempoten:** unique `(lease_id, period)`. Menjalankan penjadwal dua kali tidak boleh menghasilkan tagihan ganda.
- **Nomor dokumen:** dari `number_sequences` dengan `lockForUpdate` di dalam transaksi. Nomor tidak boleh berlubang atau ganda.
- **Tagihan terbit tidak diubah jumlahnya.** Koreksi lewat `invoice_adjustments`. Pembatalan lewat status `void`, bukan hapus.
- **Pembayaran dialokasikan** lewat `payment_allocations`. `invoices.paid_total` dihitung dari alokasi pembayaran berstatus `active` dan dihitung ulang saat pembayaran dibatalkan. Alokasi tidak boleh melebihi jumlah pembayaran maupun sisa tagihan.
- **Data keuangan tidak dihapus.** Tagihan, penyesuaian, pembayaran, dan alokasi hanya `void`. Pengeluaran (`expenses`) memakai soft delete.
- **Status kamar `occupied`** ditentukan oleh kontrak aktif, bukan diatur manual.
- **Kuota pesan WhatsApp** dinaikkan atomik saat pesan dikirim, dan pengiriman berhenti saat kuota habis.

### Ekspor data
- Setiap model milik tenant yang baru didaftarkan ke registri ekspor data tenant (`TenantExportable`, dibuat di Fase 1; NFR-12). Kolom rahasia (`password`, `token_hash`, kunci) dikecualikan dari ekspor.

### Berkas dan privasi
- Semua lampiran lewat tabel `attachments` di disk privat, disajikan lewat tautan bertanda tangan. Foto KTP dienkripsi dan aksesnya dicatat ke `audit_logs`.
- **Nomor KTP tidak disimpan.** Tidak sebagai kolom, tidak di log, tidak di catatan.
- Token portal penghuni hanya disimpan sebagai hash SHA-256. Token asli hanya ada di tautan WhatsApp.
- Jangan menulis data pribadi (nama, telepon, foto) ke log aplikasi.

### Inertia dan Vue
- Halaman menerima props dari API Resource atau DTO. Dilarang mengirim model Eloquent mentah: semua yang ada di props terlihat di browser.
- Jangan pernah mengirim `token_hash`, token portal, path berkas sensitif (foto KTP), kolom tenant lain, atau data pribadi yang tidak dipakai layar itu.
- Menyembunyikan tombol di Vue bukan otorisasi. Kirim izin dari Policy (mis. `can`) sebagai props, dan tetap periksa lagi di server.
- Form memakai Form Request di server; pesan galat validasi ditampilkan dari `errors` Inertia, dalam bahasa Indonesia.
- TypeScript: definisikan tipe props per halaman. Satu halaman Vue satu layar di `docs/plan/mockup/<nama-layar>/`.
- Tes fitur memakai asersi Inertia (komponen dan props), termasuk tes bahwa props tidak memuat kolom terlarang.

### UI
- Teks antarmuka berbahasa Indonesia. Identifier kode berbahasa Inggris.
- Ikuti `docs/plan/DESIGN.md` untuk warna, tipografi, dan komponen, lalu `docs/plan/mockup/` untuk tata letak, teks, dan kelas komponen. Perilaku dan data mengikuti PRD dan `docs/plan/database.md`; JavaScript di mockup hanya peraga.
- Gaya lewat token di `resources/css/app.css` dan komponen Vue (`Button.vue`, `StatusBadge.vue`, `RoomPlate.vue`, `Panel.vue`, `Modal.vue`). Jangan menyalin deretan kelas utilitas panjang ke tiap halaman. Jangan menambah warna di luar token DESIGN.md (emerald `primary`, Slate Navy `secondary`, Amber `tertiary`, warna status). Amber bukan warna teks di atas putih; pakai `#92400E`. Semua animasi dibungkus `motion-safe`.
- Status selalu ditampilkan dengan teks dan warna, tidak warna saja.

### Pustaka
- Jangan memasang paket Composer atau npm baru tanpa menyebut nama paket, versi, dan alasannya lebih dulu.
- Hindari paket yang tidak terawat. Periksa kompatibilitas Laravel 12 sebelum mengusulkan.

## Cara bekerja

1. Mulai dengan rencana singkat: berkas yang akan disentuh, tabel yang terlibat, risiko.
2. Tulis tes untuk aturan uang dan isolasi tenant lebih dulu, lalu implementasi.
3. Kerjakan satu fase atau satu fitur kecil per sesi. Jangan melompati prasyarat fase.
4. Sebelum selesai: jalankan `./vendor/bin/pint` dan seluruh tes. Ringkas berkas yang berubah dan setiap penyimpangan dari dokumen.
5. Nama tabel dan kolom mengikuti `docs/plan/database.md` persis. Bila perlu menyimpang, berhenti dan tanya.

## Batas yang tidak boleh dilanggar

- Jangan mengubah berkas di `docs/` kecuali diminta.
- Jangan commit `.env`, kunci, atau data pelanggan asli. Gunakan seeder untuk data contoh.
- Jangan `git push`, `git reset --hard`, atau menghapus cabang tanpa diminta.
- Jangan menjalankan migrasi destruktif (`migrate:fresh`, `db:wipe`) pada database selain lokal.
- Jangan menambah integrasi pembayaran yang menahan dana. Itu di luar tujuan produk.
- Jangan mengirim pesan WhatsApp sungguhan dari lingkungan pengembangan. Pakai `FakeProvider`.

## Definisi selesai

- Tes baru dan tes lama hijau.
- Pint bersih.
- Tes isolasi tenant ada untuk fitur yang menyentuh data pemilik.
- Tidak ada query tanpa scope tenant, tidak ada float untuk uang.
- Teks UI Indonesia dan tampilan sesuai `docs/plan/DESIGN.md` dan mockup di lebar desktop dan ponsel (360 px ke atas).
- Penyimpangan dari PRD atau skema dicatat di ringkasan akhir.

## Commit

Pesan commit ringkas, gaya Conventional Commits: `feat(tagihan): terbitkan tagihan bulanan otomatis`, `fix(pembayaran): ...`, `chore: ...`. Satu perubahan logis per commit.
