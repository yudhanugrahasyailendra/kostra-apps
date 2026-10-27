<?php

namespace App\Actions\Residents;

use App\Models\Resident;
use App\Support\CsvReader;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class ImportResidents
{
    public const MAX_ROWS = 1000;

    private const HEADERS = [
        'nama' => 'name', 'name' => 'name',
        'telepon' => 'phone', 'phone' => 'phone', 'hp' => 'phone', 'no_hp' => 'phone', 'whatsapp' => 'phone', 'nomor_whatsapp' => 'phone',
        'email' => 'email',
        'pekerjaan' => 'occupation', 'occupation' => 'occupation', 'kampus' => 'occupation',
        'kontak_darurat_nama' => 'emergency_name', 'emergency_name' => 'emergency_name',
        'kontak_darurat_hubungan' => 'emergency_relation', 'emergency_relation' => 'emergency_relation',
        'kontak_darurat_telepon' => 'emergency_phone', 'emergency_phone' => 'emergency_phone',
        'catatan' => 'notes', 'notes' => 'notes',
    ];

    /**
     * Impor penghuni dari CSV. Baris valid tetap masuk; nomor ganda tetap masuk tetapi ditandai;
     * baris tidak valid dilaporkan beserta alasannya. Pemanggil menjamin persetujuan sudah ditegaskan.
     */
    public function __invoke(string $path): ImportResult
    {
        $rows = CsvReader::read($path, self::HEADERS, 'name', self::MAX_ROWS);
        $result = new ImportResult;

        DB::transaction(function () use ($rows, $result) {
            $known = Resident::query()->pluck('phone')->flip()->all();

            foreach ($rows as ['line' => $line, 'data' => $row]) {
                $name = trim($row['name'] ?? '');
                $normalized = $this->normalize($row);

                if (is_string($normalized)) {
                    $result->failures[] = ['line' => $line, 'name' => $name, 'reason' => $normalized];

                    continue;
                }

                if (isset($known[$normalized['phone']])) {
                    $result->duplicates[] = ['line' => $line, 'name' => $name, 'phone' => $normalized['phone']];
                }

                Resident::create([...$normalized, 'consented_at' => now(), 'is_active' => true]);

                $known[$normalized['phone']] = true;
                $result->created++;
            }
        });

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, ?string>|string Data bersih, atau alasan penolakan
     */
    private function normalize(array $row): array|string
    {
        $get = fn (string $key) => ($value = trim($row[$key] ?? '')) === '' ? null : $value;

        $name = $get('name');

        if ($name === null) {
            return 'Nama kosong.';
        }

        if (mb_strlen($name) > 120) {
            return 'Nama lebih dari 120 karakter.';
        }

        $phone = PhoneNumber::normalize($get('phone'));

        if ($phone === null) {
            return 'Nomor WhatsApp kosong atau tidak valid.';
        }

        $email = $get('email');

        if ($email !== null && (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return 'Email tidak valid.';
        }

        $emergencyPhone = null;

        if ($get('emergency_phone') !== null) {
            $emergencyPhone = PhoneNumber::normalize($get('emergency_phone'));

            if ($emergencyPhone === null) {
                return 'Nomor kontak darurat tidak valid.';
            }
        }

        foreach (['occupation' => 120, 'emergency_name' => 255, 'emergency_relation' => 255, 'notes' => 2000] as $field => $max) {
            if ($get($field) !== null && mb_strlen($get($field)) > $max) {
                return "Kolom {$field} terlalu panjang (maksimal {$max} karakter).";
            }
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email === null ? null : mb_strtolower($email),
            'occupation' => $get('occupation'),
            'emergency_name' => $get('emergency_name'),
            'emergency_relation' => $get('emergency_relation'),
            'emergency_phone' => $emergencyPhone,
            'notes' => $get('notes'),
        ];
    }
}
