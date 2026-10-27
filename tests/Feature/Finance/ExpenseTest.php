<?php

namespace Tests\Feature\Finance;

use App\Actions\Auth\RegisterOwner;
use App\Actions\Finance\SeedExpenseCategories;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpFinance();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'spent_on' => '2026-10-10',
            'amount' => '250.000',
            'expense_category_id' => $this->category('Listrik')->id,
            'description' => 'Token listrik bulan Oktober',
        ], $overrides);
    }

    private function expenses(?Tenant $tenant = null)
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => Expense::withTrashed()->orderBy('id')->get());
    }

    public function test_registering_an_owner_creates_the_default_categories_for_that_tenant_only(): void
    {
        $owner = app(RegisterOwner::class)([
            'name' => 'Budi', 'business_name' => 'Kos Melati', 'email' => 'budi@example.test', 'password' => 'rahasia-1234',
        ]);

        $names = $this->asTenant($owner->tenant, fn () => ExpenseCategory::orderBy('sort_order')->pluck('name')->all());

        $this->assertSame(['Listrik', 'Air', 'Gaji penjaga', 'Perbaikan', 'Kebersihan', 'Lainnya'], $names);
        $this->assertTrue($this->asTenant($owner->tenant, fn () => ExpenseCategory::where('is_system', false)->doesntExist()));
        $this->assertSame(6, $this->asTenant($this->tenant, fn () => ExpenseCategory::count()));
    }

    public function test_seeding_categories_twice_does_not_duplicate_and_keeps_owner_made_names(): void
    {
        $this->asTenant($this->tenant, function () {
            ExpenseCategory::query()->where('name', 'Air')->delete();
            ExpenseCategory::create(['name' => 'air', 'is_system' => false, 'sort_order' => 9]);

            app(SeedExpenseCategories::class)();
            app(SeedExpenseCategories::class)();
        });

        $names = $this->asTenant($this->tenant, fn () => ExpenseCategory::pluck('name')->map(fn ($n) => mb_strtolower($n))->all());

        $this->assertSame(6, count($names));
        $this->assertSame(1, count(array_keys($names, 'air')));
    }

    public function test_owner_records_an_expense_with_a_receipt(): void
    {
        $response = $this->actingAs($this->owner)->post(route('owner.expenses.store'), $this->payload([
            'property_id' => $this->property->id,
            'vendor_name' => 'PLN',
            'file' => UploadedFile::fake()->image('nota.png'),
        ]));

        $response->assertRedirect(route('owner.expenses.index'))->assertSessionHasNoErrors();

        $expense = $this->expenses()->sole();
        $this->assertSame([250_000, '2026-10-10', $this->property->id, $this->owner->id, 'PLN'], [
            $expense->amount, $expense->spent_on->toDateString(), $expense->property_id, $expense->created_by, $expense->vendor_name,
        ]);

        $attachment = $this->asTenant($this->tenant, fn () => Attachment::sole());
        $this->assertSame(['expense', 'expense_receipt', $this->tenant->id], [
            $attachment->attachable_type, $attachment->category->value, $attachment->tenant_id,
        ]);
        Storage::disk('private')->assertExists($attachment->path);
    }

    public function test_a_general_expense_has_no_property(): void
    {
        $this->actingAs($this->owner)->post(route('owner.expenses.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertNull($this->expenses()->sole()->property_id);
    }

    public function test_validation_rules(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->owner)->post(route('owner.expenses.store'), $this->payload($overrides));

        $post(['amount' => '0'])->assertSessionHasErrors('amount');
        $post(['amount' => '12,5'])->assertSessionHasErrors('amount');
        $post(['amount' => ''])->assertSessionHasErrors('amount');
        $post(['description' => ''])->assertSessionHasErrors('description');
        $post(['spent_on' => '2026-10-16'])->assertSessionHasErrors('spent_on');
        $post(['spent_on' => '2026-02-30'])->assertSessionHasErrors('spent_on');
        $post(['expense_category_id' => 999999])->assertSessionHasErrors('expense_category_id');
        $post(['file' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('file');
        $post(['file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('file');

        $this->assertCount(0, $this->expenses());
        $post(['spent_on' => '2026-10-15'])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->expenses());
    }

    public function test_property_and_category_of_another_tenant_are_rejected(): void
    {
        [$other, $otherOwner] = $this->tenantWithOwner('business');
        $foreignProperty = $this->propertyFor($other);
        $foreignCategory = $this->category('Air', $other);

        $this->actingAs($this->owner)->post(route('owner.expenses.store'), $this->payload(['property_id' => $foreignProperty->id]))
            ->assertSessionHasErrors('property_id');
        $this->actingAs($this->owner)->post(route('owner.expenses.store'), $this->payload(['expense_category_id' => $foreignCategory->id]))
            ->assertSessionHasErrors('expense_category_id');

        $this->assertCount(0, $this->expenses());
        $this->assertCount(0, $this->expenses($other));
    }

    public function test_the_list_filters_by_property_category_and_month(): void
    {
        $other = $this->propertyFor($this->tenant, ['name' => 'Kos Anggrek']);
        $this->expense(['description' => 'Listrik A', 'property_id' => $this->property->id, 'spent_on' => '2026-10-05']);
        $this->expense(['description' => 'Listrik B', 'property_id' => $other->id, 'spent_on' => '2026-10-06']);
        $this->expense(['description' => 'Listrik September', 'property_id' => $this->property->id, 'spent_on' => '2026-09-20']);
        $air = $this->category('Air');
        $this->asTenant($this->tenant, fn () => Expense::factory()->for($this->tenant)->for($air, 'category')->for($this->owner, 'creator')
            ->create(['description' => 'Air PDAM', 'spent_on' => '2026-10-07']));

        $get = fn (array $query) => $this->actingAs($this->owner)->get(route('owner.expenses.index', $query));

        $get([])->assertOk()->assertSee('Listrik A')->assertSee('Listrik B')->assertSee('Air PDAM')->assertDontSee('Listrik September');
        $get(['period' => 'all'])->assertSee('Listrik September');
        $get(['property_id' => $other->id])->assertSee('Listrik B')->assertDontSee('Listrik A')->assertDontSee('Air PDAM');
        $get(['expense_category_id' => $air->id])->assertSee('Air PDAM')->assertDontSee('Listrik A');
        $get(['q' => 'PDAM'])->assertSee('Air PDAM')->assertDontSee('Listrik A');
        $get([])->assertSee('Rp 750.000');
    }

    public function test_updating_an_expense_is_audited_and_validated(): void
    {
        $expense = $this->expense(['amount' => 100_000]);

        $this->actingAs($this->owner)->put(route('owner.expenses.update', $expense), $this->payload(['amount' => '175.000', 'description' => 'Revisi']))
            ->assertRedirect(route('owner.expenses.index'));

        $fresh = $this->expenses()->sole();
        $this->assertSame([175_000, 'Revisi'], [$fresh->amount, $fresh->description]);

        $log = $this->asTenant($this->tenant, fn () => AuditLog::where('event', 'updated')->latest('id')->first());
        $this->assertNotNull($log);
        $this->assertSame(175_000, $log->new_values['amount']);

        $this->actingAs($this->owner)->put(route('owner.expenses.update', $expense), $this->payload(['amount' => '0']))->assertSessionHasErrors('amount');
    }

    public function test_deleting_is_a_soft_delete_that_only_the_owner_can_do(): void
    {
        $expense = $this->expense();
        $staff = $this->staffWith(['finance.view']);

        $this->actingAs($staff)->delete(route('owner.expenses.destroy', $expense))->assertForbidden();
        $this->assertNull($this->expenses()->sole()->deleted_at);

        $this->actingAs($this->owner)->delete(route('owner.expenses.destroy', $expense))->assertRedirect(route('owner.expenses.index'));

        $this->assertNotNull($this->expenses()->sole()->deleted_at);
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Expense::count()));
        $this->assertNotNull($this->asTenant($this->tenant, fn () => AuditLog::where('event', 'deleted')->first()));
    }

    public function test_staff_without_finance_permission_cannot_touch_expenses_or_their_receipts(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->owner)->put(route('owner.expenses.update', $expense), $this->payload(['file' => UploadedFile::fake()->image('n.png')]));
        $receipt = $this->asTenant($this->tenant, fn () => Attachment::sole());
        $plain = $this->staffWith();

        $this->actingAs($plain)->get(route('owner.expenses.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('owner.expenses.create'))->assertForbidden();
        $this->actingAs($plain)->post(route('owner.expenses.store'), $this->payload())->assertForbidden();
        $this->actingAs($plain)->get(route('owner.expenses.edit', $expense))->assertForbidden();
        $this->actingAs($plain)->put(route('owner.expenses.update', $expense), $this->payload())->assertForbidden();
        $this->actingAs($plain)->get($receipt->temporaryUrl())->assertForbidden();

        $allowed = $this->staffWith(['finance.view']);
        $this->actingAs($allowed)->get(route('owner.expenses.index'))->assertOk();
        $this->actingAs($allowed)->get($receipt->temporaryUrl())->assertOk();
        $this->actingAs($allowed)->post(route('owner.expenses.store'), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_receipts_are_financial_attachments_that_cannot_be_deleted(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->owner)->put(route('owner.expenses.update', $expense), $this->payload(['file' => UploadedFile::fake()->image('n.png')]));
        $receipt = $this->asTenant($this->tenant, fn () => Attachment::sole());

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $receipt))->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_other_tenants_cannot_see_edit_or_delete_expenses(): void
    {
        $expense = $this->expense(['description' => 'Rahasia tenant A']);
        [$other, $stranger] = $this->tenantWithOwner('business');
        $this->expense(['description' => 'Milik tenant B'], $other);

        $this->actingAs($stranger)->get(route('owner.expenses.edit', $expense))->assertNotFound();
        $this->actingAs($stranger)->put(route('owner.expenses.update', $expense), $this->payload())->assertNotFound();
        $this->actingAs($stranger)->delete(route('owner.expenses.destroy', $expense))->assertNotFound();
        $this->actingAs($stranger)->get(route('owner.expenses.index'))->assertOk()
            ->assertSee('Milik tenant B')->assertDontSee('Rahasia tenant A');

        $this->assertNull($this->expenses()->sole()->deleted_at);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('owner.expenses.index'))->assertRedirect(route('login'));
        $this->post(route('owner.expenses.store'), $this->payload())->assertRedirect(route('login'));
    }
}
