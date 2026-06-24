<?php

namespace App\Http\Controllers;

use App\Media;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UserController
    |--------------------------------------------------------------------------
    |
    | This controller handles the manipualtion of user
    |
    */

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Shows profile of logged in user
     *
     * @return \Illuminate\Http\Response
     */
    public function getProfile()
    {
        $user_id = request()->session()->get('user.id');
        $user = User::where('id', $user_id)->with(['media'])->first();
        $config_languages = config('constants.langs');
        $languages = [];
        foreach ($config_languages as $key => $value) {
            $languages[$key] = $value['full_name'];
        }

        return view('user.profile', compact('user', 'languages'));
    }

    /**
     * updates user profile
     *
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $user_id = $request->session()->get('user.id');
            $input = $request->only(['surname', 'first_name', 'last_name', 'email', 'language', 'marital_status',
                'blood_group', 'contact_number', 'fb_link', 'twitter_link', 'social_media_1',
                'social_media_2', 'permanent_address', 'current_address',
                'guardian_name', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'id_proof_name', 'id_proof_number', 'gender', 'family_number', 'alt_number', ]);

            if (! empty($request->input('dob'))) {
                $input['dob'] = $this->moduleUtil->uf_date($request->input('dob'));
            }
            if (! empty($request->input('bank_details'))) {
                $input['bank_details'] = json_encode($request->input('bank_details'));
            }

            $user = User::find($user_id);
            $user->update($input);

            Media::uploadMedia($user->business_id, $user, request(), 'profile_photo', true);

            //update session
            $input['id'] = $user_id;
            $business_id = request()->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            session()->put('user', $input);

            $output = ['success' => 1,
                'msg' => __('lang_v1.profile_updated_successfully'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('user/profile')->with('status', $output);
    }

    /**
     * updates user password
     *
     * @return \Illuminate\Http\Response
     */
    public function updatePassword(Request $request)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
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
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('user/profile')->with('status', $output);
    }

    /**
     * Tela "Meu perfil" em Inertia (rota nova /perfil — redesign ComVis).
     * O legado /user/profile (Blade) segue INTACTO. Tier 0: só o usuário logado.
     */
    public function perfil()
    {
        $user_id = request()->session()->get('user.id');
        $user = User::where('id', $user_id)->with(['media'])->firstOrFail();

        $config_languages = config('constants.langs');
        $languages = [];
        foreach ((array) $config_languages as $key => $value) {
            $languages[$key] = is_array($value) ? ($value['full_name'] ?? $key) : $key;
        }

        $bankRaw = $user->bank_details;
        $bankDecoded = is_string($bankRaw) && $bankRaw !== '' ? json_decode($bankRaw, true) : [];
        $bank = is_array($bankDecoded) ? $bankDecoded : [];

        $media = $user->media;
        $photo_url = $media ? $media->display_url : null;

        // Labels dos campos customizáveis por empresa (espelha user/form.blade.php)
        $business = \App\Business::findOrFail($user->business_id);
        $labelsRaw = $business->getAttribute('custom_labels');
        $labelsDecoded = is_string($labelsRaw) && $labelsRaw !== '' ? json_decode($labelsRaw, true) : [];
        $labels = is_array($labelsDecoded) ? $labelsDecoded : [];
        $userLabels = isset($labels['user']) && is_array($labels['user']) ? $labels['user'] : [];
        $custom_field_labels = [
            'custom_field_1' => $userLabels['custom_field_1'] ?? __('lang_v1.user_custom_field1'),
            'custom_field_2' => $userLabels['custom_field_2'] ?? __('lang_v1.user_custom_field2'),
            'custom_field_3' => $userLabels['custom_field_3'] ?? __('lang_v1.user_custom_field3'),
            'custom_field_4' => $userLabels['custom_field_4'] ?? __('lang_v1.user_custom_field4'),
        ];

        return Inertia::render('User/Perfil', [
            'usuario' => [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'language' => $user->language,
                'dob' => ! empty($user->dob) ? Carbon::parse($user->dob)->format('Y-m-d') : '',
                'gender' => $user->gender,
                'marital_status' => $user->marital_status,
                'blood_group' => $user->blood_group,
                'contact_number' => $user->contact_number,
                'alt_number' => $user->alt_number,
                'family_number' => $user->family_number,
                'fb_link' => $user->fb_link,
                'twitter_link' => $user->twitter_link,
                'social_media_1' => $user->social_media_1,
                'social_media_2' => $user->social_media_2,
                'guardian_name' => $user->guardian_name,
                'id_proof_name' => $user->id_proof_name,
                'id_proof_number' => $user->id_proof_number,
                'permanent_address' => $user->permanent_address,
                'current_address' => $user->current_address,
                'custom_field_1' => $user->custom_field_1,
                'custom_field_2' => $user->custom_field_2,
                'custom_field_3' => $user->custom_field_3,
                'custom_field_4' => $user->custom_field_4,
                'bank_details' => [
                    'account_holder_name' => $bank['account_holder_name'] ?? '',
                    'account_number' => $bank['account_number'] ?? '',
                    'bank_name' => $bank['bank_name'] ?? '',
                    'bank_code' => $bank['bank_code'] ?? '',
                    'branch' => $bank['branch'] ?? '',
                    'tax_payer_id' => $bank['tax_payer_id'] ?? '',
                ],
                'photo_url' => $photo_url,
            ],
            'languages' => $languages,
            'custom_field_labels' => $custom_field_labels,
        ]);
    }

    /**
     * Update do perfil pela tela Inertia. Espelha updateProfile() mas redireciona
     * de volta pra /perfil (Inertia). Tier 0: só o usuário logado.
     */
    public function perfilUpdate(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $request->validate([
            'first_name' => 'required|max:255',
            'email' => 'required|email|max:255',
        ]);

        try {
            $user_id = $request->session()->get('user.id');
            $input = $request->only(['surname', 'first_name', 'last_name', 'email', 'language',
                'marital_status', 'blood_group', 'contact_number', 'fb_link', 'twitter_link',
                'social_media_1', 'social_media_2', 'permanent_address', 'current_address',
                'guardian_name', 'id_proof_name', 'id_proof_number', 'gender', 'family_number', 'alt_number',
                'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', ]);

            if (! empty($request->input('dob'))) {
                // input type=date já chega em Y-m-d (sem uf_date — que espera formato do negócio)
                $input['dob'] = $request->input('dob');
            }
            if (! empty($request->input('bank_details'))) {
                $input['bank_details'] = json_encode($request->input('bank_details'));
            }

            $user = User::where('id', $user_id)->firstOrFail();
            $user->update($input);

            Media::uploadMedia($user->business_id, $user, $request, 'profile_photo', true);

            // refresh do nome de exibição na sessão (sem clobber do blob inteiro)
            session()->put('user.surname', $user->surname);
            session()->put('user.first_name', $user->first_name);
            session()->put('user.last_name', $user->last_name);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return back()->withErrors(['geral' => __('messages.something_went_wrong')]);
        }

        return back()->with('status', __('lang_v1.profile_updated_successfully'));
    }

    /**
     * Troca de senha pela tela Inertia. Espelha updatePassword().
     */
    public function perfilPassword(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user_id = $request->session()->get('user.id');
        $user = User::where('id', $user_id)->firstOrFail();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('lang_v1.u_have_entered_wrong_password'),
            ]);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return back()->with('status', __('lang_v1.password_updated_successfully'));
    }
}
