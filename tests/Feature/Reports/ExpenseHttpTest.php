<?php

namespace Tests\Feature\Reports;

use App\Actions\EnsureDefaultExpenseCategories;
use App\Actions\RegisterOwner;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExpenseHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private User $staffNoFinance;

    private User $staffFinance;

    private Property $propertyA;

    private Property $propertyB;

    private ExpenseCategory $categoryA;

    private ExpenseCategory $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
        Storage::fake('attachments');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffFinance = User::factory()->forTenant($this->tenantA)->staff(['finance.view'])->create();

        $this->propertyA = $this->inTenant($this->tenantA, fn () => Property::factory()->create());
        $this->propertyB = $this->inTenant($this->tenantB, fn () => Property::factory()->create());
        $this->categoryA = $this->inTenant($this->tenantA, fn () => ExpenseCategory::factory()->create(['name' => 'Listrik']));
        $this->categoryB = $this->inTenant($this->tenantB, fn () => ExpenseCategory::factory()->create(['name' => 'Listrik']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    /** @param  array<string, mixed>  $attributes */
    private function expenseIn(Tenant $tenant, User $by, array $attributes = []): Expense
    {
        return $this->inTenant($tenant, fn () => Expense::factory()->create($attributes + [
            'expense_category_id' => $tenant->is($this->tenantA) ? $this->categoryA->id : $this->categoryB->id,
            'created_by' => $by->id,
        ]));
    }

    /** @return array<string, mixed> */
    private function body(array $overrides = []): array
    {
        return $overrides + [
            'expense_category_id' => $this->categoryA->id,
            'property_id' => $this->propertyA->id,
            'amount' => 350000,
            'spent_on' => '2026-11-05',
            'description' => 'Token listrik koridor',
            'vendor_name' => 'PLN',
        ];
    }

    // Kategori bawaan

    public function test_pendaftaran_pemilik_membuat_kategori_bawaan_sekali_saja(): void
    {
        Plan::factory()->create(['key' => 'free']);

        $owner = app(RegisterOwner::class)->handle(['kos_name' => 'Kos Baru', 'name' => 'Budi', 'email' => 'budi@example.test', 'password' => 'rahasia-123']);

        $names = $this->inTenant($owner->tenant, fn () => ExpenseCategory::query()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(['Listrik', 'Air', 'Gaji penjaga', 'Perbaikan', 'Kebersihan', 'Lainnya'], $names);

        $this->inTenant($owner->tenant, function () {
            app(EnsureDefaultExpenseCategories::class)->handle();
            $this->assertSame(6, ExpenseCategory::query()->count());
            $this->assertTrue(ExpenseCategory::query()->where('name', 'Air')->value('is_system'));
        });
    }

    // Catat, ubah, hapus

    public function test_pemilik_mencatat_pengeluaran_dengan_nota(): void
    {
        $response = $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body([
            'receipt' => UploadedFile::fake()->image('nota.jpg'),
        ]));

        $response->assertRedirect()->assertSessionHas('success');
        $expense = $this->inTenant($this->tenantA, fn () => Expense::query()->sole());
        $this->assertSame(350000, $expense->amount);
        $this->assertSame('2026-11-05', $expense->spent_on->toDateString());
        $this->assertSame($this->ownerA->id, $expense->created_by);
        $this->assertSame($this->propertyA->id, $expense->property_id);

        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->sole());
        $this->assertSame(AttachmentCategory::ExpenseReceipt, $attachment->category);
        $this->assertSame($expense->id, $attachment->attachable_id);
        $this->assertFalse($attachment->is_sensitive);
    }

    public function test_pengeluaran_umum_tanpa_properti_diterima(): void
    {
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['property_id' => null]))->assertSessionHasNoErrors();

        $this->assertNull($this->inTenant($this->tenantA, fn () => Expense::query()->sole())->property_id);
    }

    public function test_validasi_pengeluaran_dalam_bahasa_indonesia(): void
    {
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body([
            'amount' => 0, 'spent_on' => '2027-01-01', 'description' => '',
        ]))->assertSessionHasErrors([
            'amount' => 'Jumlah harus lebih dari Rp 0.',
            'spent_on' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'description' => 'Keterangan wajib diisi.',
        ]);

        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['amount' => 12.5]))
            ->assertSessionHasErrors(['amount' => 'Jumlah harus berupa angka bulat rupiah.']);
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['receipt' => UploadedFile::fake()->create('nota.exe', 10)]))
            ->assertSessionHasErrors(['receipt' => 'Nota harus berformat JPG, PNG, atau PDF.']);

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Expense::query()->count()));
    }

    public function test_kategori_dan_properti_tenant_lain_ditolak(): void
    {
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['expense_category_id' => $this->categoryB->id]))
            ->assertSessionHasErrors('expense_category_id');
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['property_id' => $this->propertyB->id]))
            ->assertSessionHasErrors('property_id');

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Expense::query()->count()));
    }

    public function test_pengeluaran_diubah_dan_dihapus_secara_soft_delete(): void
    {
        $expense = $this->expenseIn($this->tenantA, $this->ownerA, ['amount' => 100000]);

        $this->actingAs($this->ownerA)->post("/app/pengeluaran/{$expense->id}", $this->body(['amount' => 125000]))->assertSessionHasNoErrors();
        $this->assertSame(125000, $expense->fresh()->amount);

        $this->actingAs($this->ownerA)->delete("/app/pengeluaran/{$expense->id}")->assertRedirect();
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Expense::query()->count()));
    }

    // Daftar dan filter

    public function test_daftar_difilter_per_periode_properti_dan_kategori(): void
    {
        $other = $this->inTenant($this->tenantA, fn () => ExpenseCategory::factory()->create(['name' => 'Air']));
        $otherProperty = $this->inTenant($this->tenantA, fn () => Property::factory()->create());
        $this->expenseIn($this->tenantA, $this->ownerA, ['amount' => 100000, 'spent_on' => '2026-11-02', 'property_id' => $this->propertyA->id]);
        $this->expenseIn($this->tenantA, $this->ownerA, ['amount' => 200000, 'spent_on' => '2026-11-03', 'property_id' => $otherProperty->id, 'expense_category_id' => $other->id]);
        $this->expenseIn($this->tenantA, $this->ownerA, ['amount' => 400000, 'spent_on' => '2026-11-04', 'property_id' => null]);
        $this->expenseIn($this->tenantA, $this->ownerA, ['amount' => 800000, 'spent_on' => '2026-10-04']);

        $this->actingAs($this->ownerA)->get('/app/pengeluaran')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Expenses/Index')
                ->has('expenses', 3)
                ->where('total', 700000)
                ->where('filters.period', '2026-11'));

        $this->actingAs($this->ownerA)->get("/app/pengeluaran?period=2026-11&property_id={$this->propertyA->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('total', 100000));

        $this->actingAs($this->ownerA)->get("/app/pengeluaran?period=2026-11&category_id={$other->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('total', 200000));

        $this->actingAs($this->ownerA)->get('/app/pengeluaran?period=2026-10')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('total', 800000));
    }

    public function test_props_pengeluaran_tidak_memuat_kolom_terlarang(): void
    {
        $expense = $this->expenseIn($this->tenantA, $this->ownerA);
        $this->actingAs($this->ownerA)->post("/app/pengeluaran/{$expense->id}", $this->body(['receipt' => UploadedFile::fake()->image('nota.png')]));

        $this->actingAs($this->ownerA)->get('/app/pengeluaran')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.0', fn (AssertableInertia $row) => $row
                    ->missing('tenant_id')->missing('deleted_at')->missing('created_by')->missing('complaint_id')
                    ->has('receipts.0', fn (AssertableInertia $receipt) => $receipt->missing('path')->missing('disk')->missing('tenant_id')->etc())
                    ->etc()));
    }

    // Izin

    public function test_staf_tanpa_izin_keuangan_ditolak_di_semua_aksi_pengeluaran(): void
    {
        $expense = $this->expenseIn($this->tenantA, $this->ownerA);

        $this->actingAs($this->staffNoFinance)->get('/app/pengeluaran')->assertForbidden();
        $this->actingAs($this->staffNoFinance)->post('/app/pengeluaran', $this->body())->assertForbidden();
        $this->actingAs($this->staffNoFinance)->post("/app/pengeluaran/{$expense->id}", $this->body())->assertForbidden();
        $this->actingAs($this->staffNoFinance)->delete("/app/pengeluaran/{$expense->id}")->assertForbidden();

        $this->assertNull($expense->fresh()->deleted_at);
        $this->assertSame(1, $this->inTenant($this->tenantA, fn () => Expense::query()->count()));
    }

    public function test_staf_dengan_izin_keuangan_boleh_mencatat(): void
    {
        $this->actingAs($this->staffFinance)->get('/app/pengeluaran')->assertOk();
        $this->actingAs($this->staffFinance)->post('/app/pengeluaran', $this->body())->assertSessionHasNoErrors();
        $this->assertSame($this->staffFinance->id, $this->inTenant($this->tenantA, fn () => Expense::query()->sole())->created_by);
    }

    public function test_nota_hanya_terbuka_bagi_pengguna_dengan_izin_keuangan(): void
    {
        $this->actingAs($this->ownerA)->post('/app/pengeluaran', $this->body(['receipt' => UploadedFile::fake()->image('nota.jpg')]));
        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->sole());
        $url = URL::temporarySignedRoute('owner.attachments.show', now()->addMinutes(5), ['attachment' => $attachment->id], absolute: false);

        $this->actingAs($this->staffNoFinance)->get($url)->assertForbidden();
        $this->actingAs($this->staffFinance)->get($url)->assertOk();
        $this->actingAs($this->ownerB)->get($url)->assertNotFound();
    }

    // Isolasi tenant

    public function test_pengguna_tenant_a_tidak_membaca_atau_mengubah_pengeluaran_tenant_b(): void
    {
        $theirs = $this->expenseIn($this->tenantB, $this->ownerB, ['amount' => 999000, 'description' => 'Rahasia tenant B']);
        $mine = $this->expenseIn($this->tenantA, $this->ownerA);

        // Halaman pertama membuat kategori bawaan tenant A (Listrik sudah ada, jadi 6 kategori); milik B tidak ikut.
        $this->actingAs($this->ownerA)->get('/app/pengeluaran')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses', 1)->where('expenses.0.id', $mine->id)->where('total', 250000)
                ->has('categories', 6)->has('properties', 1)
                ->where('categories', fn ($categories) => ! collect($categories)->contains('id', $this->categoryB->id)));
        $this->assertSame(1, $this->inTenant($this->tenantB, fn () => ExpenseCategory::query()->count()), 'Tenant B tidak ikut dibuatkan kategori.');

        $this->actingAs($this->ownerA)->post("/app/pengeluaran/{$theirs->id}", $this->body(['amount' => 1]))->assertNotFound();
        $this->actingAs($this->ownerA)->delete("/app/pengeluaran/{$theirs->id}")->assertNotFound();

        $this->assertSame(999000, $theirs->fresh()->amount);
        $this->assertNull($theirs->fresh()->deleted_at);

        $this->actingAs($this->ownerA)->get("/app/pengeluaran?category_id={$this->categoryB->id}")->assertSessionHasErrors('category_id');
    }
}
