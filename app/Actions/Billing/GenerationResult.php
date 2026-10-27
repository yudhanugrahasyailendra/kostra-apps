<?php

namespace App\Actions\Billing;

final class GenerationResult
{
    /**
     * @param  list<array{lease_id: int, error: string}>  $failures  Hanya id dan kelas galat; tanpa data pribadi.
     */
    public function __construct(
        public int $issued = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public array $failures = [],
    ) {}
}
