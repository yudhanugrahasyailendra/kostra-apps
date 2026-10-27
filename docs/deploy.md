# Deploy dan operasional Kostra (pilot)

Panduan memasang Kostra di satu server (VPS) dengan MySQL 8, PHP 8.2+, dan web server Nginx/Apache.

## 1. Prasyarat

PHP 8.2+ (ekstensi: mbstring, intl, gd, pdo_mysql, zip, bcmath, fileinfo), MySQL 8, Composer, Node 20+ (hanya untuk build aset), Supervisor, cron.

## 2. Variabel `.env` production

| Variabel | Nilai | Catatan |
| --- | --- | --- |
| `APP_ENV` | `production` | Mengaktifkan HTTPS paksa, cookie aman, sesi terenkripsi |
| `APP_DEBUG` | `false` | Wajib |
| `APP_KEY` | `php artisan key:generate` | Jangan diganti setelah ada data (KTP terenkripsi dengan kunci ini) |
| `APP_URL` | `https://domain-anda` | Harus https |
| `DB_*` | kredensial MySQL | Pengguna khusus aplikasi, bukan root |
| `QUEUE_CONNECTION` | `database` (atau `redis`) | Jangan `sync` |
| `SESSION_DRIVER` | `database` | `SESSION_SECURE_COOKIE` dan `SESSION_ENCRYPT` otomatis `true` di production |
| `MAIL_*` | SMTP nyata | `log` menulis tautan undangan dan reset kata sandi ke berkas log |
| `TRUSTED_PROXIES` | alamat proksi, atau `*` | Wajib bila di belakang Nginx/Cloudflare/penyeimbang beban, supaya IP klien benar (pembatas laju) |
| `WHATSAPP_PROVIDER` | `fake` | Penyedia nyata belum tersedia; pesan tidak benar-benar terkirim |
| `SUBSCRIPTION_BANK`, `SUBSCRIPTION_ACCOUNT_NUMBER`, `SUBSCRIPTION_ACCOUNT_NAME` | rekening Anda | Ditampilkan di halaman Langganan pemilik |
| `SUPERADMIN_NAME/EMAIL/PASSWORD` | akun Super Admin | Dipakai seeder saat pertama kali |
| `LOG_LEVEL` | `warning` | Log aplikasi tidak memuat data pribadi, tetap batasi |

Jangan commit `.env`.

## 3. Pemasangan pertama

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force          # paket, templat pesan, Super Admin (bila SUPERADMIN_* terisi)
php artisan storage:link             # hanya untuk aset publik; lampiran memakai disk privat
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan kostra:check             # semua baris harus OK
```

Urutan migrasi mengikuti nama berkas (`database/migrations`). Jangan pernah `migrate:fresh` atau `db:wipe` di server.

Berkas `storage/` dan `bootstrap/cache/` harus bisa ditulis oleh pengguna web server. Lampiran tersimpan di `storage/app/private` dan tidak boleh dilayani langsung oleh web server.

## 4. Antrean dan penjadwal

Pekerja antrean (Supervisor), mis. `/etc/supervisor/conf.d/kostra-worker.conf`:

```ini
[program:kostra-worker]
command=php /var/www/kostra/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/kostra/storage/logs/worker.log
```

Setelah deploy: `php artisan queue:restart`.

Cron (satu baris, tiap menit):

```
* * * * * cd /var/www/kostra && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal: `billing:run` 01:00, `subscriptions:run` 01:30, `messages:reminders` 02:00 UTC. Semuanya idempoten (aman bila berjalan dua kali atau terlewat sehari) dan memakai `onOneServer` + `withoutOverlapping`; `onOneServer` memerlukan cache bersama (database/redis), bukan `array`.

Job yang gagal setelah percobaan terakhir masuk tabel `failed_jobs`:

```bash
php artisan queue:failed            # lihat
php artisan queue:retry all         # ulangi
php artisan queue:flush             # buang (setelah ditinjau)
```

## 5. Pencadangan

- Basis data: `mysqldump --single-transaction --routines kostra | gzip > kostra-$(date +%F).sql.gz` setiap hari, simpan minimal 14 hari, salinan di luar server.
- Berkas: `storage/app/private` (lampiran) dicadangkan bersama basis data. Berkas KTP terenkripsi dengan `APP_KEY`: **cadangkan `APP_KEY` secara terpisah dan aman**, tanpanya foto KTP tidak bisa dibuka.

### Pemulihan

1. Pasang kode di server bersih (bagian 3, tanpa `db:seed`).
2. `gunzip -c kostra-TANGGAL.sql.gz | mysql kostra`
3. Pulihkan `storage/app/private` dan `.env` (dengan `APP_KEY` yang sama).
4. `php artisan migrate --force` (menerapkan migrasi yang lebih baru dari cadangan), `php artisan kostra:check`.
5. Buka satu lampiran dan satu tagihan sebagai uji asap.

> Catatan jujur: latihan pemulihan sungguhan belum dijalankan di lingkungan pengembangan ini (klien `mysql` tidak tersedia). Lakukan sekali di server staging sebelum pilot dan catat durasinya.

## 6. Keamanan

- HTTPS wajib (HSTS dikirim otomatis pada permintaan HTTPS). Header keamanan dasar dikirim aplikasi.
- Tautan portal penghuni berbentuk `/p/{token}`: samarkan path `/p/*` di log akses web server/proksi.
- Super Admin: gunakan kata sandi panjang dan unik.

## 7. Data demo

`php artisan demo:seed` membuat tenant demo (`demo@kostra.test`, kata sandi acak yang dicetak) untuk staging. Menolak berjalan di production tanpa `--force` dan bila tenant demo sudah ada. Jangan dipakai bersama data pelanggan asli.

## 8. Daftar periksa sebelum pilot

- [ ] `php artisan kostra:check` semua OK
- [ ] Cron dan pekerja antrean berjalan; `failed_jobs` kosong
- [ ] Cadangan harian berjalan dan satu pemulihan sudah dilatih
- [ ] `TRUSTED_PROXIES` benar (cek IP klien di log/login)
- [ ] Rekening langganan terisi; Super Admin bisa mencatat pembayaran langganan
- [ ] Batas yang diketahui dipahami (lihat `docs/audit-fase-12.md`)
