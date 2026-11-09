<?php

namespace App\Events;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setiap status tiket berubah. Fase 9 mendengarkannya untuk memberi tahu penghuni. */
class ComplaintStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Complaint $complaint,
        public readonly ComplaintStatus $from,
        public readonly ComplaintStatus $to,
    ) {}
}
