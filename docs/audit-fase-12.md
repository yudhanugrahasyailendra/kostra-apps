# Audit Fase 12: isolasi tenant, performa, penjadwal, konfigurasi produksi

Dibuat saat Fase 12 (hardening sebelum pilot). Pemeriksa isolasi tenant adalah subagent `tenant-auditor` yang membaca seluruh `app/`, `routes/`, dan `bootstrap/app.php`, bukan hanya diff. Bagian performa, penjadwal, dan produksi diukur lewat tes di `tests/Feature/Hardening/`.

## 1. Isolasi tenant

**Kesimpulan:** tidak ada celah baca atau tulis lintas tenant yang dapat dieksploitasi. Tidak ada temuan tinggi. 3 menengah, 6 rendah.

Yang diperiksa dan lolos:

- Seluruh 31 model milik pemilik memakai `BelongsToTenant`; hanya `Tenant` dan `Plan` (katalog global) yang tidak.
- `TenantScope` gagal tertutup tanpa tenant; `tenant_id` yang berbeda dari konteks ditolak saat membuat dan tidak dapat diubah.
- Tidak ada `DB::table` atau `withoutGlobalScopes` kecuali `TenantUserProvider` (terdokumentasi). Semua `TenantContext::unscoped()` punya komentar alasan dan filter manual.
- Route model binding terscope tenant (`SetCurrentTenant` didahulukan sebelum `SubstituteBindings`). Setiap route `owner.*` yang mengubah data punya Form Request atau `authorize()`.
- Portal tanpa route model binding; semua id dicari lewat `resident_id` dari token. Resource portal hanya memuat kolom layar itu.
- Props Inertia tidak memuat `token_hash`, path berkas sensitif, atau kolom tenant lain.
- Job dan command menetapkan tenant eksplisit lewat `runAs`.

### Temuan menengah

| ID | Temuan | Status |
| --- | --- | --- |
| M1 | Token portal hidup tersimpan di `message_logs.body` untuk pesan gagal (dan ikut ekspor). Redaksi hanya terjadi saat status `sent` atau `skipped`. | **Diperbaiki.** `DeliverWhatsAppMessage::fail()` menyamarkan token; `ResendWhatsAppMessage` menerbitkan token baru. Pesan yang masih `queued` menyimpan token sampai terkirim (jendela singkat); lihat sisa risiko. Tes: `AuditFixesTest`. |
| M2 | Siklus hidup token portal longgar: check-out tidak mencabut token, token lama tidak dicabut saat token baru terbit, penghuni terhapus tidak dicabut. | **Sebagian.** Hapus penghuni kini mencabut token. Check-out dan penerbitan token baru **sengaja belum diubah**: lihat keputusan terbuka K1. |
| M3 | Staf tanpa `finance.view` melihat `rent_amount`, detail deposit, dan `outstanding` di layar kontrak dan check-out. | **Belum diubah.** Keputusan produk, lihat K2. Bukan kebocoran antartenant. |

### Temuan rendah

| ID | Temuan | Status |
| --- | --- | --- |
| R1 | `AttachmentPolicy::delete` sama dengan `view`; staf yang boleh melihat dokumen non-sensitif boleh menghapusnya. | Belum diubah, lihat K3. |
| R2 | Pembatas laju memakai `$request->ip()` tanpa `trustProxies`. | **Diperbaiki.** `TRUSTED_PROXIES` (config `kostra.trusted_proxies`) dipasang di `AppServiceProvider`. Wajib diisi di produksi, lihat `docs/deploy.md`. |
| R3 | `ApplyProviderStatus` mencari lintas tenant hanya lewat `provider_message_id`. | Belum terjangkau (belum ada route webhook). Saat webhook dibuat: tambah unique `(provider, provider_message_id)`, filter `provider`, dan verifikasi tanda tangan. Dicatat sebagai prasyarat penyedia WhatsApp sungguhan. |
| R4 | Pesan unique global pada pendaftaran dan undangan membuka enumerasi akun. | Disengaja (email adalah kunci login); dibatasi throttle yang ada. |
| R5 | `audit_logs` memuat email dan telepon pengguna dan undangan. | **Diperbaiki.** `User::$auditExclude` kini memuat `email` dan `phone`; payload `invited_staff` tidak lagi menyimpan keduanya. |
| R6 | `invoices:mark-overdue` tanpa `try/catch` per tenant. | **Diperbaiki.** Satu tenant gagal tidak menghentikan putaran; exit code gagal bila ada. |

### Keputusan terbuka (perlu jawaban pemilik produk)

- **K1.** Haruskah check-out mencabut token portal mantan penghuni? Mencabut menutup akses 90 hari ke tagihan lama, tetapi mantan penghuni tidak lagi bisa melihat atau membayar tunggakan terakhir lewat portal. PRD (PRT-01) tidak menentukan.
- **K2.** Haruskah `outstanding` dan rincian deposit di layar kontrak dan check-out hanya untuk pengguna dengan `finance.view`? Komentar `LeasePolicy` menyebut staf penjaga boleh check-in dan check-out.
- **K3.** Haruskah hapus lampiran penghuni khusus pemilik, seperti `ResidentPolicy::delete`?

### Sisa risiko yang diterima

- Pesan `queued` atau yang sedang dicoba ulang menyimpan token portal hidup di `message_logs.body` sampai terkirim (menit). Token itu berlaku 90 hari dan hanya terlihat oleh pemilik tenant yang sama dan ekspornya.
- Pemeriksa tidak membaca sisi Vue dan tidak menjalankan tes.

## 2. Performa (NFR-05)

Tes `tests/Feature/Hardening/PerformanceTest.php` (grup `performance`) membuat tenant 100 kamar, 12 bulan tagihan, 90% hunian (lebih dari 900 tagihan dan ratusan pembayaran) lewat `DemoTenantBuilder`, lalu mengukur setiap halaman utama sebagai pemilik. Batas: kurang dari 60 query dan kurang dari 2000 ms per halaman.

| Halaman | Query | Waktu (ms) |
| --- | --- | --- |
| dashboard | 14 | 206 |
| kamar | 8 | 84 |
| penghuni | 5 | 47 |
| kontrak | 11 | 149 |
| tagihan | 9 | 119 |
| pembayaran | 8 | 83 |
| pengeluaran | 16 | 38 |
| komplain | 11 | 63 |
| pesan | 15 | 59 |
| log-audit | 6 | 38 |
| laporan | 4 | 27 |

Tidak ditemukan N+1 dan tidak ada indeks tambahan yang diperlukan, jadi **tidak ada migrasi baru**. Catatan: waktu diukur di mesin pengembangan dalam proses tes (tanpa jaringan dan tanpa aset Vue); ukur ulang di server uji. Halaman detail dan laporan dengan rentang tanggal panjang belum diukur.

## 3. Penjadwal dan antrean (NFR-04, NFR-09)

- Keempat perintah terjadwal berjalan `withoutOverlapping` dan `onOneServer` (tes `SchedulerTest`).
- Seluruh penjadwal dijalankan dua kali berturut-turut pada tenant demo: jumlah tagihan, pembayaran, pesan, dan tagihan langganan tidak berubah.
- `SendWhatsAppMessage`: `$tries = 10` sebagai pengaman, batas percobaan sebenarnya `kostra.whatsapp.max_attempts = 3` dengan jeda 60 dan 300 detik; `failed()` menandai pesan gagal dan mengembalikan kuota.
- Baru: pekerjaan gagal dicatat (`Pekerjaan antrean gagal`, hanya nama kelas dan kelas galat, tanpa payload), antrean menumpuk lebih dari 100 dicatat `critical` lewat `queue:monitor` tiap 5 menit, dan `queue:prune-failed` membuang catatan gagal setelah 14 hari.
- Untuk memenuhi "peringatan ke pengembang" (NFR-09) arahkan log `critical` ke Slack atau email; lihat `docs/deploy.md`.

## 4. Konfigurasi produksi (NFR-01, NFR-11)

- `URL::forceHttps()` di production; cookie sesi `secure` otomatis di production; `http_only` dan `same_site=lax` sudah bawaan.
- `trustProxies` lewat `TRUSTED_PROXIES`.
- Rate limit: login 5 percobaan per kunci, pendaftaran dan reset sandi 6 sampai 10 per menit, portal per IP dan per penghuni (sudah ada sebelum fase ini).
- Log: kanal `single` dan `daily` memakai `App\Logging\RedactPersonalData` yang menyamarkan token portal, hash 64 heksadesimal, email, dan nomor telepon Indonesia pada pesan maupun jejak tumpukan (termasuk pesan galat SQL yang memuat nilai binding). Nama orang tidak dapat dikenali dari pola, jadi aturan "jangan mencatat nama" tetap berlaku untuk kode.
- `APP_DEBUG=false` wajib di production (daftar `.env` di `docs/deploy.md`).

## 5. Ekspor data (NFR-12)

`ExportCompletenessTest`: setiap model ber-`BelongsToTenant` terdaftar di registri (kecuali `AuditLog`, sengaja), tidak ada kolom terlarang (`password`, `token_hash`, `path`, `tenant_id`, dan sejenisnya), dan ekspor tenant demo tidak memuat data tenant lain maupun hash kata sandi.

## 6. Belum dikerjakan atau tidak dapat diverifikasi dari sesi ini

- Suite tes pada versi MySQL yang akan dipakai produksi.
- Uji pemulihan cadangan ke database baru.
- `docs/deploy.md` dicoba di server uji.
- Satu tenant demo dari pendaftaran sampai laporan tanpa intervensi database (`demo:seed` mendekati ini, tetapi belum diuji manual lewat browser).
- Pekerjaan di luar kode (kebijakan privasi, persetujuan penghuni, daftar pilot).
