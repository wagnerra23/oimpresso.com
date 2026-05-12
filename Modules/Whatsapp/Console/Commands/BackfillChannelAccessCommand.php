<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;

/**
 * Hotfix — Backfill `channel_user_access` pra atendentes pré-existentes (US-WA-068/069).
 *
 * Contexto: merge #655 (US-WA-069) ativou filtragem de inbox por canal usando
 * `channel_user_access`. Tabela criada em #644 (US-WA-068) nasceu vazia em prod
 * — atendentes que já tinham permission `whatsapp.send` (via role Admin#{biz})
 * perderam acesso ao inbox até receberem grant manual via UI Channels.
 *
 * Este command faz backfill idempotente: pra cada Channel ativo, cria grant
 * ativo (`revoked_at NULL`) pra todo User do mesmo business com permission
 * `whatsapp.send` OU `whatsapp.access`.
 *
 * Uso pós-deploy:
 *   php artisan whatsapp:backfill-channel-access --business=1   (smoke biz=1 prod)
 *   php artisan whatsapp:backfill-channel-access --dry-run      (preview todos)
 *   php artisan whatsapp:backfill-channel-access                (todos businesses)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 * - `business_id` scope explícito em todas queries
 * - Permission filtrada per-business (Spatie UltimatePOS pattern)
 * - Idempotente (UNIQUE + skip se já existe ativo)
 * - Logs sem PII (só IDs)
 *
 * Edge case: business sem nenhum user com `whatsapp.send/access` → log warning
 * + skip canal. Caso provável pós-#655 onde permission registry zerada.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068 / US-WA-069
 */
class BackfillChannelAccessCommand extends Command
{
    protected $signature = 'whatsapp:backfill-channel-access
                            {--business=all : business_id alvo (default: all)}
                            {--dry-run : Só conta, não persiste}';

    protected $description = 'Backfill channel_user_access pra users com whatsapp.send/access (idempotente)';

    public function handle(): int
    {
        $businessOpt = (string) $this->option('business');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida.');
        }

        // SUPERADMIN: backfill CLI cross-business — sem auth, scope não filtra
        // mas explicitamos withoutGlobalScope pra deixar intent claro.
        $channelsQuery = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class);

        if ($businessOpt !== 'all') {
            $businessId = (int) $businessOpt;
            if ($businessId <= 0) {
                $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
                return self::FAILURE;
            }
            $channelsQuery->where('business_id', $businessId);
        }

        $channels = $channelsQuery->orderBy('business_id')->orderBy('id')->get();
        $totalChannels = $channels->count();

        if ($totalChannels === 0) {
            $this->info('Nenhum canal encontrado pra processar.');
            return self::SUCCESS;
        }

        $this->info("Processando {$totalChannels} canal(is)...");

        // Cache per-business pra evitar re-query users a cada canal
        $usersPerBusiness = [];
        $totalGranted = 0;
        $totalSkipped = 0;
        $totalChannelsSkipped = 0;

        foreach ($channels as $channel) {
            /** @var Channel $channel */
            $bizId = (int) $channel->business_id;

            if (! isset($usersPerBusiness[$bizId])) {
                $usersPerBusiness[$bizId] = $this->fetchWhatsappUsers($bizId);
            }

            $users = $usersPerBusiness[$bizId];

            if ($users->isEmpty()) {
                $this->warn(sprintf(
                    '  Canal #%d (biz=%d): nenhum user com whatsapp.send/access — skip',
                    $channel->id,
                    $bizId
                ));
                Log::warning('[whatsapp.backfill_channel_access.no_users]', [
                    'business_id' => $bizId,
                    'channel_id' => $channel->id,
                ]);
                $totalChannelsSkipped++;
                continue;
            }

            $grantedNow = 0;
            $skippedNow = 0;

            foreach ($users as $user) {
                $userId = (int) $user->id;

                // Idempotência — query explícita ao invés de updateOrCreate por causa
                // do UNIQUE composto (channel_id, user_id, revoked_at) tratar NULL como
                // distinto: updateOrCreate com revoked_at=null pode não casar match em
                // alguns engines. Query manual é defensiva.
                $existingActive = ChannelUserAccess::query()
                    ->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: CLI sem auth
                    ->where('business_id', $bizId)
                    ->where('channel_id', $channel->id)
                    ->where('user_id', $userId)
                    ->whereNull('revoked_at')
                    ->exists();

                if ($existingActive) {
                    $skippedNow++;
                    continue;
                }

                if (! $dryRun) {
                    DB::transaction(function () use ($channel, $userId, $bizId) {
                        ChannelUserAccess::create([
                            'business_id' => $bizId,
                            'channel_id' => $channel->id,
                            'user_id' => $userId,
                            // granted_by_user_id = 0 = sentinel "system backfill".
                            // Coluna é NOT NULL unsignedInteger; 0 reservado pra system
                            // ops (não colide com users.id que começa em 1).
                            'granted_by_user_id' => 0,
                            'granted_at' => now(),
                        ]);
                    });
                }

                $grantedNow++;
            }

            $action = $dryRun ? 'would grant' : 'granted';
            $this->line(sprintf(
                '  Canal #%d (biz=%d) "%s": %s %d user(s) · skipped %d (já ativos)',
                $channel->id,
                $bizId,
                $channel->label,
                $action,
                $grantedNow,
                $skippedNow
            ));

            $totalGranted += $grantedNow;
            $totalSkipped += $skippedNow;
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d canal(is) processado(s), %d canal(is) sem users elegíveis · %s %d grant(s) · %d já ativos',
            $totalChannels - $totalChannelsSkipped,
            $totalChannelsSkipped,
            $dryRun ? 'WOULD grant' : 'granted',
            $totalGranted,
            $totalSkipped
        ));

        Log::info('[whatsapp.backfill_channel_access.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'channels_total' => $totalChannels,
            'channels_skipped' => $totalChannelsSkipped,
            'grants_created' => $totalGranted,
            'grants_skipped_existing' => $totalSkipped,
        ]);

        return self::SUCCESS;
    }

    /**
     * Busca Users do business com permission Whatsapp (send ou access).
     *
     * Tier 0: filter explícito por business_id. Spatie usa relacionamentos
     * `roles` + `permissions` polimórficos — `whatsapp.send/access` pode vir
     * direto OU via role (Admin#{biz}, Cashier#{biz}, etc).
     */
    private function fetchWhatsappUsers(int $businessId): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->whereHas('permissions', function ($p) {
                    $p->whereIn('name', ['whatsapp.send', 'whatsapp.access']);
                })->orWhereHas('roles.permissions', function ($p) {
                    $p->whereIn('name', ['whatsapp.send', 'whatsapp.access']);
                });
            })
            ->get();
    }
}
