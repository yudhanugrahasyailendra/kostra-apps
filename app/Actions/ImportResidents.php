<?php

namespace App\Actions;

use App\Models\Resident;
use App\Support\CsvRows;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportResidents
{
    public const MAX_ROWS = 500;

    /** Nama kolom yang dikenali (huruf kecil) dan padanannya. */
    private const HEADERS = [
        'nama' => 'name', 'name' => 'name',
        'whatsapp' => 'phone', 'nomor whatsapp' => 'phone', 'no whatsapp' => 'phone', 'no. whatsapp' => 'phone',
        'telepon' => 'phone', 'hp' => 'phone', 'no hp' => 'phone', 'phone' => 'phone',
        'email' => 'email',
        'pekerjaan' => 'occupation', 'pekerjaan/kampus' => 'occupation', 'kampus' => 'occupation', 'occupation' => 'occupation',
        'kontak darurat' => 'emergency_name', 'nama kontak darurat' => 'emergency_name',
        'hubungan' => 'emergency_relation', 'hubungan kontak darurat' => 'emergency_relation',
        'telepon darurat' => 'emergency_phone', 'whatsapp darurat' => 'emergency_phone',
        'catatan' => 'notes', 'notes' => 'notes',
    ];

    /**
     * Impor penghuni dari CSV (PNG-06). Baris valid tetap masuk; baris gagal dilaporkan beserta
     * alasannya. Nomor WhatsApp yang sudah ada (di database atau baris sebelumnya) ditandai
     * sebagai ganda tetapi tetap dimasukkan.
     *
     * @return array{created: int, failures: list<array{row: int, name: string, reason: string}>, duplicates: list<array{row: int, name: string}>}
     */
    public function handle(string $path): array
    {
        $rows = CsvRows::read($path, self::HEADERS, ['name' => 'nama', 'phone' => 'whatsapp']);

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
        }

        return DB::transaction(function () use ($rows) {
            $seen = Resident::query()->pluck('phone')->flip();
            $created = 0;
            $failures = [];
            $duplicates = [];

            foreach ($rows as $line => $row) {
                $name = trim($row['name'] ?? '');
                $fail = function (string $reason) use (&$failures, $line, $name): void {
                    $failures[] = ['row' => $line, 'name' => $name, 'reason' => $reason];
                };

                if ($name === '' || mb_strlen($name) > 120) {
                    $fail('Nama kosong atau lebih dari 120 karakter.');

                    continue;
                }

                $phone = PhoneNumber::normalize($row['phone'] ?? null);

                if ($phone === null) {
                    $fail('Nomor WhatsApp tidak valid.');

                    continue;
                }

                $email = trim($row['email'] ?? '');

                if ($email !== '' && (mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                    $fail('Email tidak valid.');

                    continue;
                }

                $emergencyPhoneRaw = trim($row['emergency_phone'] ?? '');
                $emergencyPhone = PhoneNumber::normalize($emergencyPhoneRaw);

                if ($emergencyPhoneRaw !== '' && $emergencyPhone === null) {
                    $fail('Nomor telepon darurat tidak valid.');

                    continue;
                }

                $occupation = trim($row['occupation'] ?? '');
                $emergencyName = trim($row['emergency_name'] ?? '');
                $emergencyRelation = trim($row['emergency_relation'] ?? '');

                if (mb_strlen($occupation) > 120 || mb_strlen($emergencyName) > 255 || mb_strlen($emergencyRelation) > 255) {
                    $fail('Pekerjaan, kontak darurat, atau hubungan terlalu panjang.');

                    continue;
                }

                if ($seen->has($phone)) {
                    $duplicates[] = ['row' => $line, 'name' => $name];
                }

                Resident::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email === '' ? null : $email,
                    'occupation' => $occupation === '' ? null : $occupation,
                    'emergency_name' => $emergencyName === '' ? null : $emergencyName,
                    'emergency_relation' => $emergencyRelation === '' ? null : $emergencyRelation,
                    'emergency_phone' => $emergencyPhone,
                    'notes' => trim($row['notes'] ?? '') ?: null,
                    'is_active' => true,
                ]);

                $seen->put($phone, true);
                $created++;
            }

            return ['created' => $created, 'failures' => $failures, 'duplicates' => $duplicates];
        });
    }
}
