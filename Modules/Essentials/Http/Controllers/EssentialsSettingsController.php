<?php

namespace Modules\Essentials\Http\Controllers;

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EssentialsSettingsController — versão Inertia.
 *
 * Configurações do módulo por business. Valores ficam em
 * `businesses.essentials_settings` (JSON). Apenas admin vê/edita.
 *
 * Campos preservados do Blade:
 *   - leave_ref_no_prefix, leave_instructions
 *   - payroll_ref_no_prefix
 *   - essentials_todos_prefix (usado em ToDoController::store)
 *   - grace_before_checkin, grace_after_checkin
 *   - grace_before_checkout, grace_after_checkout
 *   - is_location_required (bool)
 *   - calculate_sales_target_commission_without_tax (bool)
 */
class EssentialsSettingsController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function edit(): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdmin($businessId);

        $raw = request()->session()->get('business.essentials_settings');
        $settings = ! empty($raw) ? json_decode($raw, true) : [];

        return Inertia::render('Essentials/Settings/Index', [
            'settings' => [
                'leave_ref_no_prefix'                           => $settings['leave_ref_no_prefix']   ?? '',
                'leave_instructions'                            => $settings['leave_instructions']    ?? '',
                'payroll_ref_no_prefix'                         => $settings['payroll_ref_no_prefix'] ?? '',
                'essentials_todos_prefix'                       => $settings['essentials_todos_prefix'] ?? '',
                'grace_before_checkin'                          => $settings['grace_before_checkin']  ?? '',
                'grace_after_checkin'                           => $settings['grace_after_checkin']   ?? '',
                'grace_before_checkout'                         => $settings['grace_before_checkout'] ?? '',
                'grace_after_checkout'                          => $settings['grace_after_checkout']  ?? '',
                'is_location_required'                          => ! empty($settings['is_location_required']),
                'calculate_sales_target_commission_without_tax' => ! empty($settings['calculate_sales_target_commission_without_tax']),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdmin($businessId);

        $validated = $request->validate([
            'leave_ref_no_prefix'                           => 'nullable|string|max:32',
            'leave_instructions'                            => 'nullable|string|max:4000',
            'payroll_ref_no_prefix'                         => 'nullable|string|max:32',
            'essentials_todos_prefix'                       => 'nullable|string|max:32',
            'grace_before_checkin'                          => 'nullable|string|max:10',
            'grace_after_checkin'                           => 'nullable|string|max:10',
            'grace_before_checkout'                         => 'nullable|string|max:10',
            'grace_after_checkout'                          => 'nullable|string|max:10',
            'is_location_required'                          => 'boolean',
            'calculate_sales_target_commission_without_tax' => 'boolean',
        ]);

        // Normaliza flags para int (compat com código legado que lê `? 1 : 0`)
        $validated['is_location_required'] = ! empty($validated['is_location_required']) ? 1 : 0;
        $validated['calculate_sales_target_commission_without_tax'] = ! empty($validated['calculate_sales_target_commission_without_tax']) ? 1 : 0;

        $business = Business::findOrFail($businessId);
        $business->essentials_settings = json_encode($validated);
        $business->save();

        // Reflete na sessão para que ToDoController e outros leiam sem
        // precisar refetch do banco
        $request->session()->put('business', $business);

        return back()->with('success', __('lang_v1.updated_succesfully'));
    }

    // ------------------------------------------------------------------------

    protected function currentBusinessId(): int
    {
        return (int) (session('business.id') ?: request()->session()->get('user.business_id'));
    }

    protected function authorizeAdmin(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if (! $this->moduleUtil->is_admin(auth()->user(), $businessId)) {
            abort(403, 'Apenas administradores podem ver/editar as configurações do Essentials.');
        }
    }
}
