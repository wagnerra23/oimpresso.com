<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Passport v11+ desabilitou password grant por padrao. Desktop Delphi
        // legado usa `grant_type=password` — reabilitar pra preservar compat.
        // Ver ADR 0019.
        Passport::enablePasswordGrant();

        // Passport 13 nasce com `$clientUuids = true`: o model Client gera UUID no
        // `id`. O schema legado de `oauth_clients.id` é INT auto-increment e o
        // sql_mode de prod não é estrito, então o MySQL trunca o UUID nos dígitos
        // iniciais ("01a0f5fe-…" vira 1) e o insert colide com o client 1 — todo
        // `store` dos painéis /connector/client e /officeimpresso/client falhava.
        // Desligar só muda o id de clients NOVOS (volta ao auto-increment); os
        // existentes (39, 107… — o Delphi e o User::validateForPassportPasswordGrant
        // dependem desses ints) e o hash do secret não mudam.
        Passport::$clientUuids = false;

        Gate::before(function ($user, $ability) {
            if (in_array($ability, ['backup', 'superadmin',
                'manage_modules', ])) {
                $administrator_list = config('constants.administrator_usernames');

                if (in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
                    return true;
                }
            } elseif (in_array($ability, self::permissoesDePlataforma(), true)) {
                // D-GATE (ADR 0415): permissão de PLATAFORMA abre dado de todas as
                // empresas, então o papel `Admin#{business_id}` não basta. Passa quem
                // está em administrator_usernames; o resto cai na checagem normal do
                // Spatie — só entra quem tem a permissão de verdade.
                $administrator_list = config('constants.administrator_usernames');

                if (in_array(strtolower($user->username), explode(',', strtolower((string) $administrator_list)))) {
                    return true;
                }
            } else {
                if ($user->hasRole('Admin#'.$user->business_id)) {
                    return true;
                }
            }
        });
    }

    /**
     * Permissões de plataforma — as que o dono de uma empresa NÃO herda pelo papel
     * `Admin#{business_id}` (ADR 0415).
     *
     * Fonte única: os scopes `admin_only` do catálogo MCP (os mesmos que o
     * `DataController@mcpScopePermissions` da Jana já tira do editor de papéis,
     * #6962) + `jana.superadmin`. Não é lista escrita à mão: marcar um scope novo
     * como `admin_only` no catálogo já o tira do bypass.
     *
     * @return array<int, string>
     */
    public static function permissoesDePlataforma(): array
    {
        static $lista = null;

        if ($lista === null) {
            $adminOnly = [];
            $catalogo = \Modules\Jana\Database\Seeders\McpScopesSeeder::class;
            if (class_exists($catalogo)) {
                foreach ($catalogo::catalogo() as $scope) {
                    if (($scope['admin_only'] ?? false) === true) {
                        $adminOnly[] = $scope['slug'];
                    }
                }
            }
            $lista = array_values(array_unique(array_merge($adminOnly, ['jana.superadmin'])));
        }

        return $lista;
    }
}
