<?php

namespace Modules\Essentials\Http\Controllers;

use App\BusinessLocation;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\EssentialsHoliday;

/**
 * EssentialsHolidayController — versão Inertia.
 *
 * Feriados do business, opcionalmente escopados por localidade. Apenas admin
 * pode criar/editar/deletar. Todos podem ver (filtrado por permitted_locations).
 *
 * Paridade com Blade preservada:
 *   - CRUD (create, store, edit, update, destroy)
 *   - Filtros por location_id, start_date/end_date
 *   - Scope por business_id + permitted_locations
 *   - is_admin só pode editar/deletar
 */
class EssentialsHolidayController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $isAdmin = $this->moduleUtil->is_admin(auth()->user(), $businessId);

        $query = EssentialsHoliday::where('business_id', $businessId)
            ->with(['location:id,name']);

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all') {
            $query->where(function ($q) use ($permitted) {
                $q->whereIn('location_id', $permitted)->orWhereNull('location_id');
            });
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('start_date', '>=', $request->string('start_date'))
                ->whereDate('start_date', '<=', $request->string('end_date'));
        }

        $holidays = $query->orderByDesc('start_date')->get()->map(fn ($h) => $this->toShape($h))->values();

        $locations = collect(BusinessLocation::forDropdown($businessId))
            ->map(fn ($label, $id) => ['id' => (int) $id, 'label' => (string) $label])
            ->values()->all();

        return Inertia::render('Essentials/Holidays/Index', [
            'holidays' => $holidays,
            'locations' => $locations,
            'filtros'   => [
                'location_id' => $request->integer('location_id') ?: null,
                'start_date'  => $request->string('start_date')->toString() ?: null,
                'end_date'    => $request->string('end_date')->toString() ?: null,
            ],
            'can_manage' => $isAdmin,
        ]);
    }

    public function create()
    {
        // Inline no Index (Dialog). Redireciona para manter compat de links antigos.
        return redirect('/hrm/holiday');
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdmin($businessId);

        $data = $this->validateHoliday($request);

        EssentialsHoliday::create(array_merge($data, ['business_id' => $businessId]));

        return back()->with('success', __('lang_v1.added_success'));
    }

    public function show()
    {
        return redirect('/hrm/holiday');
    }

    public function edit($id)
    {
        return redirect('/hrm/holiday');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdmin($businessId);

        $data = $this->validateHoliday($request);

        EssentialsHoliday::where('business_id', $businessId)
            ->where('id', $id)
            ->update($data);

        return back()->with('success', __('lang_v1.updated_success'));
    }

    public function destroy($id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdmin($businessId);

        EssentialsHoliday::where('business_id', $businessId)
            ->where('id', $id)
            ->delete();

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    // ------------------------------------------------------------------------

    protected function validateHoliday(Request $request): array
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'start_date'  => 'required',
            'end_date'    => 'required',
            'location_id' => 'nullable|integer|exists:business_locations,id',
            'note'        => 'nullable|string|max:2000',
        ]);

        $validated['start_date'] = Carbon::parse($validated['start_date'])->format('Y-m-d');
        $validated['end_date']   = Carbon::parse($validated['end_date'])->format('Y-m-d');

        return $validated;
    }

    protected function toShape(EssentialsHoliday $h): array
    {
        $start = $h->start_date ? Carbon::parse($h->start_date) : null;
        $end = $h->end_date ? Carbon::parse($h->end_date) : null;

        return [
            'id'            => $h->id,
            'name'          => $h->name,
            'start_date'    => optional($start)->format('Y-m-d'),
            'end_date'      => optional($end)->format('Y-m-d'),
            'days'          => $start && $end ? $start->diffInDays($end) + 1 : 1,
            'location_id'   => $h->location_id,
            'location_name' => optional($h->location)->name,
            'note'          => $h->note,
        ];
    }

    protected function currentBusinessId(): int
    {
        return (int) (session('business.id') ?: request()->session()->get('user.business_id'));
    }

    protected function authorizeAccess(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function authorizeAdmin(int $businessId): void
    {
        $this->authorizeAccess($businessId);
        if (! $this->moduleUtil->is_admin(auth()->user(), $businessId)) {
            abort(403, 'Apenas administradores podem gerenciar feriados.');
        }
    }
}
