<?php

namespace App\Http\Controllers\Auth;

use App\Business;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Auth;
use DB;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * Login social via Socialite (Google + Microsoft).
 *
 * Wagner: pra ativar:
 *   1. composer require laravel/socialite socialiteproviders/microsoft
 *   2. configurar GOOGLE_CLIENT_ID/SECRET/REDIRECT e MICROSOFT_*
 *   3. registrar SocialiteProviders\Manager\ServiceProvider e listener
 *      pro Microsoft (config/app.php + EventServiceProvider) — opcional
 *
 * Ver PR3 (claude/cms-pr3-auth-social).
 */
class SocialAuthController extends Controller
{
    protected BusinessUtil $businessUtil;

    protected ModuleUtil $moduleUtil;

    /** Providers OAuth aceitos por essa rota. */
    protected const PROVIDERS = ['google', 'microsoft'];

    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->middleware('guest')->except('logout');
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Redireciona pro provider OAuth.
     */
    public function redirect(string $provider)
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        if (empty(config("services.$provider.client_id"))) {
            return redirect('/login')->with('status', [
                'success' => 0,
                'msg' => "Configure {$this->envHint($provider)} no .env antes de usar login com ".ucfirst($provider).'.',
            ]);
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Throwable $e) {
            \Log::warning('Socialite redirect failed for '.$provider.': '.$e->getMessage());

            return redirect('/login')->with('status', [
                'success' => 0,
                'msg' => 'Não foi possível iniciar o login com '.ucfirst($provider).'.',
            ]);
        }
    }

    /**
     * Callback do provider OAuth.
     * - Se user existe pelo email → vincula {provider}_id e loga.
     * - Se não existe → cria User + Business básico (BR/SP/BRL) + role admin → loga.
     */
    public function callback(string $provider)
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            \Log::warning('Socialite callback failed: '.$e->getMessage());

            return redirect('/login')->with('status', [
                'success' => 0,
                'msg' => 'Falha ao autenticar com '.ucfirst($provider).'. Tente novamente.',
            ]);
        }

        $email = $socialUser->getEmail();
        if (empty($email)) {
            return redirect('/login')->with('status', [
                'success' => 0,
                'msg' => 'Não recebemos seu email do '.ucfirst($provider).'. Use email/senha ou autorize o acesso ao email.',
            ]);
        }

        $providerIdColumn = $provider.'_id';

        $user = User::where('email', $email)->first();

        if ($user) {
            // Vincular conta social a usuário existente
            if (empty($user->{$providerIdColumn})) {
                $user->{$providerIdColumn} = $socialUser->getId();
            }
            if (empty($user->avatar_url) && method_exists($socialUser, 'getAvatar')) {
                $user->avatar_url = $socialUser->getAvatar();
            }
            $user->save();
        } else {
            $user = $this->createUserAndBusiness($provider, $socialUser);
        }

        if (! $user || ! $user->business_id) {
            return redirect('/login')->with('status', [
                'success' => 0,
                'msg' => 'Não foi possível concluir o cadastro. Fale com o suporte.',
            ]);
        }

        Auth::login($user, true);

        $this->businessUtil->activityLog($user, 'login', null, ['via' => $provider], false, $user->business_id);

        return redirect('/home');
    }

    /**
     * Cria User + Business mínimo (idêntico ao fluxo /business/register, mas auto).
     */
    protected function createUserAndBusiness(string $provider, $socialUser): ?User
    {
        return DB::transaction(function () use ($provider, $socialUser) {
            $email = $socialUser->getEmail();
            $name = trim($socialUser->getName() ?: $email);
            $parts = preg_split('/\s+/', $name, 2);
            $firstName = $parts[0] ?? $email;
            $lastName = $parts[1] ?? null;

            $username = $this->generateUniqueUsername($email);

            $user = User::create([
                'surname' => null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'email' => $email,
                'password' => bcrypt(str()->random(40)),
                'language' => 'pt-br',
                $provider.'_id' => $socialUser->getId(),
                'avatar_url' => method_exists($socialUser, 'getAvatar') ? $socialUser->getAvatar() : null,
            ]);

            $brl = DB::table('currencies')->where('code', 'BRL')->value('id') ?? 1;

            $businessDetails = [
                'name' => $email,
                'currency_id' => $brl,
                'time_zone' => 'America/Sao_Paulo',
                'fy_start_month' => 1,
                'accounting_method' => 'fifo',
                'owner_id' => $user->id,
                'enabled_modules' => ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'],
            ];

            $business = $this->businessUtil->createNewBusiness($businessDetails);

            $user->business_id = $business->id;
            $user->save();

            $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);

            $this->businessUtil->addLocation($business->id, [
                'name' => $email,
                'country' => 'Brasil',
                'state' => 'SP',
                'city' => 'São Paulo',
                'zip_code' => '00000-000',
                'landmark' => '-',
            ]);

            // Atribui role Admin#{business_id} (padrão UltimatePOS)
            $roleName = 'Admin#'.$business->id;
            $user->assignRole($roleName);

            return $user;
        });
    }

    protected function generateUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower(strstr($email, '@', true)));
        if (strlen($base) < 4) {
            $base = $base.'user';
        }
        $candidate = $base;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }

    protected function envHint(string $provider): string
    {
        return strtoupper($provider).'_CLIENT_ID';
    }
}
