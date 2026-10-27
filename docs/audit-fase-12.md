# Audit Fase 12

Dua audit baca-kode (agen `tenant-auditor`): audit Fase 11 (langganan) dan audit seluruh aplikasi. Hasil: **tidak ada temuan Kritis; tidak ada jalur satu pemilik kos membaca atau mengubah data pemilik lain.** Audit dilakukan dengan membaca kode; kesimpulan diperkuat tes otomatis (lihat kolom Tes).

## Temuan dan status

| ID | Tingkat | Temuan | Status | Tes |
| --- | --- | --- | --- | --- |
| F11-1 | Tinggi | Turun paket dari langganan yang belum dibayar menyalin periode tanpa bayar | Diperbaiki: model bayar-dulu (status `pending`), turun hanya dari langganan aktif | SubscriptionLifecycleTest |
| F11-2 | Tinggi | Berlangganan, tunggu tenggang habis, ulangi: paket berbayar nyaris terus-menerus tanpa bayar | Diperbaiki: paket terbuka hanya saat pembayaran dicatat | SubscriptionLifecycleTest |
| F11-3 | Sedang | Permintaan paralel `RequestSubscription`/`StartTrial` | Diperbaiki: kunci tenant di dalam transaksi | AuditFase12Test |
| F11-4 | Sedang | Pembayaran membuka kembali tenant yang ditangguhkan manual | Diperbaiki: alasan penangguhan (`arrears`/`manual`) | SubscriptionLifecycleTest |
| F11-5 | Sedang | Batas staf bisa dilewati lewat undangan paralel | Diperbaiki: periksa di transaksi dengan kunci tenant | AuditFase12Test |
| F11-8 | Rendah | Peran diperiksa setelah route binding (403 vs 404) | Diperbaiki: `EnsureRole` masuk daftar prioritas | AdminPanelTest |
| F11-10 | Rendah | Ekspor menarik semua baris untuk ringkasan, tanpa throttle, apostrof di nomor `+62` | Diperbaiki | TenantExportTest |
| S1 | Sedang | `report($e)` menulis SQL dan nilai binding (nama, catatan) ke log | Diperbaiki: pelapor galat DB global tanpa binding; log per kontrak hanya kelas dan id | AuditFase12Test, ProductionReadinessTest |
| S2 | Sedang | Anonimisasi tidak membersihkan log pesan, keluhan, catatan bukti/pembayaran, tautan portal | Diperbaiki | AuditFase12Test |
| S3 | Sedang | IP proksi tidak dipercaya (pembatas laju portal bisa dipakai memblokir semua penghuni) | Diperbaiki: `TRUSTED_PROXIES` (wajib diisi di production, lihat deploy.md) | `kostra:check` |
| S4 | Sedang | `AcceptInvitation` tidak memeriksa ulang batas paket dan status tenant | Diperbaiki | AuditFase12Test |
| R2 | Rendah | Check-out tidak mencabut tautan portal | Diperbaiki | AuditFase12Test |
| R3 | Rendah | Unggahan penghuni tanpa batas jumlah | Diperbaiki: 3 bukti menunggu per tagihan, 5 keluhan terbuka per penghuni | AuditFase12Test |
| R4 | Rendah | Percobaan gratis bisa dimulai dua kali lewat permintaan paralel | Diperbaiki (bagian F11-3) | AuditFase12Test |
| R5 | Rendah | Token undangan di tabel `sessions` | Dimitigasi: sesi terenkripsi otomatis di production | `kostra:check` |
| S5 | Sedang | `audit_logs` menyimpan PII untuk model non-penghuni (`User`, `Invitation`, `PaymentAccount`, teks bebas) | **Terbuka** | - |
| R1 | Rendah | Peran dicek setelah route binding (403 vs 404), sama dengan F11-8 | Diperbaiki | AdminPanelTest |
| R6 | Rendah | `provider->send()` di dalam transaksi pengiriman: kegagalan commit setelah kirim bisa memicu kirim ganda | **Terbuka**; tidak berdampak selama penyedia `fake` | - |
| F11-9/11 | Rendah | Guard GET `CreateFreeSubscription`, `reference` pembayaran langganan tidak unik, throttle rute admin, event audit khusus aksi admin | **Terbuka** | - |

## Pemeriksaan Fase 12 lain

- **Indeks** terdokumentasi (`docs/database.md` bagian 11) diuji keberadaannya.
- **Performa:** halaman dashboard, kamar, penghuni, tagihan, pembayaran tidak menambah query saat data bertambah (tanpa N+1) dan < 2 detik pada data uji. Catatan: uji memakai puluhan baris, bukan 100 kamar x 12 bulan; ukur ulang di staging dengan `demo:seed` atau data nyata.
- **Penjadwal:** `billing:run`, `subscriptions:run`, `messages:reminders` dijalankan dua kali tanpa data ganda. Semua job punya batas percobaan; kegagalan masuk `failed_jobs`.
- **Log:** tes memindai `app/`; hanya 3 listener pesan, penerbitan tagihan, dan pelapor galat DB yang menulis ke log, tanpa pesan galat maupun data pribadi.
- **Produksi:** `kostra:check`, header keamanan (HSTS pada HTTPS), HTTPS paksa, cookie aman dan sesi terenkripsi otomatis di production.

## Penyimpangan dari dokumen

- Status langganan baru `pending` (belum ada di `docs/database.md`).
- `tenants.settings.suspension_reason` dipakai untuk membedakan penangguhan tunggakan dan manual.

## Batas yang diketahui untuk pilot

- Penyedia WhatsApp nyata belum ada (`fake`): pengingat tercatat tetapi tidak terkirim.
- Masa percobaan sekali per tenant; pendaftaran akun ganda untuk percobaan lagi tidak dicegah.
- Registrasi tanpa verifikasi email/nomor; pemilik belum bisa menonaktifkan staf dari UI.
- Latihan pemulihan cadangan belum dijalankan (lihat deploy.md).
- UI belum diverifikasi di peramban/ponsel nyata.
