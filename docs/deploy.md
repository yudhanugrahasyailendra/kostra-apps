# Panduan deploy Kostra

Status: **belum dicoba di server uji.** Perintah di bawah disusun dari kode dan konfigurasi repo; jalankan sekali di server uji sebelum pilot dan perbaiki berkas ini bila ada yang berbeda.

## 1. Kebutuhan server

- PHP 8.2 atau lebih baru dengan ekstensi `mbstring`, `openssl`, `pdo_mysql`, `ctype`, `json`, `tokenizer`, `xml`, `zip` (ekspor), `gd` atau `imagick` (PDF dan gambar).
- MySQL (gunakan versi yang sama di uji dan produksi; lokal memakai 9.1.0). Jalankan suite tes pada versi itu sebelum rilis.
- Composer, Node.js untuk membangun aset (bisa di mesin build, bukan di server).
- Nginx atau Apache dengan HTTPS (Let's Encrypt) dan root pada `public/`.
- Supervisor (atau systemd) untuk worker antrean, dan cron.
- Disk privat untuk lampiran (`storage/app/private`), ikut dicadangkan.

## 2. Variabel `.env` produksi

```dotenv
APP_NAME=Kostra
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.contoh-kostra.id
APP_KEY=                      # php artisan key:generate --show; JANGAN diganti setelah ada data (KTP terenkripsi)
APP_LOCALE=id
APP_FALLBACK_LOCALE=id

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kostra
DB_USERNAME=kostra
DB_PASSWORD=                  # kata sandi kuat, pengguna khusus dengan hak hanya pada database ini

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true    # otomatis true di production bila dikosongkan; tulis eksplisit
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# IP proxy balik (Nginx di mesin lain, load balancer, Cloudflare), dipisah koma. Tanpa ini pembatas laju login
# dan portal melihat semua klien sebagai satu IP. Isi * hanya bila server hanya bisa dijangkau lewat proxy.
TRUSTED_PROXIES=

KOSTRA_DISPLAY_TIMEZONE=Asia/Makassar
KOSTRA_WHATSAPP_PROVIDER=fake # tetap fake sampai penyedia resmi diputuskan (ARCHITECTURE.md bagian 15)

# Super Admin pertama untuk SuperAdminSeeder (hapus dari .env setelah seeder dijalankan)
KOSTRA_ADMIN_EMAIL=
KOSTRA_ADMIN_PASSWORD=

MAIL_MAILER=smtp              # atur sesuai penyedia email; reset sandi memakai ini
```

Aturan: `.env` tidak di-commit, izin berkas `600`, pemilik pengguna web. Simpan salinan `APP_KEY` di pengelola kata sandi terpisah dari cadangan database; tanpa kunci ini foto KTP terenkripsi tidak dapat dibuka.

Untuk peringatan ke pengembang (NFR-09, NFR-11) arahkan log `critical` dan `error` ke saluran yang dibaca, mis. `LOG_STACK=daily,slack` dengan `LOG_SLACK_WEBHOOK_URL`. Pesan log hanya memuat nama kelas pekerjaan dan galat, tanpa data pribadi.

## 3. Rilis pertama

```bash
git clone <repo> /var/www/kostra && cd /var/www/kostra
git checkout v0.1.0-pilot
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # atau bangun di mesin build lalu salin public/build
cp .env.example .env               # lalu isi sesuai bagian 2
php artisan key:generate           # hanya pada instalasi baru
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan db:seed --class=MessageTemplateSeeder --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan storage:link           # bila aset publik dipakai
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
```

Urutan migrasi: satu kali `php artisan migrate --force` menjalankan seluruh migrasi berurutan menurut stempel waktu. Jangan pernah `migrate:fresh` atau `db:wipe` di server selain lokal.

`PlanSeeder` memakai `updateOrCreate`, jadi aman dijalankan ulang. Harga dan kuota pesan di seeder masih penanda (PRD bagian 14); ubah sebelum pelanggan membayar.

## 4. Rilis berikutnya

```bash
php artisan down --retry=60
git pull && git checkout <tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
php artisan queue:restart          # worker memuat kode baru
php artisan up
```

Cadangkan database sebelum `migrate` (bagian 7). Tidak ada migrasi destruktif di repo; bila suatu rilis menambahkannya, tulis di catatan rilis.

## 5. Worker antrean (Supervisor)

`/etc/supervisor/conf.d/kostra-worker.conf`:

```ini
[program:kostra-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/kostra/artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/kostra/storage/logs/worker.log
stopwaitsecs=3600
```

`--timeout=90` harus lebih kecil dari `retry_after` (90 detik bawaan `DB_QUEUE_RETRY_AFTER`); naikkan `retry_after` bila timeout dinaikkan. Pekerjaan gagal tersimpan di tabel `failed_jobs`: lihat dengan `php artisan queue:failed`, ulangi dengan `php artisan queue:retry <uuid>`.

## 6. Cron

Satu baris, tiap menit, sebagai pengguna web:

```cron
* * * * * cd /var/www/kostra && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal (`php artisan schedule:list`): `invoices:generate`, `reminders:send`, dan `subscriptions:process` tiap jam; `invoices:mark-overdue` pukul 01:00 UTC; `queue:monitor` tiap 5 menit; `queue:prune-failed` harian. Semuanya idempoten dan `onOneServer`; `onOneServer` memakai cache database, jadi tidak perlu Redis pada satu server. Bila server dua, pastikan semua memakai cache yang sama.

## 7. Pencadangan harian (NFR-07)

Retensi 14 hari. Contoh `/usr/local/bin/kostra-backup.sh`, dijalankan cron harian pukul 02:30:

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%F)
DEST=/var/backups/kostra
mkdir -p "$DEST"

mysqldump --single-transaction --routines --triggers \
  --defaults-extra-file=/root/.kostra-my.cnf kostra | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/files-$STAMP.tar.gz" -C /var/www/kostra/storage/app private

find "$DEST" -type f -mtime +14 -delete
# Salin ke luar server (rclone, S3, atau mesin lain). Cadangan di server yang sama bukan cadangan.
```

Berkas kredensial `/root/.kostra-my.cnf` (`[client] user=... password=...`) izin `600`. Cadangan berisi data pribadi: simpan terenkripsi di luar server dan batasi aksesnya. `APP_KEY` disimpan terpisah (bagian 2).

## 8. Pemulihan dan uji asap

Lakukan di database baru, bukan menimpa produksi, dan ulangi berkala (NFR-07).

```bash
mysql -e "CREATE DATABASE kostra_pulih CHARACTER SET utf8mb4"
gunzip -c /var/backups/kostra/db-YYYY-MM-DD.sql.gz | mysql kostra_pulih
tar -xzf /var/backups/kostra/files-YYYY-MM-DD.tar.gz -C /tmp/kostra-pulih

# Arahkan salinan aplikasi ke database itu (DB_DATABASE=kostra_pulih di .env sementara), lalu:
php artisan migrate:status          # semua migrasi berstatus Ran
php artisan tinker --execute="echo App\Models\Tenant::count();"
```

Uji asap (manual): masuk sebagai pemilik, buka dashboard, daftar tagihan, satu tagihan, satu foto lampiran (harus terbuka dengan `APP_KEY` asli), lalu ekspor data. Catat tanggal dan hasil uji pemulihan di tempat yang bisa dilihat.

Pemulihan darurat produksi: `php artisan down`, pulihkan database dan `storage/app/private` dari cadangan terakhir, `php artisan migrate --force` bila skema cadangan lebih lama dari kode, `php artisan config:cache`, `php artisan queue:restart`, `php artisan up`. Pesan WhatsApp yang diantrekan sejak cadangan hilang; periksa `message_logs` dan jalankan `reminders:send` (idempoten per tagihan, template, dan hari).

## 9. Tenant demo untuk calon pelanggan

```bash
php artisan demo:seed --rooms=30 --months=6 --email=demo@contoh-kostra.id
```

Menolak berjalan di production tanpa `--force`, menolak email yang sudah dipakai, dan menolak bila `KOSTRA_WHATSAPP_PROVIDER` bukan `fake`. Kata sandi acak ditampilkan sekali. Jangan menjalankannya di server yang melayani pelanggan sungguhan kecuali untuk tenant demo yang disengaja.

## 10. Daftar periksa sebelum membuka untuk pilot

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS aktif, `curl -I http://...` dialihkan ke HTTPS.
- [ ] `TRUSTED_PROXIES` diisi bila ada proxy; uji: dua klien berbeda tidak berbagi batas laju.
- [ ] Worker berjalan (`supervisorctl status`), cron aktif (`schedule:list`), `queue:failed` kosong.
- [ ] Cadangan harian berjalan dan salinan di luar server ada; uji pemulihan sukses.
- [ ] `/up` merespons 200 dan dipantau dari luar (UptimeRobot atau serupa).
- [ ] Log tidak memuat nomor telepon, email, atau tautan portal utuh: `grep -E "\+62|/p/[a-f0-9]{20}" storage/logs/*.log` kosong.
- [ ] Super Admin dibuat dan `KOSTRA_ADMIN_PASSWORD` dihapus dari `.env`.
- [ ] Kebijakan privasi dan ketentuan layanan sudah ditinjau penasihat hukum.
