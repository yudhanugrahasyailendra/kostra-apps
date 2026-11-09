<?php

namespace App\Http\Controllers\Owner;

use App\Actions\DeleteResident;
use App\Actions\ImportResidents;
use App\Actions\SaveResident;
use App\Enums\AttachmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ImportResidentsRequest;
use App\Http\Requests\Owner\ResidentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\LeaseResource;
use App\Http\Resources\ResidentDetailResource;
use App\Http\Resources\ResidentResource;
use App\Models\Attachment;
use App\Models\Lease;
use App\Models\LeaseResident;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Resident::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $q = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;

        $residents = Resident::query()
            ->withExists(['attachments as has_ktp' => fn ($query) => $query->where('category', AttachmentCategory::Ktp->value)])
            ->when($q !== '', fn ($query) => $this->search($query, $q))
            ->when($status !== null, fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        // Nomor ganda dicari hanya untuk nomor di halaman ini (satu query, tenant scope aktif).
        $duplicatePhones = Resident::query()
            ->whereIn('phone', $residents->pluck('phone')->unique()->values())
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('phone')
            ->flip();

        $residents->each(fn (Resident $r) => $r->setAttribute('has_duplicate_phone', $duplicatePhones->has($r->phone)));

        $counts = Resident::query()->selectRaw('is_active, count(*) as total')->groupBy('is_active')->pluck('total', 'is_active');

        return Inertia::render('Owner/Residents/Index', [
            'residents' => ResidentResource::collection($residents->items())->resolve(),
            'pagination' => [
                'current_page' => $residents->currentPage(),
                'last_page' => $residents->lastPage(),
                'total' => $residents->total(),
                'prev_url' => $residents->previousPageUrl(),
                'next_url' => $residents->nextPageUrl(),
            ],
            'filters' => ['q' => $q === '' ? null : $q, 'status' => $status],
            'counts' => [
                'all' => (int) $counts->sum(),
                'active' => (int) ($counts[1] ?? 0),
                'inactive' => (int) ($counts[0] ?? 0),
            ],
            'can' => ['create' => $request->user()->can('create', Resident::class)],
        ]);
    }

    public function show(Request $request, Resident $resident): Response
    {
        $this->authorize('view', $resident);

        $user = $request->user();
        $resident->loadExists(['attachments as has_ktp' => fn ($query) => $query->where('category', AttachmentCategory::Ktp->value)]);

        // Berkas sensitif hanya dikirim bila Policy mengizinkan; selain itu cukup penanda `has_ktp`.
        $attachments = $resident->attachments()->orderByDesc('id')->get()
            ->filter(fn (Attachment $attachment) => $user->can('view', $attachment))
            ->values();

        // Riwayat kontrak (aktif lebih dulu, lalu yang terbaru).
        $leases = Lease::query()
            ->whereHas('leaseResidents', fn ($q) => $q->where('resident_id', $resident->id))
            ->with(['room', 'property', 'primaryResident', 'deposit'])
            ->orderByRaw("status = 'active' desc")->orderByDesc('start_date')->orderByDesc('id')
            ->get();

        return Inertia::render('Owner/Residents/Show', [
            'resident' => (new ResidentDetailResource($resident))->resolve(),
            'attachments' => AttachmentResource::collection($attachments)->resolve(),
            'leases' => LeaseResource::collection($leases)->resolve(),
            'categories' => array_map(fn (AttachmentCategory $c) => ['value' => $c->value, 'label' => $c->label()], AttachmentCategory::forResident()),
            'can' => [
                'update' => $user->can('update', $resident),
                'delete' => $user->can('delete', $resident),
                'upload_sensitive' => $user->can('create', [Attachment::class, AttachmentCategory::Ktp]),
            ],
        ]);
    }

    public function store(ResidentRequest $request, SaveResident $save): RedirectResponse
    {
        $resident = $save->handle($request->validated());

        return redirect()->route('owner.residents.show', $resident)->with('success', 'Penghuni ditambahkan.');
    }

    public function update(ResidentRequest $request, Resident $resident, SaveResident $save): RedirectResponse
    {
        $save->handle($request->validated(), $resident);

        return back()->with('success', 'Data penghuni diperbarui.');
    }

    public function destroy(Resident $resident, DeleteResident $delete): RedirectResponse
    {
        $this->authorize('delete', $resident);

        if (LeaseResident::query()->where('resident_id', $resident->id)->whereHas('lease', fn ($q) => $q->active())->exists()) {
            throw ValidationException::withMessages(['resident' => 'Penghuni masih punya kontrak aktif. Check-out lebih dulu.']);
        }

        $delete->handle($resident);

        return redirect()->route('owner.residents.index')->with('success', 'Penghuni dihapus.');
    }

    public function import(ImportResidentsRequest $request, ImportResidents $import): RedirectResponse
    {
        $result = $import->handle($request->file('file')->getRealPath());

        return back()
            ->with('success', "{$result['created']} penghuni diimpor, ".count($result['failures']).' baris gagal.')
            ->with('resident_import_result', $result);
    }

    /** Templat CSV dengan satu contoh baris. */
    public function template(): StreamedResponse
    {
        $this->authorize('viewAny', Resident::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nama', 'whatsapp', 'email', 'pekerjaan', 'kontak darurat', 'hubungan', 'telepon darurat', 'catatan']);
            fputcsv($out, ['Budi Santoso', '081234567890', 'budi@contoh.id', 'Karyawan', 'Ibu Budi', 'Ibu', '081298765432', '']);
            fclose($out);
        }, 'templat-impor-penghuni.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cari nama, pekerjaan, email, atau nomor (format lokal maupun internasional). */
    private function search($query, string $q): void
    {
        $like = '%'.addcslashes($q, '\\%_').'%';

        $query->where(function ($where) use ($like, $q) {
            $where->where('name', 'like', $like)
                ->orWhere('occupation', 'like', $like)
                ->orWhere('email', 'like', $like);

            $digits = preg_replace('/\D+/', '', $q) ?? '';

            if (strlen($digits) >= 3) {
                // Nomor tersimpan sebagai +62...; ubah awalan 0 lokal ke 62 agar cocok dengan potongan nomor.
                $digits = str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
                $where->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
