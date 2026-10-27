<?php

namespace App\Actions\Rooms;

final class ImportResult
{
    /**
     * @param  list<array{line: int, number: string, reason: string}>  $failures
     */
    public function __construct(
        public int $created = 0,
        public array $failures = [],
    ) {}

    public function fail(int $line, string $number, string $reason): void
    {
        $this->failures[] = ['line' => $line, 'number' => $number, 'reason' => $reason];
    }
}
