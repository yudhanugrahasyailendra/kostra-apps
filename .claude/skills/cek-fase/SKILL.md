---
description: Periksa apakah fase yang baru dikerjakan memenuhi kriteria selesai
argument-hint: [nomor fase]
disable-model-invocation: true
allowed-tools: Bash(php artisan test *) Bash(./vendor/bin/pint *) Bash(git diff *) Bash(git status *)
---

Periksa Fase $ARGUMENTS terhadap bagian "Selesai bila" di docs/plan/implementation-plan.md.

1. Jalankan `php artisan test` dan `./vendor/bin/pint --test`.
2. Pastikan setiap model tabel milik pemilik memakai BelongsToTenant.
3. Bandingkan migrasi dengan docs/plan/database.md: nama kolom, tipe, indeks unik, foreign key.
4. Cari float atau decimal untuk uang, dan route tanpa middleware atau Policy.
5. Tampilkan tabel lulus atau gagal per kriteria. Jangan memperbaiki apa pun kecuali saya minta.
