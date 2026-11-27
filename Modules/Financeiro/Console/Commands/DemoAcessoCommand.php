<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dá (ou revoga) as permissões financeiro.* DIRETO a um usuário, sem mexer no papel
 * dele. Serve para liberar o módulo a quem vai testar a massa [TESTE] sem abrir o
 * Financeiro para todos os usuários do mesmo papel.
 *
 * [W] 2026-11-27: "Sim, só o wr23" (a [M] testa com o usuário wr23 da empresa 1).
 *
 * Tier 0: o usuário tem de ser da empresa informada. Dry-run por padrão.
 *
 *   php artisan financeiro:demo-acesso --business=1 --username=wr23            # mostra
 *   php artisan financeiro:demo-acesso --business=1 --username=wr23 --force    # aplica
 *   php artisan financeiro:demo-acesso --business=1 --username=wr23 --revogar --force
 */
class DemoAcessoCommand extends Command
{
    protected $signature = 'financeiro:demo-acesso
                            {--business= : empresa do usuário (obrigatório)}
                            {--username= : login do usuário (obrigatório)}
                            {--revogar : remove as permissões diretas em vez de dar}
                            {--force : aplica (o padrão é dry-run)}';

    protected $description = 'Dá ou revoga as permissões financeiro.* direto a um usuário (sem mexer no papel). Dry-run por padrão.';

    public function handle(): int
    {
        $biz = (int) $this->option('business');
        $username = trim((string) $this->option('username'));
        if ($biz <= 0 || $username === '') {
            $this->error('Informe --business=<id> e --username=<login>.');

            return self::INVALID;
        }

        $user = User::where('business_id', $biz)->whereRaw('LOWER(username) = ?', [strtolower($username)])->first();
        if (! $user) {
            $this->error("Usuário {$username} não encontrado na empresa {$biz}.");

            return self::FAILURE;
        }

        $todas = Permission::where('name', 'LIKE', 'financeiro.%')->where('guard_name', 'web')->pluck('name');
        $diretas = $user->getDirectPermissions()->pluck('name');
        $alvo = $this->option('revogar')
            ? $diretas->filter(fn ($p) => str_starts_with($p, 'financeiro.'))->values()
            : $todas->diff($diretas)->values();

        $acao = $this->option('revogar') ? 'revogar' : 'dar';
        $this->info("financeiro:demo-acesso — empresa {$biz} — usuário #{$user->id} — {$acao} {$alvo->count()} permissão(ões) — "
            .($this->option('force') ? 'APLICANDO' : 'DRY-RUN'));
        foreach ($alvo as $p) {
            $this->line("  {$p}");
        }

        if (! $this->option('force')) {
            $this->warn('Dry-run. Use --force para aplicar.');

            return self::SUCCESS;
        }

        if ($this->option('revogar')) {
            foreach ($alvo as $p) {
                $user->revokePermissionTo($p);
            }
        } else {
            $user->givePermissionTo($alvo->all());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Feito. financeiro.access agora: '.($user->fresh()->can('financeiro.access') ? 'sim' : 'não'));

        return self::SUCCESS;
    }
}
