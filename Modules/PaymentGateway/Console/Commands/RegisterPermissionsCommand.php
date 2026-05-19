<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Http\Controllers\DataController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registra permissions canônicas `paymentgateway.*` (Spatie) + atribui ao
 * role `Admin#{biz}` de cada business.
 *
 * Pattern espelhado de `whatsapp:register-permissions` (PR #665) — resolve
 * o mesmo bug: permissions definidas em DataController nunca chegam à
 * tabela `permissions` em prod até alguém atribuir via UI /roles, e Spatie
 * cria on-demand. Comando força registry idempotente.
 *
 * ADR 0170 Onda 5 SIMPLIFICADA — pré-requisito pra Wagner conseguir marcar
 * `paymentgateway.cobranca.emit` etc no role Admin#1 antes de cobrar tenants.
 *
 * Uso pós-deploy:
 *   1) Preview:   php artisan paymentgateway:register-permissions --business=1 --dry-run
 *   2) Apply:     php artisan paymentgateway:register-permissions --business=1
 *   3) Rollout:   php artisan paymentgateway:register-permissions --business=all
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Permissions são GLOBAIS (sem business_id) — guard `web`
 *   - Role `Admin#{biz}` lookup com `business_id` filter (UltimatePOS pattern)
 *   - `firstOrCreate` idempotente
 *   - Logs sem PII (só IDs)
 *
 * @see DataController::user_permissions() — fonte canônica das 10 permissions
 * @see memory/reference/whatsapp-permissions-spatie.md
 */
class RegisterPermissionsCommand extends Command
{
    protected $signature = 'paymentgateway:register-permissions
                            {--business=all : business_id alvo (numeric) ou "all"}
                            {--dry-run : Só conta + lista, não persiste}';

    protected $description = 'Registra permissions paymentgateway.* (Spatie) + atribui ao Admin#{biz} (idempotente)';

    public function handle(): int
    {
        $businessOpt = (string) $this->option('business');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida.');
        }

        $permissionsDef = (new DataController())->user_permissions();
        $permissionNames = array_map(static fn ($p) => $p['value'], $permissionsDef);

        $this->line('Permissions canônicas (DataController::user_permissions):');
        foreach ($permissionsDef as $p) {
            $this->line(sprintf('  - %-48s %s', $p['value'], (string) $p['label']));
        }
        $this->newLine();

        $registered = [];
        $createdCount = 0;
        foreach ($permissionsDef as $p) {
            if ($dryRun) {
                $exists = Permission::where('name', $p['value'])
                    ->where('guard_name', 'web')
                    ->exists();
                if (!$exists) {
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
            count($permissionsDef) - $createdCount,
        ));
        $this->newLine();

        $businessIds = $this->resolveBusinessIds($businessOpt);
        if ($businessIds === null) {
            return self::FAILURE;
        }
        if (empty($businessIds)) {
            $this->warn('Nenhum business encontrado.');

            return self::SUCCESS;
        }

        $totalAttached = 0;
        $totalAlreadyHad = 0;
        $totalSkipped = 0;

        foreach ($businessIds as $bizId) {
            $roleName = "Admin#{$bizId}";

            $role = Role::where('name', $roleName)
                ->where('business_id', $bizId)
                ->where('guard_name', 'web')
                ->first();

            if (!$role) {
                $this->warn(sprintf(
                    '  Business #%d: %s não encontrado — skip',
                    $bizId,
                    $roleName,
                ));
                Log::warning('[paymentgateway.register_permissions.role_missing]', [
                    'business_id' => $bizId,
                    'role_name' => $roleName,
                ]);
                $totalSkipped++;
                continue;
            }

            if ($dryRun) {
                $alreadyHas = $role->permissions()
                    ->whereIn('name', $permissionNames)
                    ->count();
                $wouldAttach = count($permissionNames) - $alreadyHas;
                $this->line(sprintf(
                    '  Business #%d: %s ← WOULD attach %d (já tem %d)',
                    $bizId,
                    $roleName,
                    $wouldAttach,
                    $alreadyHas,
                ));
                $totalAttached += $wouldAttach;
                $totalAlreadyHad += $alreadyHas;
                continue;
            }

            $beforeIds = $role->permissions()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

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
                $alreadyHad,
            ));

            $totalAttached += $attached;
            $totalAlreadyHad += $alreadyHad;
        }

        if (!$dryRun) {
            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                // tolerante a env sem cache configurado
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d business(es) processado(s) · %d skipped · %s %d permission(s) · %d já tinha(m)',
            count($businessIds) - $totalSkipped,
            $totalSkipped,
            $dryRun ? 'WOULD attach' : 'attached',
            $totalAttached,
            $totalAlreadyHad,
        ));

        Log::info('[paymentgateway.register_permissions.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'permissions_total' => count($permissionsDef),
            'permissions_created' => $createdCount,
            'businesses_processed' => count($businessIds) - $totalSkipped,
            'businesses_skipped' => $totalSkipped,
            'attached_total' => $totalAttached,
        ]);

        return self::SUCCESS;
    }

    /**
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

        $exists = Business::query()->whereKey($bizId)->exists();
        if (!$exists) {
            $this->error("Business #{$bizId} não encontrado.");

            return null;
        }

        return [$bizId];
    }
}
