<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisiona a conta FAKE biz=99 read-only usada pelo smoke visual pos-deploy
 * (.github/workflows/screen-smoke-after-merge.yml -> scripts/screen-smoke/smoke.mjs).
 *
 * Idempotente + transacional + DRY-RUN por padrao.
 *
 * ⚠️ Tier 0: cria login e escreve no banco multi-tenant. Rode SEMPRE --dry-run primeiro
 *    e valide no CT100 (staging, MySQL real) ANTES de producao. Quem EXECUTA e humano
 *    (cria conta + define senha); o agente Claude nao executa (regra de conta/senha).
 *
 * Colunas de `business` espelham database/seeders/FullSuiteMinimalTenantSeeder
 * (via tests/Support/WithSeededTenant.php). Enable de financeiro_module espelha
 * Modules/Financeiro/Console/Commands/InstallCommand.php (padroes verificados).
 *
 * Ao rodar com --force, IMPRIME usuario+senha UMA vez. Copie pros secrets:
 *   gh secret set SMOKE_PROD_USER --repo wagnerra23/oimpresso.com
 *   gh secret set SMOKE_PROD_PASS --repo wagnerra23/oimpresso.com
 * (a senha vai direto do seu terminal pro secret; nunca passa pelo agente)
 */
class ProvisionSmokeTenantCommand extends Command
{
    protected $signature = 'screen-smoke:provision-tenant
                            {--force : escreve de fato (default e dry-run, nada gravado)}
                            {--business-id=99 : id do tenant fake}
                            {--username=smoke_bot_99 : login da conta de smoke}';

    protected $description = 'Provisiona a conta FAKE biz=99 read-only pro smoke visual (idempotente, dry-run default).';

    public function handle(): int
    {
        $bizId = (int) $this->option('business-id');
        $username = (string) $this->option('username');
        $write = (bool) $this->option('force');
        $this->info('== screen-smoke:provision-tenant — biz=' . $bizId . ' — modo: ' . ($write ? 'ESCRITA (--force)' : 'DRY-RUN (nada gravado)') . ' ==');

        if (! $write) {
            $this->warn('DRY-RUN: mostrando o plano. --force pra aplicar (rode no CT100 antes de prod).');
        }

        $plainPassword = null;

        $run = function () use ($bizId, $username, $write, &$plainPassword) {
            // 1. Business fake (idempotente) — ordem user->business->backfill (chicken-egg FK)
            $biz = Business::query()->whereKey($bizId)->first();
            if ($biz === null) {
                $this->line('[biz]  business ' . $bizId . ' ausente -> criar');
                if ($write) {
                    $curId = optional(DB::table('currencies')->first())->id ?? 1;
                    $ownerId = optional(DB::table('users')->where('username', 'smoke_owner_' . $bizId)->first())->id
                        ?? DB::table('users')->insertGetId([
                            'surname' => 'Smoke', 'first_name' => 'Owner ' . $bizId,
                            'username' => 'smoke_owner_' . $bizId, 'password' => Hash::make(Str::random(24)),
                            'allow_login' => 0, 'status' => 'active', 'user_type' => 'user',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    DB::table('business')->insert([
                        'id' => $bizId, 'name' => 'SMOKE FAKE ' . $bizId, 'currency_id' => $curId,
                        'owner_id' => $ownerId, 'stop_selling_before' => 0, 'weighing_scale_setting' => '',
                        'certificado' => '', 'officeimpresso_numerodemaquinas' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('users')->where('id', $ownerId)->update(['business_id' => $bizId]);
                }
            } else {
                $this->line('[biz]  business ' . $bizId . ' ja existe (' . $biz->name . ')');
            }

            // 2. Usuario de LOGIN do smoke (idempotente por username; allow_login=1)
            $user = DB::table('users')->where('username', $username)->first();
            if ($user === null) {
                $this->line('[user] usuario "' . $username . '" ausente -> criar (allow_login=1)');
                $plainPassword = 'Smk-' . Str::random(20); // gerada aqui, impressa 1x
                if ($write) {
                    DB::table('users')->insert([
                        'surname' => 'Smoke', 'first_name' => 'Bot',
                        'username' => $username, 'password' => Hash::make($plainPassword),
                        'allow_login' => 1, 'status' => 'active', 'user_type' => 'user',
                        'business_id' => $bizId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            } else {
                $this->line('[user] usuario "' . $username . '" ja existe — senha NAO alterada');
            }

            // 3. Habilitar financeiro_module na subscription ativa (padrao InstallCommand)
            $pkgIds = DB::table('subscriptions')
                ->where('business_id', $bizId)->where('status', 'approved')
                ->where('end_date', '>=', now())->distinct()->pluck('package_id')->all();

            if (empty($pkgIds)) {
                $this->warn('[pkg]  SEM subscription ativa pro business ' . $bizId . ' -> Financeiro fica invisivel.');
                $this->warn('       Atribua um pacote no superadmin (/superadmin/packages/{id}/edit -> financeiro_module + "atualizar inscricoes"), depois rode de novo.');
            } else {
                foreach ($pkgIds as $pkgId) {
                    $pkg = DB::table('packages')->where('id', $pkgId)->first();
                    if (! $pkg) {
                        continue;
                    }
                    $custom = json_decode($pkg->custom_permissions ?? '{}', true) ?: [];
                    if (($custom['financeiro_module'] ?? false) !== true) {
                        $custom['financeiro_module'] = true;
                        if ($write) {
                            DB::table('packages')->where('id', $pkgId)->update(['custom_permissions' => json_encode($custom)]);
                        }
                        $this->line('[pkg]  pkg #' . $pkgId . ': financeiro_module=true ' . ($write ? 'ok' : '(dry)'));
                    } else {
                        $this->line('[pkg]  pkg #' . $pkgId . ': ja ativo');
                    }
                }
            }
        };

        if ($write) {
            DB::transaction($run);
        } else {
            $run();
        }

        if ($write && $plainPassword !== null) {
            $this->newLine();
            $this->info('== CREDENCIAIS (aparecem UMA vez — copie agora) ==');
            $this->line('SMOKE_PROD_USER = ' . $username);
            $this->line('SMOKE_PROD_PASS = ' . $plainPassword);
            $this->newLine();
            $this->line('Setar (voce cola — nunca passa pelo agente):');
            $this->line('  gh secret set SMOKE_PROD_USER --repo wagnerra23/oimpresso.com');
            $this->line('  gh secret set SMOKE_PROD_PASS --repo wagnerra23/oimpresso.com');
        } elseif ($write) {
            $this->warn('Usuario ja existia — nenhuma senha nova gerada. Resete a senha manualmente se precisar.');
        }

        return self::SUCCESS;
    }
}
