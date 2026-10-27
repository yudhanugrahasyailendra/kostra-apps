<?php

namespace Tests\Feature\Payments;

use App\Models\Attachment;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpBilling();
    }

    private function create(array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.payment-accounts.store'), array_merge([
            'type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8820-1928-30', 'account_name' => 'Hendra Wijaya',
        ], $data));
    }

    private function accounts()
    {
        return $this->asTenant($this->tenant, fn () => PaymentAccount::orderBy('id')->get());
    }

    public function test_owner_adds_a_bank_account_and_the_first_one_becomes_the_default(): void
    {
        $this->create(['instructions' => 'Cantumkan nomor kamar'])->assertSessionHasNoErrors();

        $account = $this->accounts()->sole();
        $this->assertSame(['bank', 'BCA', '8820-1928-30', 'Hendra Wijaya', true, true], [
            $account->type->value, $account->bank_name, $account->account_number, $account->account_name, $account->is_default, $account->is_active,
        ]);
        $this->assertSame($this->tenant->id, $account->tenant_id);
        $this->assertSame('Cantumkan nomor kamar', $account->instructions);
    }

    public function test_only_one_account_is_the_default_and_choosing_another_replaces_it(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $this->create(['bank_name' => 'Mandiri', 'account_number' => '137-00-19283-11'])->assertSessionHasNoErrors();
        $this->assertSame([true, false], $this->accounts()->pluck('is_default')->all());

        $this->create(['bank_name' => 'BNI', 'account_number' => '0123456789', 'is_default' => '1'])->assertSessionHasNoErrors();
        $this->assertSame([false, false, true], $this->accounts()->pluck('is_default')->all());

        $second = $this->accounts()[1];
        $this->actingAs($this->owner)->put(route('owner.payment-accounts.update', $second), [
            'type' => 'bank', 'bank_name' => 'Mandiri', 'account_number' => '137-00-19283-11', 'is_default' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame([false, true, false], $this->accounts()->pluck('is_default')->all());
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => PaymentAccount::where('is_default', true)->count()));
    }

    public function test_an_inactive_account_cannot_be_the_default_and_deactivating_the_default_clears_it(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $account = $this->accounts()->sole();

        $this->actingAs($this->owner)->put(route('owner.payment-accounts.update', $account), [
            'type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8820-1928-30', 'is_default' => '1', 'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $saved = $this->accounts()->sole();
        $this->assertSame([false, false], [$saved->is_active, $saved->is_default]);
    }

    public function test_qris_and_ewallet_validation(): void
    {
        $this->create(['type' => 'qris', 'bank_name' => '', 'account_number' => '', 'account_name' => 'Kos Melati'])->assertSessionHasNoErrors();
        $this->create(['type' => 'bank', 'bank_name' => '', 'account_number' => '8820'])->assertSessionHasErrors('bank_name');
        $this->create(['type' => 'bank', 'account_number' => ''])->assertSessionHasErrors('account_number');
        $this->create(['type' => 'ewallet', 'bank_name' => 'GoPay', 'account_number' => '0812-1111-2222'])->assertSessionHasNoErrors();
        $this->create(['account_number' => '12'])->assertSessionHasErrors('account_number');
        $this->create(['account_number' => '8820<script>'])->assertSessionHasErrors('account_number');
        $this->create(['type' => 'giro'])->assertSessionHasErrors('type');

        $this->assertSame(['qris', 'ewallet'], $this->accounts()->pluck('type.value')->all());
    }

    public function test_the_qris_image_is_stored_privately_and_replaced_on_new_upload(): void
    {
        $this->create(['type' => 'qris', 'bank_name' => '', 'account_number' => ''])->assertSessionHasNoErrors();
        $account = $this->accounts()->sole();

        $upload = fn (string $name) => $this->actingAs($this->owner)->post(route('owner.payment-accounts.qris', $account), ['file' => UploadedFile::fake()->image($name)]);

        $upload('qris-1.png')->assertSessionHasNoErrors();
        $first = $this->asTenant($this->tenant, fn () => Attachment::firstOrFail());
        Storage::disk('private')->assertExists($first->path);

        $upload('qris-2.png')->assertSessionHasNoErrors();
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
        Storage::disk('private')->assertMissing($first->path);

        $attachment = $this->asTenant($this->tenant, fn () => Attachment::firstOrFail());
        $this->assertSame(['payment_account', 'qris_image', false], [$attachment->attachable_type, $attachment->category->value, $attachment->is_sensitive]);

        $this->actingAs($this->owner)->get(route('owner.payment-accounts.edit', $account))->assertOk()->assertSee('<img', false);
        $this->actingAs($this->owner)->get($attachment->temporaryUrl())->assertOk();
        $this->actingAs($this->owner)->post(route('owner.payment-accounts.qris', $account), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
    }

    public function test_accounts_are_never_deleted(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $account = $this->accounts()->sole();

        $this->actingAs($this->owner)->delete('/owner/settings/payment-accounts/'.$account->id)->assertStatus(405);
        $names = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter();
        $this->assertFalse($names->contains(fn ($n) => str_contains($n, 'payment-accounts.destroy')));
    }

    public function test_deleting_a_qris_image_needs_the_settings_permission_and_stays_in_the_tenant(): void
    {
        $this->create(['type' => 'qris', 'bank_name' => '', 'account_number' => ''])->assertSessionHasNoErrors();
        $account = $this->accounts()->sole();
        $this->actingAs($this->owner)->post(route('owner.payment-accounts.qris', $account), ['file' => UploadedFile::fake()->image('q.png')])->assertSessionHasNoErrors();
        $qris = $this->asTenant($this->tenant, fn () => Attachment::sole());

        $plain = User::factory()->for($this->tenant)->staff()->create();
        $this->actingAs($plain)->delete(route('owner.attachments.destroy', $qris))->assertForbidden();
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
        Storage::disk('private')->assertExists($qris->path);

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();
        $this->actingAs($stranger)->delete(route('owner.attachments.destroy', $qris))->assertNotFound();

        $allowed = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();
        $this->actingAs($allowed)->delete(route('owner.attachments.destroy', $qris))
            ->assertRedirect(route('owner.payment-accounts.edit', $account));
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_only_users_with_the_settings_permission_manage_accounts(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $account = $this->accounts()->sole();
        $plain = User::factory()->for($this->tenant)->staff()->create();
        $allowed = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();

        $this->actingAs($plain)->get(route('owner.payment-accounts.index'))->assertForbidden();
        $this->create(['bank_name' => 'BNI'], $plain)->assertForbidden();
        $this->actingAs($plain)->post(route('owner.payment-accounts.qris', $account), ['file' => UploadedFile::fake()->image('q.png')])->assertForbidden();

        $this->actingAs($allowed)->get(route('owner.payment-accounts.index'))->assertOk();
        $this->create(['bank_name' => 'BNI', 'account_number' => '5550001'], $allowed)->assertSessionHasNoErrors();
        $this->assertCount(2, $this->accounts());
    }

    public function test_accounts_are_isolated_between_tenants(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $mine = $this->accounts()->sole();

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();
        $theirs = $this->asTenant($other, fn () => PaymentAccount::create(['type' => 'bank', 'bank_name' => 'BRI', 'account_number' => '999888777', 'is_active' => true]));

        $this->actingAs($stranger)->get(route('owner.payment-accounts.edit', $mine))->assertNotFound();
        $this->actingAs($stranger)->put(route('owner.payment-accounts.update', $mine), ['type' => 'bank', 'bank_name' => 'X', 'account_number' => '1234567'])->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.payment-accounts.index'))->assertSee('8820-1928-30')->assertDontSee('999888777');

        $this->assertSame('BCA', $this->asTenant($this->tenant, fn () => PaymentAccount::findOrFail($mine->id)->bank_name));
        $this->assertSame('BRI', $this->asTenant($other, fn () => PaymentAccount::findOrFail($theirs->id)->bank_name));
    }

    public function test_an_account_can_be_limited_to_one_property_of_the_same_tenant_only(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $foreignProperty = $this->propertyFor($other);

        $this->create(['property_id' => $this->property->id])->assertSessionHasNoErrors();
        $this->create(['property_id' => $foreignProperty->id])->assertSessionHasErrors('property_id');

        $this->assertSame($this->property->id, $this->accounts()->sole()->property_id);
    }
}
