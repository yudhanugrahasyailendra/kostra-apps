# PRD Kostra - Manajemen Kos

Diperbarui 20 Oktober 2026

## 1. PRD induk

Kostra adalah SaaS berbasis web untuk pemilik kos kecil-menengah (5-50 kamar) yang mengganti catatan manual dan chat WhatsApp dengan tagihan otomatis, pencatatan pembayaran, dan laporan keuangan. Dibangun dengan Laravel 12, multi-tenant, dijual dengan langganan bulanan. Semua angka target dan harga di dokumen ini adalah usulan awal yang perlu divalidasi dengan pilot.

### Masalah

- Pemilik mencatat sewa di buku, Excel, atau chat WhatsApp, sehingga tunggakan sulit dilacak.
- Tagihan dan pengingat dikirim manual tiap bulan.
- Data penghuni, kontrak, dan bukti pembayaran tersebar di banyak tempat.
- Laporan pemasukan dan okupansi harus dihitung sendiri.

### Pengguna

| Peran | Kebutuhan utama | Akses |
| --- | --- | --- |
| Pemilik | Melihat okupansi, tunggakan, dan pemasukan; mengatur kos | Panel penuh untuk tenant-nya |
| Staf / penjaga | Mencatat pembayaran dan komplain | Panel dengan menu terbatas |
| Penghuni | Melihat tagihan, mengunggah bukti bayar, mengajukan komplain | Portal lewat link, tanpa akun |
| Super Admin Kostra | Mengelola tenant, paket, dan penagihan langganan | Panel internal |

### Tujuan dan metrik (usulan)

| Tujuan | Metrik | Target pilot |
| --- | --- | --- |
| Tagihan terbit otomatis | Persentase tagihan bulanan yang terbit tanpa input manual | 95% |
| Tunggakan turun | Rasio tagihan lewat jatuh tempo 7 hari | Turun dibanding catatan awal pemilik |
| Onboarding cepat | Waktu dari daftar sampai tagihan pertama terkirim | Kurang dari 30 menit |
| Adopsi | Pemilik pilot yang masih aktif setelah 8 minggu | 4 dari 5 |
| Kesediaan membayar | Pilot yang memilih paket berbayar | 3 dari 5 |

### Ruang lingkup

| Tahap | Isi |
| --- | --- |
| MVP | Properti dan kamar, penghuni, kontrak, tagihan otomatis, pencatatan pembayaran manual dan bukti transfer, laporan dasar, pengingat WhatsApp, portal penghuni, langganan paket |
| Tahap berikutnya | Booking calon penghuni, meteran listrik dan air, denda otomatis, pembayaran QRIS/VA, kalender okupansi, jadwal perawatan, laba rugi per properti |
| Bukan tujuan | Marketplace pencarian kos, menahan atau menyalurkan dana pemilik, akuntansi penuh, aplikasi mobile native |

### Paket langganan (usulan)

| Paket | Harga/bulan (Rp) | Batas | Fitur kunci |
| --- | --- | --- | --- |
| Gratis | 0 | 1 properti, 10 kamar | Data kamar, tagihan manual |
| Starter | 49-79 rb | 1 properti, 30 kamar | Tagihan otomatis, pengingat WhatsApp, laporan dasar |
| Pro | 129-199 rb | 3 properti, 100 kamar | Multi-user, laporan lanjutan, kuota pesan lebih besar |
| Business | 299-499 rb | Properti tanpa batas | Prioritas support, ekspor lanjutan |

### Asumsi dan risiko

- Pembayaran dicatat manual di MVP; Kostra tidak menahan dana pemilik.
- Biaya pesan WhatsApp resmi dihitung per pesan, sehingga kuota pesan dipisah dari harga langganan.
- Data KTP dan kontak penghuni tunduk pada UU Pelindungan Data Pribadi.
- Pesaing sudah ada (misalnya Mamikos, Kostify, Kamaru), jadi pembeda harus jelas: onboarding sederhana, dukungan lokal, tanpa komisi.

## 2. PRD modul Fondasi: autentikasi, tenant, peran

Modul ini memastikan setiap pemilik kos hanya melihat data miliknya dan setiap peran hanya mengakses menu yang sesuai. Semua modul lain bergantung padanya, jadi dibangun pertama.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| FND-01 | Pemilik mendaftar dengan email atau nomor WhatsApp dan membuat tenant otomatis | MVP | Setelah daftar, pemilik masuk ke dashboard kosong dengan paket Gratis |
| FND-02 | Login, logout, reset kata sandi | MVP | Reset lewat tautan yang kedaluwarsa dalam 60 menit |
| FND-03 | Isolasi data per tenant lewat kolom tenant_id dan global scope | MVP | Uji otomatis: pengguna tenant A tidak bisa membaca atau mengubah data tenant B lewat UI maupun URL |
| FND-04 | Peran Pemilik, Staf, Super Admin dengan hak akses per fitur | MVP | Staf tidak melihat menu Keuangan, Pengaturan, dan Langganan |
| FND-05 | Pemilik mengundang staf lewat email atau WhatsApp | MVP | Undangan sekali pakai, kedaluwarsa 7 hari |
| FND-06 | Log audit untuk aksi penting (ubah harga, hapus penghuni, ubah pembayaran) | MVP | Tiap entri mencatat pengguna, waktu, data sebelum dan sesudah |
| FND-07 | Verifikasi dua langkah untuk pemilik | Tahap berikutnya | Opsional lewat kode sekali pakai |
| FND-08 | Impersonasi tenant oleh Super Admin untuk support | Tahap berikutnya | Tercatat di log audit dan terlihat oleh pemilik |

### Aturan bisnis

- Satu pengguna terhubung ke satu tenant pada MVP.
- Batas paket (jumlah properti dan kamar) diperiksa di sisi server saat membuat data baru.
- Menghapus data penting memakai penghapusan lunak agar bisa dipulihkan.

### Data utama

`tenants`, `users`, `roles`, `permissions`, `invitations`, `audit_logs`.

### Di luar lingkup MVP

Masuk dengan akun Google, satu pengguna di banyak tenant, dan SSO.

## 3. PRD modul Properti dan Kamar

Modul ini menyimpan daftar properti, tipe kamar, dan status tiap kamar, karena semua kontrak dan tagihan menempel pada kamar. Pemilik harus bisa memasukkan seluruh kamarnya dalam beberapa menit.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| PRP-01 | CRUD properti (nama, alamat, foto, jumlah lantai) | MVP | Properti baru muncul di daftar dan filter semua halaman |
| PRP-02 | CRUD tipe kamar (nama, harga dasar, fasilitas, ukuran) | MVP | Mengubah harga tipe tidak mengubah kontrak yang sudah berjalan |
| PRP-03 | CRUD kamar (nomor, tipe, lantai, harga, status) | MVP | Status: kosong, terisi, perawatan; nomor kamar unik per properti |
| PRP-04 | Tambah kamar massal (misalnya kamar 101-110 sekaligus) | MVP | 20 kamar dapat dibuat dalam satu langkah |
| PRP-05 | Impor kamar dari Excel/CSV | MVP | Baris yang gagal ditampilkan beserta alasannya; baris valid tetap masuk |
| PRP-06 | Daftar kamar dengan filter status, properti, tipe | MVP | Daftar 100 kamar tampil kurang dari 2 detik |
| PRP-07 | Batas jumlah properti dan kamar sesuai paket | MVP | Penambahan di atas batas ditolak dengan tawaran naik paket |
| PRP-08 | Kalender okupansi per kamar | Tahap berikutnya | Tampilan garis waktu terisi dan kosong per bulan |

### Aturan bisnis

- Status kamar "terisi" ditentukan oleh kontrak aktif, bukan diatur manual.
- Kamar dengan kontrak aktif tidak dapat dihapus, hanya dinonaktifkan setelah check-out.
- Harga kamar dapat berbeda dari harga tipe; kontrak menyimpan harga yang disepakati.

### Data utama

`properties`, `room_types`, `rooms`, `facilities`.

### Di luar lingkup MVP

Halaman publik kamar kosong, harga dinamis, dan sinkronisasi dengan marketplace kos.

## 4. PRD modul Penghuni

Modul ini menyimpan data orang yang menyewa kamar beserta dokumennya, dengan perlindungan khusus karena memuat data pribadi. Nomor WhatsApp penghuni wajib karena menjadi saluran tagihan dan portal.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| PNG-01 | CRUD penghuni (nama, nomor WhatsApp, email, pekerjaan/kampus, kontak darurat) | MVP | Nomor WhatsApp divalidasi dan disimpan dalam format internasional |
| PNG-02 | Unggah dokumen (foto KTP, foto penghuni, dokumen lain) | MVP | File tersimpan terenkripsi, hanya terbuka bagi pemilik dan staf berizin |
| PNG-03 | Satu penghuni dapat memiliki beberapa kontrak dari waktu ke waktu | MVP | Riwayat kontrak tampil di profil penghuni |
| PNG-04 | Riwayat penghuni yang sudah check-out | MVP | Penghuni lama tetap dapat dicari dengan status tidak aktif |
| PNG-05 | Cari dan filter (nama, nomor kamar, status, tunggakan) | MVP | Hasil muncul kurang dari 1 detik untuk 500 penghuni |
| PNG-06 | Impor penghuni dari Excel/CSV | MVP | Mendeteksi nomor WhatsApp ganda dan menandainya |
| PNG-07 | Catatan internal pemilik per penghuni | Tahap berikutnya | Tidak terlihat oleh penghuni |
| PNG-08 | Calon penghuni dan booking kamar | Tahap berikutnya | Calon dapat diubah menjadi penghuni dan kontrak |

### Aturan bisnis

- Foto KTP bersifat opsional per kebijakan pemilik; sistem menampilkan peringatan pelindungan data saat mengunggahnya.
- Penghuni dapat meminta datanya dihapus; data keuangan yang diwajibkan untuk pencatatan tetap disimpan dalam bentuk anonim.
- Dua penghuni pada satu kamar (pasangan, kamar berbagi) didukung lewat satu kontrak dengan penanggung jawab tagihan.

### Data utama

`residents`, `resident_documents`, `emergency_contacts`.

### Di luar lingkup MVP

Verifikasi KTP otomatis, pemeriksaan latar belakang, dan penilaian reputasi penghuni.

## 5. PRD modul Kontrak Sewa

Kontrak menghubungkan penghuni, kamar, harga, dan masa sewa; dari kontrak inilah tagihan bulanan lahir. Check-in dan check-out harus mencatat deposit dan kondisi kamar.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| KTR-01 | Buat kontrak (penghuni, kamar, tanggal mulai, durasi, harga sewa, siklus tagihan, deposit) | MVP | Kontrak aktif mengubah status kamar menjadi terisi |
| KTR-02 | Cegah dua kontrak aktif yang tumpang tindih pada kamar yang sama | MVP | Percobaan membuat kontrak bentrok ditolak dengan pesan jelas |
| KTR-03 | Check-in: catat tanggal masuk, deposit, dan catatan kondisi kamar | MVP | Deposit tercatat sebagai kewajiban yang dapat dikembalikan |
| KTR-04 | Check-out: hitung sisa tagihan, potongan, dan pengembalian deposit | MVP | Check-out menutup kontrak dan mengembalikan kamar ke status kosong |
| KTR-05 | Perpanjangan kontrak dengan satu klik | MVP | Kontrak baru mewarisi data lama dengan tanggal mulai menyambung |
| KTR-06 | Pengingat kontrak segera habis (30, 14, 7 hari) | MVP | Pemilik menerima notifikasi; penghuni menerima pesan WhatsApp bila diaktifkan |
| KTR-07 | Pindah kamar di tengah kontrak | MVP | Riwayat kamar lama tersimpan; tagihan berikutnya memakai harga kamar baru |
| KTR-08 | Kenaikan harga sewa terjadwal saat perpanjangan | Tahap berikutnya | Pemilik menetapkan persentase atau nominal baru |
| KTR-09 | Template kontrak PDF dengan tanda tangan digital | Tahap berikutnya | PDF memuat data kontrak dan syarat dari pemilik |

### Aturan bisnis

- Siklus tagihan: bulanan pada MVP; harian, mingguan, dan tahunan pada tahap berikutnya.
- Tanggal jatuh tempo mengikuti aturan tagihan di Pengaturan, dapat diubah per kontrak.
- Penghuni dengan kontrak berakhir tetapi belum check-out ditandai "lewat masa sewa".

### Data utama

`leases`, `lease_deposits`, `room_transfers`, `checkin_records`.

### Di luar lingkup MVP

Kontrak untuk banyak penghuni dengan pembagian biaya, sewa harian, dan e-meterai.

## 6. PRD modul Tagihan

Tagihan bulanan harus terbit sendiri dari kontrak aktif, tanpa pemilik membuatnya satu per satu. Ini fitur inti Kostra dan harus sangat andal: tagihan ganda atau tagihan yang terlewat merusak kepercayaan pemilik.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| TGH-01 | Penjadwal menerbitkan tagihan bulanan per kontrak aktif pada tanggal yang diatur | MVP | Menjalankan penjadwal dua kali pada hari yang sama tidak menghasilkan tagihan ganda |
| TGH-02 | Tagihan memiliki nomor unik, periode, rincian item, jumlah, tanggal terbit, dan jatuh tempo | MVP | Nomor mengikuti format per tenant, misalnya INV-2026-10-0001 |
| TGH-03 | Item tagihan: sewa pokok dan item tambahan (listrik, air, kebersihan, lainnya) | MVP | Total tagihan = jumlah seluruh item |
| TGH-04 | Tagihan manual atau insidental untuk satu penghuni | MVP | Dapat ditambahkan di luar siklus bulanan |
| TGH-05 | Status tagihan: draf, terbit, sebagian dibayar, lunas, lewat jatuh tempo, batal | MVP | Status berubah otomatis sesuai pembayaran dan tanggal |
| TGH-06 | Daftar tagihan dengan filter status, properti, periode | MVP | Tunggakan terlihat dalam satu layar |
| TGH-07 | Pembatalan atau koreksi tagihan dengan alasan | MVP | Tagihan lunas tidak dapat dihapus, hanya dikoreksi lewat penyesuaian tercatat |
| TGH-08 | Denda keterlambatan otomatis | Tahap berikutnya | Pemilik memilih tarif tetap atau persentase per hari |
| TGH-09 | Tagihan berdasarkan meteran listrik dan air | Tahap berikutnya | Pencatatan meteran bulanan menghasilkan item pemakaian |
| TGH-10 | Unduh tagihan sebagai PDF | Tahap berikutnya | PDF memuat logo dan rekening pemilik |

### Aturan bisnis

- Penerbitan memakai tabel kunci unik (kontrak, periode) agar tidak ganda.
- Pro rata untuk bulan pertama dan terakhir dapat diaktifkan di Pengaturan.
- Tagihan yang terbit tidak berubah jumlah; koreksi dicatat sebagai item penyesuaian.
- Setiap tagihan memiliki tautan aman untuk portal penghuni.

### Data utama

`invoices`, `invoice_items`, `invoice_adjustments`, `billing_settings`.

### Di luar lingkup MVP

Pajak, faktur pajak, dan penagihan antar-mata uang.

## 7. PRD modul Pembayaran

Pada MVP, Kostra mencatat pembayaran yang terjadi di luar sistem (tunai atau transfer ke rekening pemilik) dan tidak menahan dana. Pembayaran online lewat QRIS atau virtual account baru masuk tahap berikutnya, langsung ke rekening pemilik.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| PBY-01 | Catat pembayaran (tagihan, jumlah, tanggal, metode, catatan) oleh pemilik atau staf | MVP | Pembayaran mengubah status tagihan sesuai jumlah |
| PBY-02 | Pembayaran sebagian dan pembayaran untuk beberapa tagihan sekaligus | MVP | Sisa tagihan dihitung otomatis dan tampil di daftar |
| PBY-03 | Penghuni mengunggah bukti transfer dari portal | MVP | Bukti masuk antrean verifikasi dengan status menunggu |
| PBY-04 | Pemilik menyetujui atau menolak bukti transfer dengan alasan | MVP | Persetujuan membuat catatan pembayaran dan menutup tagihan; penolakan memberi tahu penghuni |
| PBY-05 | Kuitansi digital otomatis dengan nomor unik | MVP | Kuitansi terkirim ke penghuni lewat WhatsApp dan dapat dibuka di portal |
| PBY-06 | Rekening pembayaran pemilik tampil di tagihan dan portal | MVP | Pemilik dapat memiliki lebih dari satu rekening atau QRIS statis |
| PBY-07 | Koreksi atau pembatalan pembayaran dengan alasan | MVP | Aksi tercatat di log audit dan mengembalikan status tagihan |
| PBY-08 | Pembayaran online lewat QRIS dan virtual account per merchant | Tahap berikutnya | Notifikasi pembayaran otomatis menutup tagihan tanpa input manual |
| PBY-09 | Rekonsiliasi mutasi rekening | Tahap berikutnya | Impor mutasi dicocokkan dengan tagihan terbuka |

### Aturan bisnis

- Kostra tidak menerima, menahan, atau menyalurkan uang pemilik atau penghuni.
- Pembayaran yang sudah dicatat tidak dihapus; hanya dibatalkan dengan catatan.
- Bukti transfer yang tidak ditindak dalam 3 hari memunculkan pengingat ke pemilik.

### Data utama

`payments`, `payment_proofs`, `receipts`, `payment_accounts`.

### Di luar lingkup MVP

Pengembalian dana otomatis, dompet digital, dan pembayaran dengan kartu.

## 8. PRD modul Keuangan dan Laporan

Modul ini menjawab tiga pertanyaan pemilik: berapa pemasukan bulan ini, siapa yang menunggak, dan berapa kamar yang terisi. Dashboard dan laporan memakai data dari tagihan dan pembayaran, bukan input terpisah.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| KEU-01 | Dashboard: okupansi, pemasukan bulan berjalan, total tunggakan, kontrak segera habis | MVP | Angka dashboard sama dengan jumlah pada laporan periode yang sama |
| KEU-02 | Pencatatan pengeluaran operasional (kategori, jumlah, tanggal, lampiran nota) | MVP | Pengeluaran terfilter per properti dan kategori |
| KEU-03 | Laporan pemasukan per periode dan per properti | MVP | Total pemasukan = jumlah pembayaran yang diterima pada periode itu |
| KEU-04 | Laporan tunggakan dengan umur tunggakan (1-7, 8-30, lebih dari 30 hari) | MVP | Daftar dapat diekspor dan diurutkan berdasarkan jumlah |
| KEU-05 | Laporan okupansi (kamar terisi, kosong, rata-rata hari kosong) | MVP | Persentase okupansi dihitung per tanggal yang dipilih |
| KEU-06 | Ekspor laporan ke Excel dan PDF | MVP | Berkas memuat nama properti, periode, dan tanggal cetak |
| KEU-07 | Rekap pemasukan dan pengeluaran bulanan | MVP | Selisih pemasukan dan pengeluaran tampil sebagai saldo bersih bulan itu |
| KEU-08 | Laba rugi per properti | Tahap berikutnya | Pengeluaran dialokasikan ke properti |
| KEU-09 | Laporan bulanan otomatis ke pemilik lewat WhatsApp atau email | Tahap berikutnya | Terkirim pada tanggal yang diatur pemilik |

### Aturan bisnis

- Pemasukan diakui pada tanggal pembayaran diterima, bukan tanggal tagihan terbit.
- Staf tidak dapat melihat laporan keuangan kecuali pemilik memberi izin.
- Laporan memakai zona waktu tenant (default Asia/Makassar).

### Data utama

`expenses`, `expense_categories`, tampilan agregat dari `invoices` dan `payments`.

### Di luar lingkup MVP

Akuntansi berpasangan (jurnal), pajak penghasilan, dan integrasi dengan software akuntansi.

## 9. PRD modul Komplain dan Perawatan

Penghuni mengajukan keluhan lewat portal, dan pemilik melacaknya sampai selesai supaya tidak tenggelam di chat. Pada MVP cukup tiket sederhana; jadwal perawatan dan vendor menyusul.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| KMP-01 | Penghuni membuat tiket (kategori, deskripsi, foto) dari portal | MVP | Pemilik menerima notifikasi tiket baru |
| KMP-02 | Pemilik dan staf mengubah status tiket: baru, diproses, selesai, ditolak | MVP | Setiap perubahan status memberi tahu penghuni |
| KMP-03 | Komentar dua arah pada tiket | MVP | Riwayat percakapan tersimpan dalam urutan waktu |
| KMP-04 | Penetapan petugas atau staf penanggung jawab | MVP | Tiket terfilter per penanggung jawab |
| KMP-05 | Daftar tiket dengan filter status, kamar, kategori | MVP | Tiket terbuka lebih dari 3 hari ditandai |
| KMP-06 | Biaya perbaikan dicatat sebagai pengeluaran | Tahap berikutnya | Tiket menaut ke catatan pengeluaran |
| KMP-07 | Jadwal perawatan berkala (AC, pompa air, kebersihan) | Tahap berikutnya | Pengingat otomatis sesuai interval |
| KMP-08 | Daftar vendor dan kontak teknisi | Tahap berikutnya | Vendor dapat ditautkan ke tiket |

### Aturan bisnis

- Kategori awal: listrik, air, fasilitas kamar, kebersihan, keamanan, lainnya.
- Penghuni hanya melihat tiket miliknya.
- Tiket tidak dihapus, hanya ditutup.

### Data utama

`complaints`, `complaint_comments`, `complaint_attachments`.

### Di luar lingkup MVP

Penugasan otomatis ke teknisi eksternal, SLA, dan penilaian layanan.

## 10. PRD modul Notifikasi WhatsApp

Pengingat tagihan lewat WhatsApp adalah alasan utama pemilik membayar, tetapi juga sumber biaya variabel terbesar bagi Kostra. Karena Meta menagih per pesan template yang terkirim, modul ini harus membatasi pemakaian lewat kuota dan menyimpan log setiap pesan.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| NTF-01 | Lapisan penyedia pesan (interface) sehingga penyedia WhatsApp dapat diganti tanpa mengubah modul lain | MVP | Penyedia baru cukup menambah satu kelas adapter dan konfigurasi |
| NTF-02 | Template pesan: tagihan terbit, pengingat sebelum jatuh tempo, hari jatuh tempo, tunggakan, kuitansi, kontrak segera habis | MVP | Pemilik dapat mengedit isi dalam batas variabel yang diizinkan |
| NTF-03 | Jadwal pengingat per tenant (misalnya H-3, H-0, H+3) | MVP | Pengingat hanya terkirim untuk tagihan yang belum lunas |
| NTF-04 | Kuota pesan per paket dengan penghitung sisa | MVP | Pengiriman berhenti saat kuota habis dan pemilik diberi tahu |
| NTF-05 | Log pengiriman (penerima, template, status, waktu, kegagalan) | MVP | Pesan gagal dapat dikirim ulang dari log |
| NTF-06 | Semua pengiriman lewat antrean dengan percobaan ulang terbatas | MVP | Halaman web tidak menunggu pengiriman selesai |
| NTF-07 | Persetujuan penerima dan opsi berhenti menerima pesan | MVP | Penghuni yang berhenti tidak menerima pesan otomatis lagi |
| NTF-08 | Pembatasan jam kirim (tidak mengirim pada malam hari) | MVP | Pesan di luar jam yang diatur ditunda ke jam kirim berikutnya |
| NTF-09 | Pembelian kuota tambahan prabayar | Tahap berikutnya | Kuota bertambah setelah pembayaran terkonfirmasi |
| NTF-10 | Pengumuman massal ke semua penghuni | Tahap berikutnya | Dihitung ke kuota dan memakai template yang disetujui |

### Aturan bisnis

- Pesan penagihan ditulis sebagai template utility (pengingat, kuitansi); nada promosi akan dikategorikan lebih mahal oleh Meta.
- Tarif per pesan untuk Indonesia belum diverifikasi; harga kuota ditentukan setelah memeriksa tabel tarif resmi Meta.
- Gateway WhatsApp tidak resmi tidak dipakai untuk produksi karena risiko nomor diblokir.
- Email dan notifikasi dalam aplikasi menjadi cadangan saat WhatsApp gagal.

### Data utama

`message_templates`, `message_logs`, `message_quotas`, `notification_preferences`.

### Di luar lingkup MVP

Chatbot dua arah, pesan promosi, dan kanal SMS.

## 11. PRD modul Portal Penghuni

Penghuni membuka tagihannya lewat tautan di WhatsApp tanpa membuat akun atau memasang aplikasi, sehingga hambatan adopsi tetap rendah. Halaman harus ringan dan nyaman di ponsel.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| PRT-01 | Akses lewat tautan berisi token unik per penghuni | MVP | Token tidak dapat ditebak dan dapat dicabut oleh pemilik |
| PRT-02 | Beranda: tagihan aktif, jumlah, jatuh tempo, status kontrak | MVP | Terbuka kurang dari 3 detik pada jaringan seluler biasa |
| PRT-03 | Riwayat tagihan dan pembayaran | MVP | Penghuni hanya melihat data miliknya |
| PRT-04 | Tampilkan rekening atau QRIS pemilik dan tombol salin nomor rekening | MVP | Informasi sama dengan Pengaturan Pembayaran pemilik |
| PRT-05 | Unggah bukti transfer | MVP | Menerima JPG, PNG, atau PDF hingga 5 MB |
| PRT-06 | Lihat dan unduh kuitansi | MVP | Kuitansi muncul setelah pembayaran disetujui |
| PRT-07 | Lihat kontrak dan tanggal berakhir | MVP | Menampilkan ringkasan kontrak, bukan data internal pemilik |
| PRT-08 | Ajukan komplain dan pantau statusnya | MVP | Terhubung dengan modul Komplain |
| PRT-09 | Verifikasi tambahan saat tautan dibuka dari perangkat baru (kode lewat WhatsApp) | Tahap berikutnya | Kode berlaku 10 menit |
| PRT-10 | Pengumuman dari pemilik | Tahap berikutnya | Ditandai sudah dibaca |

### Aturan bisnis

- Tautan dikirim hanya ke nomor WhatsApp penghuni yang terdaftar.
- Tautan memuat sesi terbatas: tidak ada data penghuni lain dan tidak ada aksi di luar fitur portal.
- Bahasa tampilan default Indonesia.

### Data utama

`portal_tokens`, `portal_sessions`, tabel dari modul tagihan, pembayaran, dan komplain.

### Di luar lingkup MVP

Aplikasi mobile native, notifikasi push, dan login dengan kata sandi untuk penghuni.

## 12. PRD modul Langganan Kostra dan Super Admin

Modul ini mengatur bagaimana Kostra sendiri mendapat uang: paket, batas pemakaian, penagihan langganan ke pemilik, dan panel internal untuk mengelola tenant. Pada MVP penagihan langganan boleh semi-manual agar peluncuran tidak tertunda.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan |
| --- | --- | --- | --- |
| LGN-01 | Definisi paket (harga, batas properti, batas kamar, kuota pesan, fitur aktif) yang dapat diubah Super Admin | MVP | Perubahan paket tidak mengubah tenant yang sudah berlangganan kecuali diatur |
| LGN-02 | Pembatasan fitur dan batas berdasarkan paket tenant | MVP | Melewati batas menampilkan tawaran naik paket, bukan galat |
| LGN-03 | Masa percobaan 14 hari untuk paket berbayar | MVP | Tenant kembali ke paket Gratis bila tidak berlangganan setelah masa percobaan |
| LGN-04 | Tagihan langganan bulanan atau tahunan ke pemilik | MVP | Pemilik membayar lewat transfer atau pembayaran online; status paket berubah setelah konfirmasi |
| LGN-05 | Naik dan turun paket | MVP | Turun paket diperiksa terhadap jumlah kamar dan properti yang ada |
| LGN-06 | Panel Super Admin: daftar tenant, status langganan, pemakaian kamar dan pesan | MVP | Pencarian tenant dalam satu langkah |
| LGN-07 | Tangguhkan dan aktifkan kembali tenant | MVP | Tenant yang ditangguhkan hanya dapat membaca dan mengekspor data |
| LGN-08 | Diskon tahunan dan kode promo | Tahap berikutnya | Diskon tampil di tagihan langganan |
| LGN-09 | Add-on berbayar (kuota pesan, domain kustom, pengguna tambahan) | Tahap berikutnya | Add-on ditagih bersama langganan |
| LGN-10 | Penagihan langganan otomatis berulang lewat payment gateway | Tahap berikutnya | Kegagalan pembayaran memicu pengingat dan masa tenggang |

### Aturan bisnis

- Pemilik selalu dapat mengekspor datanya, termasuk saat langganan berakhir.
- Masa tenggang 7 hari sebelum tenant ditangguhkan karena tunggakan langganan.
- Data tenant yang ditangguhkan tidak dihapus sebelum jangka waktu yang diumumkan.

### Data utama

`plans`, `subscriptions`, `subscription_invoices`, `usage_counters`.

### Di luar lingkup MVP

Program afiliasi, reseller, dan harga per kamar yang dinamis.

## 13. Kebutuhan non-fungsional

Kostra menyimpan data pribadi penghuni dan catatan uang pemilik, jadi keamanan dan keandalan tagihan lebih penting daripada kecepatan menambah fitur. Angka di bawah adalah target usulan untuk MVP.

| ID | Area | Kebutuhan | Target usulan |
| --- | --- | --- | --- |
| NFR-01 | Keamanan | Seluruh lalu lintas lewat HTTPS; kata sandi di-hash; sesi dengan kedaluwarsa | Tanpa pengecualian |
| NFR-02 | Isolasi data | Pengguna satu tenant tidak dapat mengakses data tenant lain | Dicakup uji otomatis di setiap rilis |
| NFR-03 | Data pribadi | Dokumen KTP terenkripsi saat disimpan; akses dicatat; persetujuan penghuni tercatat | Sesuai UU Pelindungan Data Pribadi |
| NFR-04 | Keandalan tagihan | Penerbitan tagihan idempoten dan dapat dijalankan ulang | Nol tagihan ganda pada uji penjadwal |
| NFR-05 | Performa | Halaman daftar dan dashboard dimuat dengan cepat | Kurang dari 2 detik untuk tenant 100 kamar |
| NFR-06 | Ketersediaan | Layanan tersedia selama jam penggunaan wajar | 99% per bulan pada MVP |
| NFR-07 | Pencadangan | Cadangan basis data harian dengan uji pemulihan berkala | Retensi 14 hari |
| NFR-08 | Audit | Aksi keuangan dan penghapusan tercatat | Log tidak dapat diubah pengguna |
| NFR-09 | Antrean dan penjadwal | Pekerjaan gagal dicoba ulang dan terlihat di pemantauan | Peringatan ke pengembang saat antrean macet |
| NFR-10 | Tampilan | Portal penghuni dan panel dapat dipakai di layar ponsel | Lebar layar 360 px ke atas |
| NFR-11 | Pemantauan | Pencatatan galat dan pemantauan kesehatan layanan | Galat produksi terlihat dalam 5 menit |
| NFR-12 | Ekspor data | Pemilik dapat mengekspor data miliknya | Format Excel/CSV |

### Catatan kepatuhan

- Persetujuan pengumpulan data penghuni diambil pemilik; Kostra berperan sebagai pemroses data dan perlu kebijakan privasi serta ketentuan layanan sebelum peluncuran.
- Untuk pembayaran online di tahap berikutnya, gunakan virtual account atau QRIS per merchant lewat penyedia berizin dan jangan menahan dana.
- Konsultasikan teks kebijakan privasi dan ketentuan layanan ke penasihat hukum; dokumen ini bukan nasihat hukum.

## 14. Rencana rilis dan pertanyaan terbuka

MVP dibangun dalam 4 minggu kerja, lalu 8 minggu pilot dengan 3-5 pemilik kos sebelum diputuskan lanjut atau tidak. Estimasi ini usulan awal dan bergantung pada waktu yang tersedia per minggu.

```text
Minggu 1-4   MVP: fondasi, properti dan kamar, penghuni dan kontrak, tagihan dan pembayaran,
             notifikasi WhatsApp, portal penghuni, laporan, langganan
Minggu 5-12  Pilot dengan 3-5 pemilik kos
Keputusan    Lanjut atau ubah arah (kriteria di bawah)
```

Minggu 1 membangun fondasi, properti, dan kamar; minggu 2 penghuni dan kontrak; minggu 3 tagihan dan pembayaran; minggu 4 notifikasi WhatsApp, portal penghuni (termasuk komplain), laporan, dan langganan.

### Ketergantungan antar modul

- Fondasi harus selesai lebih dulu karena semua modul memakai tenant dan peran.
- Properti dan Kamar mendahului Penghuni dan Kontrak; Kontrak mendahului Tagihan.
- Pembayaran bergantung pada Tagihan; Laporan bergantung pada Tagihan, Pembayaran, dan pengeluaran.
- Notifikasi dan Portal bergantung pada Tagihan dan Pembayaran.

### Kriteria keputusan akhir pilot (usulan)

Lanjut mengembangkan tahap berikutnya bila setidaknya 4 dari 5 pemilik pilot masih aktif setelah 8 minggu dan 3 dari 5 memilih paket berbayar; bila tidak, ubah arah berdasarkan alasan yang mereka sampaikan.

### Pertanyaan terbuka

- [ ] Penyedia WhatsApp resmi mana yang dipakai, dan berapa tarif template utility untuk Indonesia?
- [ ] Antarmuka panel memakai Filament atau starter kit Laravel 12 dengan Livewire?
- [ ] Apakah foto KTP penghuni wajib atau opsional pada pilot?
- [ ] Berapa harga final tiap paket setelah wawancara dengan 10 pemilik kos?
- [ ] Apakah nama dan merek Kostra sudah lolos pemeriksaan domain dan DJKI?
