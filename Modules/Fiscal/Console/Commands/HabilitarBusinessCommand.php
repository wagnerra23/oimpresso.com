<?php

declare(strict_types=1);

namespace Modules\Fiscal\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * US-FISCAL-018 — Habilitar cockpit Fiscal pra um business (piloto Larissa biz=4).
 *
 * Uso:
 *   php artisan fiscal:habilitar-business 4
 *   php artisan fiscal:habilitar-business 4 --dry-run
 *
 * Idempotente — pode rodar N vezes sem efeito colateral.
 *
 * Faz 3 coisas:
 *   1. Garante 7 permissions `fiscal.*` existem em `permissions` table (guard=web)
 *   2. Atribui as 6 permissions seguras ao role principal do business
 *      (NÃO atribui `fiscal.sped.export` enquanto GAP-FISCAL-003 não eliminar
 *      6 hardcodes Tier-0 no SpedIcmsIpiGeneratorService — vide audit sênior
 *      2026-05-25 §"Surpresa estratégica")
 *   3. Garante `package_details.fiscal_module=1` na subscription ativa do biz
 *      (Wagner regra Tier 0 2026-05-18 — habilitar SEMPRE via package, NUNCA
 *      hardcode `if (business_id === N)`)
 *
 * Espelhada via UI superadmin `/superadmin/packages/{id}/edit` — comando
 * existe pra provisionar via SSH/CI quando Wagner quiser scripted.
 *
 * @see memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-001
 * @see memory/reference/feedback-habilitar-modulo-por-business.md
 */
class HabilitarBusinessCommand extends Command
{
    protected $signature = 'fiscal:habilitar-business {businessId : ID do business (ex 4 = ROTA LIVRE Larissa)} {--dry-run : Apenas mostra o que faria, sem persistir}';

    protected $description = 'Habilita cockpit Fiscal pra um business (permissions Spatie + package subscription). Idempotente. NÃO habilita fiscal.sped.export (até GAP-FISCAL-003 eliminar hardcodes) nem fiscal.config.ambiente (conceder é ato de [W] na UI).';

    /**
     * Permissions seguras pra atribuir ao role do business piloto.
     * `fiscal.sped.export` fica de fora — feature flag `fiscal.sped.simples_only`
     * + audit sênior 2026-05-25 §"Surpresa estratégica" (6 hardcodes Tier-0).
     * `fiscal.config.ambiente` também fica de fora — ver PERMS_SOBERANIA_W.
     */
    private const PERMS_SEGURAS = [
        'fiscal.access',
        'fiscal.nfe.view',
        'fiscal.nfe.acoes',
        'fiscal.nfse.view',
        'fiscal.dfe.manage',
        'fiscal.config.edit',
    ];

    /**
     * `fiscal.sped.export` — provisionada na tabela mas NÃO atribuída ao role
     * piloto enquanto hardcodes existirem.
     */
    private const PERMS_BLOQUEADAS_ATE_GAP_003 = [
        'fiscal.sped.export',
    ];

    /**
     * `fiscal.config.ambiente` — provisionada na tabela (pra existir no
     * `/roles/{id}/edit` e pro `can()` responder false em vez de tropeçar), mas
     * NUNCA atribuída por comando. As duas ações que ela abre — trocar o ambiente
     * SEFAZ e substituir o certificado A1 — param a emissão da empresa inteira,
     * e a segunda muda o valor fiscal de toda nota emitida depois dela.
     *
     * Diferente de PERMS_BLOQUEADAS_ATE_GAP_003, isto NÃO tem data pra sair: não
     * é dívida técnica esperando conserto, é soberania — quem concede é [W], na
     * UI canônica. Um comando de deploy conceder isto sozinho seria exatamente o
     * "habilitar por código" que o `memory/proibicoes.md` §multi-tenant proíbe.
     */
    private const PERMS_SOBERANIA_W = [
        'fiscal.config.ambiente',
    ];

    public function handle(): int
    {
        $businessId = (int) $this->argument('businessId');
        $isDryRun = (bool) $this->option('dry-run');

        if ($businessId <= 0) {
            $this->error("businessId inválido: {$businessId}");
            return self::FAILURE;
        }

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            $this->error("Business {$businessId} não existe em `business` table");
            return self::FAILURE;
        }

        $this->info("Habilitando Fiscal pra biz={$businessId} ({$business->name})" . ($isDryRun ? ' [DRY-RUN]' : ''));
        $this->newLine();

        // PASSO 1 — Garantir permissions Spatie existem (idempotente)
        // Contagens DERIVADAS das constantes — o texto não restateia número que a
        // própria lista sabe melhor (o "7" fixo aqui já estava prestes a apodrecer).
        $todasPerms = array_merge(
            self::PERMS_SEGURAS,
            self::PERMS_BLOQUEADAS_ATE_GAP_003,
            self::PERMS_SOBERANIA_W,
        );
        $this->info('PASSO 1: Garantir ' . count($todasPerms) . ' permissions fiscal.* existem em `permissions`');
        foreach ($todasPerms as $permName) {
            $existe = Permission::where('name', $permName)->where('guard_name', 'web')->exists();
            if ($existe) {
                $this->line("  ✓ {$permName} já existe");
                continue;
            }
            if (! $isDryRun) {
                Permission::create(['name' => $permName, 'guard_name' => 'web']);
            }
            $this->line("  + {$permName} criada");
        }
        $this->newLine();

        // PASSO 2 — Atribuir 6 perms seguras ao role principal do business
        $this->info('PASSO 2: Atribuir ' . count(self::PERMS_SEGURAS) . ' permissions seguras ao role principal do business');
        $roleName = "Admin#{$businessId}";
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        if (! $role) {
            $this->warn("  ⚠ Role '{$roleName}' não existe — buscando alternativas em business_id={$businessId}");
            $role = Role::where('business_id', $businessId)
                ->where('guard_name', 'web')
                ->orderBy('id')
                ->first();
            if ($role) {
                $this->warn("  Usando role alternativo: {$role->name} (id={$role->id})");
            }
        }
        if (! $role) {
            $this->error("  ✗ Nenhum role encontrado pra business_id={$businessId}. Crie via /roles/create primeiro.");
            return self::FAILURE;
        }

        foreach (self::PERMS_SEGURAS as $permName) {
            if ($role->hasPermissionTo($permName)) {
                $this->line("  ✓ {$role->name} já tem {$permName}");
                continue;
            }
            if (! $isDryRun) {
                $role->givePermissionTo($permName);
            }
            $this->line("  + {$role->name} ← {$permName}");
        }
        foreach (self::PERMS_BLOQUEADAS_ATE_GAP_003 as $permName) {
            $this->warn("  ⊘ {$permName} NÃO atribuída — bloqueada até GAP-FISCAL-003 (audit sênior 2026-05-25)");
        }
        foreach (self::PERMS_SOBERANIA_W as $permName) {
            $this->warn("  ⊘ {$permName} NÃO atribuída — e não é dívida: conceder é ato de [W] em /roles/{id}/edit");
        }
        $this->newLine();

        // PASSO 3 — Garantir package_details.fiscal_module=1 na subscription ativa
        $this->info('PASSO 3: Garantir fiscal_module=1 na subscription ativa');
        $subscription = DB::table('subscriptions')
            ->where('business_id', $businessId)
            ->whereIn('status', ['approved', 'active', 'waiting'])
            ->orderByDesc('id')
            ->first();
        if (! $subscription) {
            $this->warn("  ⚠ Sem subscription ativa pra biz={$businessId} — superadmin precisa criar via UI canon");
            $this->warn('    Skipping passo 3. Re-rodar após /superadmin/packages assignment.');
        } else {
            $details = json_decode($subscription->package_details ?? '{}', true) ?: [];
            $atual = $details['fiscal_module'] ?? null;
            if ((bool) $atual === true) {
                $this->line("  ✓ subscription #{$subscription->id} já tem fiscal_module=1");
            } else {
                $details['fiscal_module'] = 1;
                if (! $isDryRun) {
                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['package_details' => json_encode($details)]);
                }
                $this->line("  + subscription #{$subscription->id} package_details.fiscal_module = 1");
            }
        }
        $this->newLine();

        $this->info('Pronto. Larissa precisa Ctrl+Shift+R no navegador pra sidebar refletir.');
        $this->line('Próximo passo manual: smoke /fiscal cockpit + /fiscal/nfe + /fiscal/dfe + /fiscal/config.');

        return self::SUCCESS;
    }
}
