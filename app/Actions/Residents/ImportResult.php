<?php

namespace App\Actions\Residents;

final class ImportResult
{
    /**
     * @param  list<array{line: int, name: string, reason: string}>  $failures
     * @param  list<array{line: int, name: string, phone: string}>  $duplicates
     */
    public function __construct(
        public int $created = 0,
        public array $failures = [],
        public array $duplicates = [],
    ) {}
}
