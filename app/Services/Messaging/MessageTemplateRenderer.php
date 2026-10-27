<?php

namespace App\Services\Messaging;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Mengisi variabel {{...}} pada template. Hanya variabel di VARIABLES yang diizinkan; variabel tak dikenal
 * ditolak, baik saat template disimpan maupun saat dirender.
 */
class MessageTemplateRenderer
{
    public const VARIABLES = ['nama', 'jumlah', 'jatuh_tempo', 'link', 'status'];

    /** Batas isi template WhatsApp. */
    public const MAX_LENGTH = 1000;

    /**
     * @return list<string> nama variabel (apa adanya) yang tidak diizinkan
     */
    public function unknownVariables(string $body): array
    {
        $unknown = [];

        foreach ($this->placeholders($body) as $name) {
            if (! in_array($name, self::VARIABLES, true) && ! in_array($name, $unknown, true)) {
                $unknown[] = $name;
            }
        }

        return $unknown;
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(string $body, string $field = 'body'): void
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([$field => 'Isi template wajib diisi (maksimal '.self::MAX_LENGTH.' karakter).']);
        }

        $unknown = $this->unknownVariables($body);

        if ($unknown !== []) {
            $allowed = implode(', ', array_map(fn (string $v) => '{{'.$v.'}}', self::VARIABLES));

            throw ValidationException::withMessages([
                $field => 'Variabel tidak dikenal: '.implode(', ', array_map(fn (string $v) => '{{'.$v.'}}', $unknown)).'. Variabel yang boleh: '.$allowed.'.',
            ]);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    public function render(string $body, array $values): string
    {
        $unknown = $this->unknownVariables($body);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Variabel tidak dikenal pada template: '.implode(', ', $unknown));
        }

        return preg_replace_callback('/\{\{(.*?)\}\}/', function (array $match) use ($values) {
            $name = trim($match[1]);

            if (! array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Nilai untuk variabel {$name} tidak diberikan.");
            }

            return $values[$name];
        }, $body);
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $body): array
    {
        preg_match_all('/\{\{(.*?)\}\}/', $body, $matches);

        return array_map('trim', $matches[1]);
    }
}
