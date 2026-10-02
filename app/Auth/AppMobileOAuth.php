<?php

declare(strict_types=1);

namespace App\Auth;

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

/**
 * Client OAuth do app das lojas (repo wagnerra23/oimpresso-app, Capacitor).
 *
 * Decisão [W] 2026-10-01: o app tem telas próprias e fala com o ERP por API com token
 * Passport. O login é por password grant (já ligado no ERP para o desktop Delphi — ADR 0019)
 * com client PÚBLICO: sem secret, porque um app instalado no celular não guarda segredo.
 *
 * O /oauth/token não passa pelo LoginController, então as travas do login web (empresa
 * inativa, usuário inativo, `allow_login`, cliente sem CRM) não se aplicavam ao token.
 * `bloqueio()` repete essas travas para o client do app — só para ele, para não mudar o
 * comportamento dos clients do desktop (39, 107).
 */
final class AppMobileOAuth
{
    /** Nome fixo do client; é por ele que o comando e a trava o reconhecem. */
    public const CLIENT_NAME = 'oimpresso-app (lojas)';

    /** O client_id informado é o client do app? */
    public static function ehClienteDoApp(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        return DB::table('oauth_clients')
            ->where('id', $clientId)
            ->where('name', self::CLIENT_NAME)
            ->exists();
    }

    /**
     * Motivo pelo qual este usuário NÃO pode entrar pelo app, ou null se pode.
     * Espelha LoginController::authenticated (login web).
     */
    public static function bloqueio(User $user): ?string
    {
        $business = Business::query()->whereKey($user->business_id)->first();

        if (! $business || ! $business->is_active) {
            return 'business_inactive';
        }
        if ($user->status !== 'active') {
            return 'user_inactive';
        }
        if (! $user->allow_login) {
            return 'login_not_allowed';
        }
        if ($user->user_type === 'user_customer') {
            return 'customer_user';
        }

        return null;
    }
}
