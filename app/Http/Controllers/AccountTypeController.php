<?php

namespace App\Http\Controllers;

use App\AccountType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tipos e subtipos de conta (`account_types`). A tela é `Financeiro/ContasBancarias` (painel "Tipos de conta") desde
 * 2026-10-27 — decisão [W], opção A; os modais Blade de criar/editar saíram com a lista antiga de contas.
 *
 * Tier 0 (ADR 0093): o tipo pai precisa ser da mesma empresa — antes o `parent_account_type_id` entrava sem checagem.
 */
class AccountTypeController extends Controller
{
    public function store(Request $request)
    {
        if (! auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $input = $this->validar($request, $business_id);
        $input['business_id'] = $business_id;

        AccountType::create($input);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('lang_v1.added_success')]);
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $account_type = AccountType::where('business_id', $business_id)->findOrFail($id);
        $input = $this->validar($request, $business_id, (int) $account_type->id);

        //Account type is changed to subtype update all its sub type's parent type
        if (empty($account_type->parent_account_type_id) && ! empty($input['parent_account_type_id'])) {
            AccountType::where('business_id', $business_id)
                ->where('parent_account_type_id', $account_type->id)
                ->update(['parent_account_type_id' => $input['parent_account_type_id']]);
        }

        $account_type->update($input);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('lang_v1.updated_success')]);
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');

        AccountType::where('business_id', $business_id)
            ->where('id', $id)
            ->delete();

        //Upadete parent account if set
        AccountType::where('business_id', $business_id)
            ->where('parent_account_type_id', $id)
            ->update(['parent_account_type_id' => null]);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('lang_v1.deleted_success')]);
    }

    /** Nome obrigatório; pai opcional, só tipo raiz da mesma empresa e nunca ele mesmo. */
    private function validar(Request $request, int $business_id, ?int $proprio = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'parent_account_type_id' => [
                'nullable', 'integer',
                Rule::exists('account_types', 'id')
                    ->where('business_id', $business_id)
                    ->whereNull('parent_account_type_id'),
                Rule::notIn(array_filter([$proprio])),
            ],
        ]);
    }
}
