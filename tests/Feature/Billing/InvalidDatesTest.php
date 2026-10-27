<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\CreateManualInvoice;
use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Leases\CheckInResident;
use App\Actions\Leases\CheckOutResident;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\TransferRoom;
use App\Actions\Payments\SubmitPaymentProof;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

/**
 * Regresi: Action tidak boleh meledak menjadi galat 500 bila menerima tanggal rusak (Carbon 3 melempar
 * InvalidFormatException). Mereka harus menolak dengan ValidationException, apa pun yang memanggilnya.
 */
class InvalidDatesTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    public static function garbage(): array
    {
        return [['kemarin'], ['2026-02-30'], ['15/10/2026'], ['']];
    }

    private function rejects(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail('Seharusnya ditolak pada kunci '.$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    #[DataProvider('garbage')]
    public function test_record_payment_rejects_garbage_dates(string $value): void
    {
        $invoice = $this->openInvoice();

        $this->rejects(fn () => $this->record([[$invoice, 1000]], ['paid_on' => $value]), 'paid_on');
    }

    #[DataProvider('garbage')]
    public function test_check_in_rejects_garbage_dates(string $value): void
    {
        $room = Room::factory()->for($this->tenant)->for($this->property)->create();
        $resident = Resident::factory()->for($this->tenant)->create();
        $call = fn (array $o) => fn () => $this->asTenant($this->tenant, fn () => app(CheckInResident::class)(array_merge([
            'room_id' => $room->id, 'primary_resident_id' => $resident->id, 'start_date' => '2026-10-01',
        ], $o)));

        $this->rejects($call(['start_date' => $value]), 'start_date');
        $this->rejects($call(['end_date' => $value === '' ? 'x' : $value]), 'end_date');
        $this->rejects($call(['deposit_received_on' => $value === '' ? 'x' : $value]), 'deposit_received_on');
    }

    #[DataProvider('garbage')]
    public function test_check_out_renew_and_transfer_reject_garbage_dates(string $value): void
    {
        $lease = $this->leaseFor();
        $target = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '900']);

        $this->rejects(fn () => $this->asTenant($this->tenant, fn () => app(CheckOutResident::class)($lease, [
            'checkout_date' => $value, 'ended_reason' => 'moved_out',
        ])), 'checkout_date');

        $this->rejects(fn () => $this->asTenant($this->tenant, fn () => app(RenewLease::class)($lease, ['end_date' => $value])), 'end_date');

        $this->rejects(fn () => $this->asTenant($this->tenant, fn () => app(TransferRoom::class)($lease, [
            'to_room_id' => $target->id, 'transferred_on' => $value,
        ], $this->owner)), 'transferred_on');
    }

    #[DataProvider('garbage')]
    public function test_manual_invoice_rejects_garbage_due_dates(string $value): void
    {
        $lease = $this->leaseFor();

        $this->rejects(fn () => $this->asTenant($this->tenant, fn () => app(CreateManualInvoice::class)($lease, [
            'due_date' => $value,
            'items' => [['type' => 'other', 'description' => 'X', 'quantity' => '1', 'unit_price' => 1000]],
        ])), 'due_date');
    }

    #[DataProvider('garbage')]
    public function test_payment_proof_rejects_garbage_transfer_dates(string $value): void
    {
        if ($value === '') {
            $this->markTestSkipped('Tanggal kosong berarti "tidak diisi" untuk bukti transfer.');
        }

        Storage::fake('private');
        $invoice = $this->openInvoice();

        $this->rejects(fn () => $this->asTenant($this->tenant, fn () => app(SubmitPaymentProof::class)(
            $invoice, ['amount' => 1000, 'paid_on' => $value], UploadedFile::fake()->image('b.jpg'), $this->owner,
        )), 'paid_on');
    }

    public function test_generating_for_a_malformed_period_is_a_clear_programming_error(): void
    {
        $this->expectException(\ValueError::class);

        $this->asTenant($this->tenant, fn () => app(GenerateMonthlyInvoices::class)('oktober'));
    }
}
