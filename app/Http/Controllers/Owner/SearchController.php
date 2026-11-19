<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SearchRequest;
use App\Models\Resident;
use App\Models\Room;
use App\Services\Search\OwnerSearch;
use Illuminate\Http\JsonResponse;

/**
 * Pencarian di kepala halaman pemilik. Mengembalikan JSON (dipanggil komponen GlobalSearch),
 * bukan halaman Inertia. Tiap jenis hasil hanya dicari bila Policy mengizinkan melihatnya.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, OwnerSearch $search): JsonResponse
    {
        $user = $request->user();

        return response()->json($search->run(
            $request->validated('q'),
            rooms: $user->can('viewAny', Room::class),
            residents: $user->can('viewAny', Resident::class),
        ));
    }
}
