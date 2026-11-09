---
description: Kerjakan satu fase implementation plan Kostra dari docs/plan/implementation-plan.md
argument-hint: [nomor fase, misalnya 5]
disable-model-invocation: true
allowed-tools: Bash(git status *) Bash(git log *)
---

## Keadaan repo
!`git status --short`
!`git log --oneline -5`

## Tugas
Kerjakan Fase $ARGUMENTS.

1. Baca bagian "Fase $ARGUMENTS" di docs/plan/implementation-plan.md, modul terkait di docs/plan/PRD.md, dan tabel terkait di docs/plan/database.md.
2. Tulis rencana: urutan langkah, daftar file, dan tes yang akan dibuat. Tunggu persetujuan saya.
3. Tulis tes lebih dulu untuk aturan uang dan isolasi tenant, lalu implementasi.
4. Jalankan `php artisan test` dan `./vendor/bin/pint`.
5. Ringkas: file berubah, hasil tes, dan setiap penyimpangan dari docs.
6. Jangan commit sebelum saya setuju.
