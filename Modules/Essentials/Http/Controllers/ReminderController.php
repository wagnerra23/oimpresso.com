<?php

namespace Modules\Essentials\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\Reminder;

/**
 * ReminderController — versão Inertia (lista simples + form inline).
 *
 * Lembretes são POR USUÁRIO (cada um vê só os seus). O Blade original
 * renderiza num calendário fullcalendar; a versão React troca por uma
 * listagem ordenada cronologicamente que é mais prática para uso diário
 * e padrão com as outras telas migradas. A estrutura de dados do modelo
 * é preservada integralmente.
 *
 * Paridade com Blade:
 *   - CRUD de lembretes (próprios)
 *   - Campos: name, date, time, end_time, repeat
 *   - Repeat: one_time, every_day, every_week, every_month
 *   - Scope por business_id + user_id
 */
class ReminderController extends Controller
{
    protected Util $commonUtil;

    protected ModuleUtil $moduleUtil;

    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $userId = auth()->user()->id;

        $reminders = Reminder::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get()
            ->map(fn (Reminder $r) => $this->toShape($r))
            ->values();

        return Inertia::render('Essentials/Reminders/Index', [
            'reminders' => $reminders,
            'repeats'   => $this->repeatOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'date'     => 'required',
            'time'     => 'required|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'repeat'   => 'required|in:one_time,every_day,every_week,every_month',
        ]);

        Reminder::create([
            'business_id' => $businessId,
            'user_id'     => auth()->user()->id,
            'name'        => $validated['name'],
            'date'        => Carbon::parse($validated['date'])->format('Y-m-d'),
            'time'        => $this->normalizeTime($validated['time']),
            'end_time'    => ! empty($validated['end_time']) ? $this->normalizeTime($validated['end_time']) : null,
            'repeat'      => $validated['repeat'],
        ]);

        return back()->with('success', __('lang_v1.success'));
    }

    public function show($id)
    {
        // Mantém endpoint para compatibilidade com links antigos (calendário legado).
        // A página React usa o payload do index(), então aqui só redirecionamos.
        return redirect('/essentials/reminder');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'date'     => 'required',
            'time'     => 'required|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'repeat'   => 'required|in:one_time,every_day,every_week,every_month',
        ]);

        Reminder::where('business_id', $businessId)
            ->where('user_id', auth()->user()->id)
            ->where('id', $id)
            ->update([
                'name'     => $validated['name'],
                'date'     => Carbon::parse($validated['date'])->format('Y-m-d'),
                'time'     => $this->normalizeTime($validated['time']),
                'end_time' => ! empty($validated['end_time']) ? $this->normalizeTime($validated['end_time']) : null,
                'repeat'   => $validated['repeat'],
            ]);

        return back()->with('success', __('lang_v1.updated_success'));
    }

    public function destroy($id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        Reminder::where('business_id', $businessId)
            ->where('user_id', auth()->user()->id)
            ->where('id', $id)
            ->delete();

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    // ------------------------------------------------------------------------

    protected function toShape(Reminder $r): array
    {
        return [
            'id'       => $r->id,
            'name'     => $r->name,
            'date'     => $r->date,
            'time'     => $r->time ? substr($r->time, 0, 5) : null,
            'end_time' => $r->end_time ? substr($r->end_time, 0, 5) : null,
            'repeat'   => $r->repeat,
        ];
    }

    protected function repeatOptions(): array
    {
        return [
            ['value' => 'one_time',    'label' => 'Uma vez'],
            ['value' => 'every_day',   'label' => 'Todo dia'],
            ['value' => 'every_week',  'label' => 'Toda semana'],
            ['value' => 'every_month', 'label' => 'Todo mês'],
        ];
    }

    protected function normalizeTime(?string $time): ?string
    {
        if (empty($time)) return null;
        // Garante HH:MM:SS
        if (strlen($time) === 5) return $time . ':00';
        return $time;
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
}
