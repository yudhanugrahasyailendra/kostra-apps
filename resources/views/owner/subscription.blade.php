@extends('layouts.owner')

@section('title', 'Langganan')

@section('content')
    @php($rank = fn ($candidate) => $candidate->price_monthly <=> $plan->price_monthly)
    @php($limit = fn ($value) => $value === null ? 'tanpa batas' : number_format($value, 0, ',', '.'))

    <x-page-header eyebrow="Pengaturan kos" title="Paket Langganan & Kuota" subtitle="Pantau batas kamar, pengguna, dan pesan WhatsApp, lalu pilih paket yang sesuai dengan skala kos Anda." />

    @if ($tenant->isSuspended())
        <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
            Akun ditangguhkan karena langganan belum dibayar. Anda masih bisa melihat dan mengekspor data. Setelah transfer dicatat Super Admin, akun aktif kembali.
        </div>
    @endif

    <x-panel title="Paket Anda">
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <div class="font-headline-lg text-headline-lg" data-stat="plan">{{ $plan->name }}</div>
                <div class="mt-1 flex flex-wrap gap-2">
                    <x-status-badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-status-badge>
                    @if ($subscription->status === App\Enums\SubscriptionStatus::Trialing && $subscription->trial_ends_at)
                        <x-status-badge tone="warn">Percobaan sampai {{ $subscription->trial_ends_at->timezone($tenant->timezone)->translatedFormat('j F Y') }}</x-status-badge>
                    @endif
                </div>
            </div>
            <div class="font-body-md text-secondary text-right angka">
                @if ($subscription->current_period_end)
                    Periode {{ $subscription->current_period_start->translatedFormat('j M Y') }} – {{ $subscription->current_period_end->translatedFormat('j M Y') }}
                @else
                    Tanpa batas waktu
                @endif
                @if ($subscription->status === App\Enums\SubscriptionStatus::PastDue && $subscription->grace_ends_at)
                    <div class="text-error">Perpanjangan belum dibayar. Bayar sebelum {{ $subscription->grace_ends_at->timezone($tenant->timezone)->subSecond()->translatedFormat('j F Y') }} agar akun tidak ditangguhkan.</div>
                @endif
            </div>
        </div>

        <div class="mt-space-md grid grid-cols-2 lg:grid-cols-4 gap-space-md">
            @foreach ([
                ['Properti', $usage['properties'], $plan->max_properties],
                ['Kamar', $usage['rooms'], $plan->max_rooms],
                ['Pengguna', $usage['users'], $plan->max_users],
                ['Pesan bulan ini', $usage['messages'], $messageLimit],
            ] as [$label, $used, $max])
                @php($pct = $max ? min(100, round($used / $max * 100)) : 0)
                <div class="rounded-xl bg-surface-container-low p-space-md flex flex-col gap-space-xs">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="angka font-label-lg text-label-lg">{{ $used }} / {{ $limit($max) }}</div>
                    @if ($max)
                        <div class="meter" role="img" aria-label="{{ $label }}: {{ $used }} dari {{ $max }} ({{ $pct }}%)"><span style="width: {{ $pct }}%" class="{{ $pct >= 90 ? 'bg-error!' : '' }}"></span></div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-panel>

    @if ($pendingRequest)
        <div class="rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="status" data-stat="pending-request">
            Pengajuan paket {{ $pendingRequest->plan->name }} ({{ $pendingRequest->billing_period->label() }}) menunggu pembayaran. Paket Anda tetap {{ $plan->name }} sampai transfer dicatat oleh tim Kostra.
        </div>
    @endif

    <x-panel title="Pilih paket">
        @if ($hasPending)
            <p class="mb-space-md rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="status">Masih ada tagihan langganan yang menunggu pembayaran, jadi Anda belum bisa berlangganan paket lain.</p>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
            @foreach ($plans as $candidate)
                @php($relation = $rank($candidate))
                <div class="rounded-xl p-space-md flex flex-col gap-space-sm {{ $relation === 0 ? 'bg-primary-fixed/30 ring-2 ring-primary' : 'bg-surface-container-low' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-headline-md text-headline-md">{{ $candidate->name }}</h3>
                        @if ($relation === 0)
                            <x-status-badge tone="ok">Paket sekarang</x-status-badge>
                        @endif
                    </div>
                    <div class="angka font-label-lg">
                        {{ $candidate->price_monthly === 0 ? 'Gratis' : App\Support\Rupiah::format($candidate->price_monthly).' / bulan' }}
                        @if ($candidate->price_yearly)
                            <span class="font-body-sm text-secondary">· {{ App\Support\Rupiah::format($candidate->price_yearly) }} / tahun</span>
                        @endif
                    </div>
                    <ul class="font-body-sm text-secondary">
                        <li>{{ $limit($candidate->max_properties) }} properti, {{ $limit($candidate->max_rooms) }} kamar</li>
                        <li>{{ $limit($candidate->max_users) }} pengguna (pemilik dan staf)</li>
                        <li>{{ number_format($candidate->monthly_message_quota, 0, ',', '.') }} pesan WhatsApp per bulan</li>
                        @foreach (($candidate->features ?? []) as $feature)
                            <li>{{ config('kostra.features.'.$feature, $feature) }}</li>
                        @endforeach
                    </ul>

                    @if ($relation > 0 && ! $hasPending)
                        <div class="flex flex-wrap gap-2 mt-auto">
                            @if (! $trialUsed && $plan->price_monthly === 0)
                                <form method="POST" action="{{ route('owner.subscription.trial') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $candidate->id }}">
                                    <x-button type="submit" variant="secondary">Coba {{ $trialDays }} hari</x-button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('owner.subscription.subscribe') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $candidate->id }}">
                                <input type="hidden" name="billing_period" value="monthly">
                                <x-button type="submit">Berlangganan bulanan</x-button>
                            </form>
                            @if ($candidate->price_yearly)
                                <form method="POST" action="{{ route('owner.subscription.subscribe') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $candidate->id }}">
                                    <input type="hidden" name="billing_period" value="yearly">
                                    <x-button type="submit" variant="secondary">Tahunan</x-button>
                                </form>
                            @endif
                        </div>
                    @elseif ($relation < 0)
                        <form method="POST" action="{{ route('owner.subscription.downgrade') }}" class="mt-auto" onsubmit="return confirm('Turun ke paket {{ $candidate->name }}? Data Anda tidak dihapus, tetapi batas paket berlaku.')">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $candidate->id }}">
                            <x-button type="submit" variant="secondary">Turun ke paket ini</x-button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        @error('plan')
            <p class="mt-space-md font-body-sm text-error" role="alert">{{ $message }}</p>
        @enderror
    </x-panel>

    <x-panel title="Tagihan langganan">
        @if ($invoices->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th>Nomor</th><th>Periode</th><th class="text-right">Jumlah</th><th>Jatuh tempo</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="angka">{{ $invoice->number }}</td>
                                <td class="angka">{{ $invoice->period_start->translatedFormat('j M Y') }} – {{ $invoice->period_end->translatedFormat('j M Y') }}</td>
                                <td class="angka text-right">{{ App\Support\Rupiah::format($invoice->amount) }}</td>
                                <td class="angka">{{ $invoice->due_date->translatedFormat('j M Y') }}</td>
                                <td><x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-on-surface-variant">Belum ada tagihan langganan.</p>
        @endif

        <div class="mt-space-md rounded-xl bg-surface-container-low p-space-md">
            <h3 class="font-label-lg mb-1">Cara membayar</h3>
            <p class="font-body-md">Transfer sesuai jumlah tagihan ke <strong>{{ $transfer['bank'] }}</strong> {{ $transfer['account_number'] }} a.n. {{ $transfer['account_name'] }}, cantumkan nomor tagihan, lalu kabari kami. Paket menjadi aktif setelah transfer dicatat oleh tim Kostra. Pembayaran tidak melewati rekening Kostra untuk uang sewa penghuni Anda.</p>
        </div>
    </x-panel>

    <x-panel title="Data Anda">
        <p class="text-on-surface-variant mb-space-sm">Anda selalu bisa mengunduh seluruh data kos, termasuk saat langganan berakhir.</p>
        <x-button :href="route('owner.export.index')" variant="secondary">Ekspor data</x-button>
    </x-panel>
@endsection
