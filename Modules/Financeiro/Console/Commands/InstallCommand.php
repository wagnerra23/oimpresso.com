<?php

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Database\Seeders\PlanoContasBrSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Instala o módulo Financeiro num business.
 *
 * Faz:
 *  1. Cria/garante 13 permissões Spatie 'financeiro.*'
 *  2. Atribui todas as permissões ao role Admin#{business_id}
 *  3. Ativa financeiro_module nos packages com subscription ativa
 *  4. Seedpa plano de contas BR (49 entries hierárquicas)
 *  5. Limpa cache de permissões
 *
 * Uso:
 *   php artisan financeiro:install --business=4              (ROTA LIVRE)
 *   php artisan financeiro:install --business=4 --no-seed    (skip plano de contas)
 *   php artisan financeiro:install --all                     (todos os businesses ativos)
 */
class InstallCommand extends Command
{
    protected $signature = 'financeiro:install
        {--business= : ID do business (1-N)}
        {--all : Instala em TODOS os businesses com subscription ativa}
        {--no-seed : Não seedar plano de contas BR}';

    protected $description = 'Instala o módulo Financeiro em um (ou todos os) businesses';

    /** Lista canônica das 13 permissões do módulo */
    private array $perms = [
        'financeiro.access',
        'financeiro.dashboard.view',
        'financeiro.contas_receber.view',
        'financeiro.contas_receber.create',
        'financeiro.contas_receber.baixar',
        'financeiro.contas_pagar.view',
        'financeiro.contas_pagar.create',
        'financeiro.contas_pagar.pagar',
        'financeiro.caixa.view',
        'financeiro.contas_bancarias.manage',
        'financeiro.conciliacao.manage',
        'financeiro.relatorios.view',
        'financeiro.relatorios.share',
    ];

    public function handle(): int
    {
        $this->ensurePermissionsExist();

        $businessIds = $this->resolveBusinessIds();

        if (empty($businessIds)) {
            $this->error('Nenhum business pra instalar. Passe --business=ID ou --all.');

            return self::FAILURE;
        }

        foreach ($businessIds as $businessId) {
            $this->installForBusiness((int) $businessId);
        }

        $this->flushCache();

        $this->info("\n✅ Instalação concluída em " . count($businessIds) . ' business(es).');
        $this->line('   Acesse /financeiro no navegador (logout/login pode ser necessário).');

        return self::SUCCESS;
    }

    private function ensurePermissionsExist(): void
    {
        $criadas = 0;
        foreach ($this->perms as $name) {
            $p = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($p->wasRecentlyCreated) {
                $criadas++;
            }
        }
        $this->line("[perms] {$criadas} permissões criadas / " . (count($this->perms) - $criadas) . ' já existentes');
    }

    private function resolveBusinessIds(): array
    {
        if ($this->option('business')) {
            return [(int) $this->option('business')];
        }

        if ($this->option('all')) {
            return DB::table('subscriptions')
                ->where('status', 'approved')
                ->where('end_date', '>=', now())
                ->distinct()
                ->pluck('business_id')
                ->all();
        }

        return [];
    }

    private function installForBusiness(int $businessId): void
    {
        $this->newLine();
        $this->info("=== Business #$businessId ===");

        // 1. Atribui ao role Admin#{business_id}
        $role = Role::where('name', "Admin#$businessId")->first();
        if (! $role) {
            $this->warn("Role 'Admin#$businessId' não existe — criando.");
            $role = Role::create([
                'name' => "Admin#$businessId",
                'guard_name' => 'web',
                'business_id' => $businessId,
                'is_default' => 0,
            ]);
        }

        $atribuidas = 0;
        foreach ($this->perms as $name) {
            if (! $role->hasPermissionTo($name)) {
                $role->givePermissionTo($name);
                $atribuidas++;
            }
        }
        $this->line("[role]  Admin#$businessId: $atribuidas permissões atribuídas / " . (count($this->perms) - $atribuidas) . ' já tinha');

        // 2. Ativa financeiro_module nos packages com subscription ativa
        $pkgIds = DB::table('subscriptions')
            ->where('business_id', $businessId)
            ->where('status', 'approved')
            ->where('end_date', '>=', now())
            ->distinct()
            ->pluck('package_id')
            ->all();

        if (empty($pkgIds)) {
            $this->warn("[pkg]   Nenhuma subscription ativa pro business $businessId — módulo ficará invisível pra usuários comuns.");
        } else {
            foreach ($pkgIds as $pkgId) {
                $pkg = DB::table('packages')->where('id', $pkgId)->first();
                if (! $pkg) {
                    continue;
                }

                $custom = json_decode($pkg->custom_permissions ?? '{}', true) ?: [];
                if (($custom['financeiro_module'] ?? false) !== true) {
                    $custom['financeiro_module'] = true;
                    DB::table('packages')->where('id', $pkgId)->update([
                        'custom_permissions' => json_encode($custom),
                    ]);
                    $this->line("[pkg]   pkg #$pkgId ({$pkg->name}): financeiro_module=true ✓");
                } else {
                    $this->line("[pkg]   pkg #$pkgId ({$pkg->name}): já estava ativo");
                }
            }
        }

        // 3. Seed plano de contas BR
        if (! $this->option('no-seed')) {
            $existing = DB::table('fin_planos_conta')->where('business_id', $businessId)->count();
            if ($existing > 0) {
                $this->line("[seed]  Plano de contas já tem $existing entries — skip.");
            } else {
                (new PlanoContasBrSeeder())->run($businessId);
                $count = DB::table('fin_planos_conta')->where('business_id', $businessId)->count();
                $this->line("[seed]  Plano de contas BR seedado ($count entries)");
            }
        }
    }

    private function flushCache(): void
    {
        app()['cache']->forget('spatie.permission.cache');
        $this->line("\n[cache] Spatie permission cache limpo.");
    }
}
