<?php

namespace App\Services\WhatsApp;

use Illuminate\Validation\ValidationException;

/**
 * Mengganti {{variabel}} pada isi template. Hanya variabel di VARIABLES yang diizinkan; variabel
 * lain ditolak, baik saat template disimpan (validate) maupun saat dirender (render), sehingga teks
 * yang keluar tidak pernah memuat sisa {{...}} atau data yang tidak dimaksudkan.
 */
class MessageTemplateRenderer
{
    public const VARIABLES = ['nama', 'jumlah', 'jatuh_tempo', 'link'];

    /** @return list<string> Variabel di body yang tidak diizinkan (unik, urutan kemunculan). */
    public function unknownVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/', $body, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $name) => ! in_array($name, self::VARIABLES, true),
        )));
    }

    /** Tolak body dengan variabel tak dikenal, atau kurung kurawal ganda yang tidak berpasangan. */
    public function validate(string $body, string $field = 'body'): void
    {
        $unknown = $this->unknownVariables($body);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                $field => 'Variabel tidak dikenal: '.implode(', ', array_map(fn (string $v) => '{{'.$v.'}}', $unknown)).'. Yang diizinkan: '
                    .implode(', ', array_map(fn (string $v) => '{{'.$v.'}}', self::VARIABLES)).'.',
            ]);
        }

        // Setelah variabel valid dibuang, sisa "{{" atau "}}" berarti penulisan variabel salah.
        $rest = preg_replace('/\{\{\s*[a-z_]+\s*\}\}/', '', $body);

        if (str_contains((string) $rest, '{{') || str_contains((string) $rest, '}}')) {
            throw ValidationException::withMessages([$field => 'Penulisan variabel salah. Gunakan format {{nama}}.']);
        }
    }

    /**
     * @param  array<string, string|int|null>  $values  nilai tiap variabel; variabel yang tidak diberikan dianggap kosong
     */
    public function render(string $body, array $values): string
    {
        $this->validate($body);

        $text = (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => (string) ($values[$m[1]] ?? ''),
            $body,
        );

        // Variabel kosong (mis. {{link}} sebelum portal ada) meninggalkan baris kosong berlebih.
        return trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)));
    }

    /** Format tampilan untuk pesan: "Rp 1.850.000". Uang tetap integer di seluruh sistem. */
    public static function formatRupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
