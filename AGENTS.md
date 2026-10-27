# AGENTS.md

Panduan untuk agen AI (Claude Code, Codex, Cursor, dan sejenisnya) yang bekerja di repo Kostra. Manusia juga boleh membacanya. Bila `CLAUDE.md` ada, isinya cukup `@AGENTS.md` ditambah catatan khusus Claude Code, supaya aturan tidak ganda.

## Produk

Kostra adalah SaaS manajemen kos untuk pemilik kos kecil sampai menengah (5 sampai 50 kamar). Fungsi inti: tagihan bulanan otomatis, pencatatan pembayaran dan bukti transfer, pengingat WhatsApp, portal penghuni tanpa akun, laporan keuangan dasar. Dijual dengan langganan bulanan. Pengembang tunggal.

Kostra tidak menahan, menerima, atau menyalurkan uang pemilik atau penghuni. Semua pembayaran dicatat setelah terjadi di luar sistem.

## Sumber kebenaran

Baca bagian yang relevan saja, jangan seluruh berkas.

| Berkas | Isi |
| --- | --- |
| `docs/prd.md` | Kebutuhan per modul, ID kebutuhan (FND-01, TGH-02, dan seterusnya), aturan bisnis |
| `docs/database.md` | Skema 33 tabel, indeks, constraint, aturan transaksi |
| `docs/implementation-plan.md` | Fase 0 sampai 12, prompt dan kriteria selesai tiap fase |
| `DESIGN.md` | Sistem desain (warna, tipografi, komponen). Sumber kebenaran visual; menang atas mockup bila berbeda |
| `docs/mockup/` | Acuan tampilan. Mulai dari `docs/mockup/README.md`, lalu hanya bagian layar terkait |
| `ARCHITECTURE.md` | Bentuk sistem, lapisan, alur kritis, keputusan terbuka |

Bila berkas-berkas ini bertentangan dengan kode atau dengan instruksi Anda saat ini, berhenti dan tanya. Jangan memilih diam-diam.

## Perintah

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev            # server, queue, vite (periksa composer.json bila skrip berbeda)
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
routes/{web,owner,portal,admin,console}.php
resources/views/{layouts,components,owner,portal,auth,admin}/
```

## Aturan wajib

### Uang
- Simpan dan hitung sebagai integer rupiah (`unsignedInteger` untuk harga katalog, `unsignedBigInteger` atau `bigInteger` untuk total dan saldo, sesuai `docs/database.md`). Dilarang `float` dan `decimal` untuk uang.
- Format ke tampilan hanya di lapisan view.

### Tenant
- Setiap model milik pemilik kos memakai trait `BelongsToTenant` (global scope `tenant_id`, isi otomatis saat membuat).
- Dilarang `withoutGlobalScopes`, `DB::table` pada tabel tenant, atau query mentah tanpa `tenant_id`, kecuali diminta eksplisit dan diberi komentar alasannya.
- Pekerjaan antrean dan perintah artisan harus menetapkan tenant secara eksplisit. Jangan mengandalkan pengguna yang login.
- Setiap fitur baru wajib punya tes isolasi: pengguna tenant A tidak bisa membaca atau mengubah data tenant B lewat UI maupun URL.

### Lapisan
- Logika bisnis di `app/Actions`. Controller dan komponen UI tipis: validasi, otorisasi, panggil Action, kembalikan respons.
- Validasi lewat Form Request. Otorisasi lewat Policy. Tidak ada route tanpa middleware.
- Aksi yang mengubah lebih dari satu tabel dibungkus `DB::transaction`.
- Status memakai PHP Enum di `app/Enums`. Jangan menulis string status langsung di kode.

### Aturan domain yang mudah salah
- **Satu kontrak aktif per kamar:** dijaga unique `leases.active_room_id` di database, bukan hanya validasi.
- **Tagihan bulanan idempoten:** unique `(lease_id, period)`. Menjalankan penjadwal dua kali tidak boleh menghasilkan tagihan ganda.
- **Nomor dokumen:** dari `number_sequences` dengan `lockForUpdate` di dalam transaksi. Nomor tidak boleh berlubang atau ganda.
- **Tagihan terbit tidak diubah jumlahnya.** Koreksi lewat `invoice_adjustments`. Pembatalan lewat status `void`, bukan hapus.
- **Pembayaran dialokasikan** lewat `payment_allocations`. `invoices.paid_total` dihitung dari alokasi pembayaran berstatus `active` dan dihitung ulang saat pembayaran dibatalkan. Alokasi tidak boleh melebihi jumlah pembayaran maupun sisa tagihan.
- **Data keuangan tidak dihapus.** Hanya `void`.
- **Status kamar `occupied`** ditentukan oleh kontrak aktif, bukan diatur manual.
- **Kuota pesan WhatsApp** dinaikkan atomik saat pesan dikirim, dan pengiriman berhenti saat kuota habis.

### Berkas dan privasi
- Semua lampiran lewat tabel `attachments` di disk privat, disajikan lewat tautan bertanda tangan. Foto KTP dienkripsi dan aksesnya dicatat ke `audit_logs`.
- **Nomor KTP tidak disimpan.** Tidak sebagai kolom, tidak di log, tidak di catatan.
- Token portal penghuni hanya disimpan sebagai hash SHA-256. Token asli hanya ada di tautan WhatsApp.
- Jangan menulis data pribadi (nama, telepon, foto) ke log aplikasi.

### UI
- Teks antarmuka berbahasa Indonesia. Identifier kode berbahasa Inggris.
- Ikuti `DESIGN.md` untuk warna, tipografi, dan komponen, lalu `docs/mockup/` untuk tata letak, teks, dan kelas komponen. Perilaku dan data mengikuti PRD dan `docs/database.md`; JavaScript di mockup hanya peraga.
- Gaya lewat kelas komponen di `resources/css/app.css` dan komponen Blade (`x-button`, `x-status-badge`, `x-room-plate`, `x-panel`, `x-modal`). Jangan menambah warna di luar token DESIGN.md (emerald `primary`, Slate Navy `secondary`, Amber `tertiary`, warna status). Amber bukan warna teks di atas putih; pakai `#92400E`. Semua animasi dibungkus `motion-safe`.
- Status selalu ditampilkan dengan teks dan warna, tidak warna saja.

### Pustaka
- Jangan memasang paket Composer atau npm baru tanpa menyebut nama paket, versi, dan alasannya lebih dulu.
- Hindari paket yang tidak terawat. Periksa kompatibilitas Laravel 12 sebelum mengusulkan.

## Cara bekerja

1. Mulai dengan rencana singkat: berkas yang akan disentuh, tabel yang terlibat, risiko.
2. Tulis tes untuk aturan uang dan isolasi tenant lebih dulu, lalu implementasi.
3. Kerjakan satu fase atau satu fitur kecil per sesi. Jangan melompati prasyarat fase.
4. Sebelum selesai: jalankan `./vendor/bin/pint` dan seluruh tes. Ringkas berkas yang berubah dan setiap penyimpangan dari dokumen.
5. Nama tabel dan kolom mengikuti `docs/database.md` persis. Bila perlu menyimpang, berhenti dan tanya.

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
- Teks UI Indonesia dan tampilan sesuai DESIGN.md dan mockup di lebar desktop dan ponsel (360 px ke atas).
- Penyimpangan dari PRD atau skema dicatat di ringkasan akhir.

## Commit

Pesan commit ringkas, gaya Conventional Commits: `feat(tagihan): terbitkan tagihan bulanan otomatis`, `fix(pembayaran): ...`, `chore: ...`. Satu perubahan logis per commit.
