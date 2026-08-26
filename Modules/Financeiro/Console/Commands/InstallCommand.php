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
 *  3. Ativa financeiro_module no PACOTE e na ASSINATURA ATIVA (o gate le a assinatura)
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

        $abertos = 0;

        foreach ($businessIds as $businessId) {
            if ($this->installForBusiness((int) $businessId)) {
                $abertos++;
            }
        }

        $this->flushCache();

        // O veredito e o ESTADO, nao o fato de ter rodado. Ate 2026-08-26 este comando
        // dizia "concluida" mesmo tendo escrito so no pacote, com o gate fechado — comando
        // que reporta sucesso sem entregar e pior que comando ausente.
        $total = count($businessIds);
        $fechados = $total - $abertos;

        if ($fechados > 0) {
            $this->warn("\nGate ABERTO em $abertos de $total business(es).");
            $this->warn("  $fechados sem assinatura ativa — o modulo segue invisivel pra usuario comum neles.");
            $this->line('  Camada 3: sem `financeiro.access` num papel, o menu tambem nao aparece.');

            return self::FAILURE;
        }

        $this->info("\nGate aberto em $total business(es).");
        $this->line('   Acesse /financeiro no navegador (logout/login pode ser necessario).');

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

    private function installForBusiness(int $businessId): bool
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

        // 2b. ONDE O GATE DE FATO LE.
        //
        // `ModuleUtil::hasThePermissionInSubscription` (app/Utils/ModuleUtil.php:154) le
        // `subscriptions.package_details` — NAO le `packages.custom_permissions`. O passo 2
        // acima escreve so no PACOTE, entao ate 2026-08-26 este comando terminava dizendo
        // "concluida" com o portao FECHADO. Medido em producao: o biz=164 tinha o modulo no
        // pacote 11 e ausente nas assinaturas 111/116.
        //
        // A assinatura alvo sai do MESMO oraculo que o LEITOR usa
        // (`Subscription::active_subscription`), nunca de um predicado paralelo — escrever
        // numa assinatura que o gate nao le e exatamente o defeito que este passo conserta.
        //
        // MERGE, nunca rebuild: `$details[...] = 1` sobre o array decodificado preserva as
        // chaves que existem SO na assinatura (ex.: `nfebrasil_module` do biz=164, que
        // nenhum pacote grava).
        $gateAberto = false;

        if (! class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
            $this->warn('[sub]   Modulo Superadmin ausente — sem assinatura onde escrever.');
        } else {
            $sub = \Modules\Superadmin\Entities\Subscription::active_subscription($businessId);

            if ($sub === null) {
                $this->warn("[sub]   Sem assinatura ATIVA pro business $businessId — o gate SEGUE FECHADO.");
                $this->warn('        Atribua um pacote em /superadmin/packages e rode de novo.');
            } else {
                $details = is_array($sub->package_details)
                    ? $sub->package_details
                    : (json_decode((string) $sub->package_details, true) ?: []);

                if (! empty($details['financeiro_module'])) {
                    $this->line("[sub]   sub #{$sub->id}: ja tinha financeiro_module");
                } else {
                    $details['financeiro_module'] = 1;
                    DB::table('subscriptions')->where('id', $sub->id)->update([
                        'package_details' => json_encode($details),
                    ]);
                    $this->line("[sub]   sub #{$sub->id}: + financeiro_module (merge — nada removido)");
                }

                $gateAberto = true;
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

        return $gateAberto;
    }

    private function flushCache(): void
    {
        app()['cache']->forget('spatie.permission.cache');
        $this->line("\n[cache] Spatie permission cache limpo.");
    }
}
