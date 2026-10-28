<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Contact;
use App\Media;
use App\Services\FeatureFlagService;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Crm\Services\PortalContatoMenu;
use App\Support\Privacy\PiiRedactor;

class ManageProfileController extends Controller
{
    /**
     * Flag do Perfil do portal do contato em React (thread crm/playbook/19, F3). Convenção
     * `useV2<Modulo><Tela>`: sem a chave no GrowthBook cai no fallbackDefaults — default OFF, a Blade
     * segue servindo. Ligar é toggle (flag:set --biz=N), não deploy.
     *
     * @see memory/requisitos/Crm/RUNBOOK-portal-perfil.md
     */
    private const FLAG_V2 = 'useV2CrmPortalPerfil';

    /**
     * O que o contato pode mudar no próprio cadastro pelo portal (charter do portal: pessoa de contato,
     * telefone, e-mail, endereço). Nome, CNPJ/CPF, tipo e as chaves ficam de fora — mudam só pelo
     * balcão (UC-CRMPO-04). Coluna → regra.
     */
    private const CAMPOS_DO_CONTATO = [
        'contato' => 'nullable|string|max:100',
        'mobile' => 'required|string|max:191',
        'email' => 'nullable|email|max:191',
        'address_line_1' => 'nullable|string|max:191',
    ];

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param CommonUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Shows profile of logged in user
     *
     * @return \Illuminate\View\View|\Inertia\Response
     */
    public function getProfile()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $user_id = request()->session()->get('user.id');
        $user = User::where('id', $user_id)->with(['media'])->first();
        $config_languages = config('constants.langs');
        $languages = [];
        foreach ($config_languages as $key => $value) {
            $languages[$key] = $value['full_name'];
        }

        // `?classico=1` mantém a Blade alcançável com a flag ligada, até o cutover.
        if (! request()->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => (int) $business_id])) {
            $contato = $this->contatoDoUsuario();
            $modulos = ! empty(session('business.enabled_modules')) ? session('business.enabled_modules') : [];

            return Inertia::render('Crm/Portal/Perfil/Index', [
                'contato' => [
                    'nome' => (string) $contato->name,
                    'documento' => (string) ($contato->tax_number ?? ''),
                    'contato' => (string) ($contato->contato ?? ''),
                    'mobile' => (string) ($contato->mobile ?? ''),
                    'email' => (string) ($contato->email ?? ''),
                    'address_line_1' => (string) ($contato->address_line_1 ?? ''),
                ],
                // A casca do contato (D6) substitui a prop `shell` inteira.
                'shell' => ['portal' => app(PortalContatoMenu::class)->casca($contato, $modulos)],
            ]);
        }

        return view('crm::profile.edit', compact('user', 'languages'));
    }

    /** O contato do usuário logado, NESTE negócio (Tier 0: business_id da sessão + crm_contact_id). */
    private function contatoDoUsuario(): Contact
    {
        return Contact::where('business_id', request()->session()->get('user.business_id'))
            ->findOrFail(auth()->user()->crm_contact_id);
    }

    /**
     * updates user profile
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateProfile(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // A tela React do portal (thread 19) edita o CADASTRO do contato; a Blade segue editando o usuário.
        if ($request->header('X-Inertia')) {
            $dados = $request->validate(self::CAMPOS_DO_CONTATO);
            $this->contatoDoUsuario()->update(array_intersect_key($dados, self::CAMPOS_DO_CONTATO));

            return redirect()->back()->with(['status' => ['success' => 1, 'msg' => __('lang_v1.profile_updated_successfully')]]);
        }

        try {
            $user_id = $request->session()->get('user.id');
            $input = $request->only(['surname', 'first_name', 'last_name', 'email', 'language']);

            $user = User::find($user_id);
            $user->update($input);

            Media::uploadMedia($user->business_id, $user, request(), 'profile_photo', true);

            //update session
            $input['id'] = $user_id;
            $input['business_id'] = $business_id;
            session()->put('user', $input);

            $output = ['success' => 1,
                'msg' => __('lang_v1.profile_updated_successfully'),
            ];
        } catch (\Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (profile toca dados pessoais).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Update the password
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePassword(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // Tela React (thread 19): a recusa é erro de validação no campo, e só a senha do usuário logado
        // muda — `user_id` no corpo não é lido (UC-CRMPO-05). A Blade segue com o fluxo de baixo.
        if ($request->header('X-Inertia')) {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string',
                'confirm_password' => 'required|same:new_password',
            ]);
            $user = auth()->user();
            if (! Hash::check($request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => __('lang_v1.u_have_entered_wrong_password')]);
            }
            $user->password = Hash::make($request->input('new_password'));
            $user->save();

            return redirect()->back()->with(['status' => ['success' => 1, 'msg' => __('lang_v1.password_updated_successfully')]]);
        }

        try {
            $user_id = $request->session()->get('user.id');
            $user = User::where('id', $user_id)->first();

            if (Hash::check($request->input('current_password'), $user->password)) {
                $user->password = Hash::make($request->input('new_password'));
                $user->save();
                $output = ['success' => 1,
                    'msg' => __('lang_v1.password_updated_successfully'),
                ];
            } else {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.u_have_entered_wrong_password'),
                ];
            }
        } catch (\Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (profile toca dados pessoais).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
