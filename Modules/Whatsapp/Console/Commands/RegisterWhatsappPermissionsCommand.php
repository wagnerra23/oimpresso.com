<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Http\Controllers\DataController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hotfix urgente — Registra permissions canônicas `whatsapp.*` na tabela `permissions`
 * (Spatie) e atribui ao role `Admin#{biz}` de cada business.
 *
 * Contexto: em prod, NENHUMA permission `whatsapp.*` existe na tabela `permissions`.
 * Sem registry, roles não conseguem atribuir e atendentes nunca tiveram acesso ao
 * Whatsapp — atalho só visível via gate `whatsapp.view-all-phones` (Admin#{biz} bypass).
 *
 * Fonte canônica das 6 permissions: `DataController::user_permissions()`.
 *
 * Uso pós-deploy (sequência recomendada):
 *   1) Preview:   php artisan whatsapp:register-permissions --business=1 --dry-run
 *   2) Smoke:     php artisan whatsapp:register-permissions --business=1 --with-backfill --dry-run
 *   3) Apply:     php artisan whatsapp:register-permissions --business=1 --with-backfill
 *   4) Rollout:   php artisan whatsapp:register-permissions --business=all --with-backfill
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 * - Permissions são GLOBAIS (sem business_id) — guard `web`
 * - Role `Admin#{biz}` lookup com `business_id` filter (UltimatePOS pattern)
 * - Business sem role Admin#{biz} → skip + warning (não cria role nova; fora de escopo)
 * - `firstOrCreate` idempotente — re-run não duplica
 * - Logs sem PII (só IDs)
 *
 * @see DataController::user_permissions() — fonte canônica
 * @see BackfillChannelAccessCommand                — encadeado via --with-backfill
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Whatsapp/SPEC.md
 */
class RegisterWhatsappPermissionsCommand extends Command
{
    protected $signature = 'whatsapp:register-permissions
                            {--business=all : business_id alvo (numeric) ou "all"}
                            {--with-backfill : Encadeia whatsapp:backfill-channel-access no fim}
                            {--dry-run : Só conta + lista, não persiste}';

    protected $description = 'Registra permissions whatsapp.* (Spatie) + atribui ao Admin#{biz} (idempotente)';

    public function handle(): int
    {
        $businessOpt = (string) $this->option('business');
        $dryRun = (bool) $this->option('dry-run');
        $withBackfill = (bool) $this->option('with-backfill');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida.');
        }

        // --- 1) Resolve permissions canônicas via DataController ---
        $permissionsDef = (new DataController())->user_permissions();
        $permissionNames = array_map(static fn ($p) => $p['value'], $permissionsDef);

        $this->line('Permissions canônicas (DataController::user_permissions):');
        foreach ($permissionsDef as $p) {
            $this->line(sprintf('  - %-32s %s', $p['value'], $p['label']));
        }
        $this->newLine();

        // --- 2) Registra cada permission via firstOrCreate (idempotente) ---
        $registered = [];
        $createdCount = 0;
        foreach ($permissionsDef as $p) {
            if ($dryRun) {
                $exists = Permission::where('name', $p['value'])
                    ->where('guard_name', 'web')
                    ->exists();
                if (! $exists) {
                    $createdCount++;
                }
                continue;
            }

            $perm = Permission::firstOrCreate([
                'name' => $p['value'],
                'guard_name' => 'web',
            ]);
            if ($perm->wasRecentlyCreated) {
                $createdCount++;
            }
            $registered[] = $perm;
        }

        $action = $dryRun ? 'WOULD register' : 'Permissions registradas';
        $this->info(sprintf(
            '%s: %d total · %d new · %d existing',
            $action,
            count($permissionsDef),
            $createdCount,
            count($permissionsDef) - $createdCount
        ));
        $this->newLine();

        // --- 3) Resolve businesses alvo ---
        $businessIds = $this->resolveBusinessIds($businessOpt);
        if ($businessIds === null) {
            return self::FAILURE;
        }
        if (empty($businessIds)) {
            $this->warn('Nenhum business encontrado.');
            return self::SUCCESS;
        }

        // --- 4) Pra cada business, atribui ao Admin#{biz} ---
        $totalAttached = 0;
        $totalAlreadyHad = 0;
        $totalSkipped = 0;

        foreach ($businessIds as $bizId) {
            $roleName = "Admin#{$bizId}";

            $role = Role::where('name', $roleName)
                ->where('business_id', $bizId)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                $this->warn(sprintf(
                    '  Business #%d: %s não encontrado — skip (criar role manual fora de escopo)',
                    $bizId,
                    $roleName
                ));
                Log::warning('[whatsapp.register_permissions.role_missing]', [
                    'business_id' => $bizId,
                    'role_name' => $roleName,
                ]);
                $totalSkipped++;
                continue;
            }

            if ($dryRun) {
                // Conta quantas já tem
                $alreadyHas = $role->permissions()
                    ->whereIn('name', $permissionNames)
                    ->count();
                $wouldAttach = count($permissionNames) - $alreadyHas;
                $this->line(sprintf(
                    '  Business #%d: %s ← WOULD attach %d (já tem %d)',
                    $bizId,
                    $roleName,
                    $wouldAttach,
                    $alreadyHas
                ));
                $totalAttached += $wouldAttach;
                $totalAlreadyHad += $alreadyHas;
                continue;
            }

            // Conta antes/depois pra diferenciar attached vs already-had
            $beforeIds = $role->permissions()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

            // givePermissionTo é idempotente (não duplica em role_has_permissions
            // por causa do PK composto). Aceita array de Permission models.
            $role->givePermissionTo($registered);

            $afterCount = $role->permissions()
                ->whereIn('name', $permissionNames)
                ->count();
            $attached = $afterCount - count($beforeIds);
            $alreadyHad = count($beforeIds);

            $this->line(sprintf(
                '  Business #%d: %s ← attached %d (já tinha %d)',
                $bizId,
                $roleName,
                $attached,
                $alreadyHad
            ));

            $totalAttached += $attached;
            $totalAlreadyHad += $alreadyHad;
        }

        // Flush cache Spatie pra próxima request enxergar (prod tem cache redis)
        if (! $dryRun) {
            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                // tolerante a env sem cache configurado (smoke local)
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d business(es) processado(s) · %d skipped · %s %d permission(s) · %d já tinha(m)',
            count($businessIds) - $totalSkipped,
            $totalSkipped,
            $dryRun ? 'WOULD attach' : 'attached',
            $totalAttached,
            $totalAlreadyHad
        ));

        Log::info('[whatsapp.register_permissions.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'permissions_total' => count($permissionsDef),
            'permissions_created' => $createdCount,
            'businesses_processed' => count($businessIds) - $totalSkipped,
            'businesses_skipped' => $totalSkipped,
            'attached_total' => $totalAttached,
        ]);

        // --- 5) Encadeia backfill se pedido ---
        if ($withBackfill) {
            $this->newLine();
            $this->info('→ Encadeando whatsapp:backfill-channel-access...');
            $params = ['--business' => $businessOpt];
            if ($dryRun) {
                $params['--dry-run'] = true;
            }
            $this->call('whatsapp:backfill-channel-access', $params);
        }

        return self::SUCCESS;
    }

    /**
     * Resolve business IDs alvo. Retorna null em caso de input inválido.
     *
     * @return array<int>|null
     */
    private function resolveBusinessIds(string $businessOpt): ?array
    {
        if ($businessOpt === 'all') {
            return Business::query()->orderBy('id')->pluck('id')->all();
        }

        $bizId = (int) $businessOpt;
        if ($bizId <= 0) {
            $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
            return null;
        }

        // Valida se business existe
        if (! Business::query()->where('id', $bizId)->exists()) {
            $this->warn("Business #{$bizId} não existe — nada a fazer.");
            return [];
        }

        return [$bizId];
    }
}
