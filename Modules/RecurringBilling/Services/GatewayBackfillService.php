<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;

/**
 * US-RB-052 · Backfill de gateway nas assinaturas com `metadata.gateway` NULL
 * (cobranças dormentes — migração WR2 2026-06-07 inseriu metadata sem a chave
 * `gateway` que o fluxo de UI grava via RecurringBillingController::store).
 *
 * Regra de resolução (NADA inventado — derivada de ADR arq/0007 + BoletoService):
 *
 *   1. FK direto da conta: fin_contas_bancarias.rb_gateway_credential_id →
 *      credencial ativa → gateway = credencial.banco.
 *   2. FK reverso: rb_boleto_credentials.conta_bancaria_id = s.conta_bancaria_id
 *      (ativo=1) → gateway = credencial.banco.
 *   3. Fallback: banco_codigo da conta (077=inter · 336=c6 · 403=cora) mapeia
 *      pro driver; se o business tem credencial ativa DESSE banco → atribui.
 *   4. Fail-closed (Tier 0 dinheiro): sem credencial ativa viável OU driver
 *      não suportado pelo BoletoService (cora não tem driver) → BLOQUEADA
 *      com motivo. Atribuir gateway sem credencial deixaria a emissão
 *      explodir depois em BoletoService::driver() firstOrFail.
 *
 * Idempotente: assinatura que já tem `metadata.gateway` é SKIP — re-rodar
 * não causa drift. Escreve APENAS `metadata` (nunca valor/estoque/status).
 *
 * Audit: SubscriptionEvent kind=note por assinatura alterada (timeline
 * append-only) + Log estruturado. LogsActivity do model NÃO cobre metadata
 * (by design, PII) — o event é a trilha.
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId explícito 1º arg; queries
 * escopadas; credencial e conta do MESMO business da assinatura.
 * Tests biz=1 (ADR 0101): nunca biz=4.
 *
 * @see Modules\RecurringBilling\Console\Commands\BackfillGatewayCommand
 * @see memory/requisitos/RecurringBilling/SPEC.md (US-RB-052)
 * @see memory/requisitos/RecurringBilling/adr/arq/0007-conta-bancaria-vs-gateway-duas-estrategias.md
 */
class GatewayBackfillService
{
    /** Drivers que BoletoService::driver() sabe instanciar. */
    public const DRIVERS_SUPORTADOS = ['inter', 'c6', 'asaas'];

    /** Código FEBRABAN → driver (Inter 077 · C6 336 · Cora 403 — handoff 2026-06-07-1855). */
    public const BANCO_CODIGO_DRIVER = [
        '077' => 'inter',
        '336' => 'c6',
        '403' => 'cora',
    ];

    /** Status elegíveis: ativas (scopeAtivas) + paused (fica pronta pro resume). */
    private const STATUS_ELEGIVEIS = ['active', 'trialing', 'past_due', 'paused'];

    /**
     * @param  int   $businessId  Multi-tenant Tier 0 obrigatório
     * @param  bool  $execute     false = dry-run (default fail-safe, NADA escrito)
     *
     * @return array{
     *   total_null:int, atribuidas:int, bloqueadas:int, skipped:int,
     *   por_gateway:array<string,int>, por_motivo:array<string,int>,
     *   linhas:list<array{subscription_id:int, status:string, conta_bancaria_id:int|null,
     *     banco_codigo:string|null, antes:null, depois:string|null, fonte:string|null, motivo:string|null}>
     * }
     */
    public function run(int $businessId, bool $execute = false): array
    {
        return OtelHelper::spanBiz('rb.gateway.backfill.run', function () use ($businessId, $execute): array {
            return $this->runInternal($businessId, $execute);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'gateway.backfill.run',
            'business_id' => $businessId,
            'execute'     => $execute,
        ]);
    }

    /**
     * @return array{total_null:int, atribuidas:int, bloqueadas:int, skipped:int, por_gateway:array<string,int>, por_motivo:array<string,int>, linhas:list<array<string,mixed>>}
     */
    private function runInternal(int $businessId, bool $execute): array
    {
        $subs = Subscription::query()
            ->where('business_id', $businessId)
            ->whereIn('status', self::STATUS_ELEGIVEIS)
            ->orderBy('id')
            ->get();

        $credenciaisAtivas = BoletoCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->get();

        $stats = [
            'total_null'  => 0,
            'atribuidas'  => 0,
            'bloqueadas'  => 0,
            'skipped'     => 0,
            'por_gateway' => [],
            'por_motivo'  => [],
            'linhas'      => [],
        ];

        // Contas bancárias referenciadas — 1 query, escopo business (Tier 0).
        $contas = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $subs->pluck('conta_bancaria_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach ($subs as $sub) {
            $metadata = $sub->metadata ?? [];

            if (($metadata['gateway'] ?? null) !== null) {
                $stats['skipped']++;

                continue;
            }

            $stats['total_null']++;

            $resolucao = $this->resolverGateway($sub, $contas, $credenciaisAtivas);

            $linha = [
                'subscription_id'   => (int) $sub->getKey(),
                'status'            => (string) $sub->getAttribute('status'),
                'conta_bancaria_id' => $sub->getAttribute('conta_bancaria_id'),
                'banco_codigo'      => $resolucao['banco_codigo'],
                'antes'             => null,
                'depois'            => $resolucao['gateway'],
                'fonte'             => $resolucao['fonte'],
                'motivo'            => $resolucao['motivo'],
            ];
            $stats['linhas'][] = $linha;

            if ($resolucao['gateway'] === null) {
                $stats['bloqueadas']++;
                $stats['por_motivo'][$resolucao['motivo']] = ($stats['por_motivo'][$resolucao['motivo']] ?? 0) + 1;

                continue;
            }

            $stats['atribuidas']++;
            $stats['por_gateway'][$resolucao['gateway']] = ($stats['por_gateway'][$resolucao['gateway']] ?? 0) + 1;

            if (! $execute) {
                continue;
            }

            $this->aplicar($sub, $businessId, $metadata, $resolucao);
        }

        Log::info('rb.gateway.backfill', [
            'business_id' => $businessId,
            'execute'     => $execute,
            'total_null'  => $stats['total_null'],
            'atribuidas'  => $stats['atribuidas'],
            'bloqueadas'  => $stats['bloqueadas'],
            'skipped'     => $stats['skipped'],
            'por_gateway' => $stats['por_gateway'],
            'por_motivo'  => $stats['por_motivo'],
        ]);

        return $stats;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ContaBancaria>       $contas
     * @param  \Illuminate\Support\Collection<int, BoletoCredential>    $credenciaisAtivas
     *
     * @return array{gateway:string|null, fonte:string|null, motivo:string|null, banco_codigo:string|null}
     */
    private function resolverGateway(Subscription $sub, $contas, $credenciaisAtivas): array
    {
        $contaId = $sub->getAttribute('conta_bancaria_id');

        if ($contaId === null) {
            return ['gateway' => null, 'fonte' => null, 'motivo' => 'sem_conta_bancaria', 'banco_codigo' => null];
        }

        /** @var ContaBancaria|null $conta */
        $conta = $contas->get($contaId);

        if ($conta === null) {
            // FK aponta pra conta de outro business ou deletada — nunca atribuir cross-tenant.
            return ['gateway' => null, 'fonte' => null, 'motivo' => 'conta_inexistente_no_business', 'banco_codigo' => null];
        }

        $bancoCodigo = $conta->getAttribute('banco_codigo') !== null
            ? (string) $conta->getAttribute('banco_codigo')
            : null;

        // 1) FK direto da conta (fin_contas_bancarias.rb_gateway_credential_id — ADR arq/0007)
        //    ou FK reverso (rb_boleto_credentials.conta_bancaria_id). Credencial INATIVA
        //    apontada pelo FK não conta — cai pros caminhos seguintes.
        $credencialIdDaConta = $conta->getAttribute('rb_gateway_credential_id');
        $porConta = $credencialIdDaConta !== null
            ? $credenciaisAtivas->firstWhere('id', $credencialIdDaConta)
            : null;
        $porConta ??= $credenciaisAtivas->firstWhere('conta_bancaria_id', $contaId);
        if ($porConta !== null) {
            $banco = (string) $porConta->getAttribute('banco');

            if (! in_array($banco, self::DRIVERS_SUPORTADOS, true)) {
                return ['gateway' => null, 'fonte' => null, 'motivo' => "driver_nao_suportado:{$banco}", 'banco_codigo' => $bancoCodigo];
            }

            return ['gateway' => $banco, 'fonte' => 'credencial_da_conta', 'motivo' => null, 'banco_codigo' => $bancoCodigo];
        }

        // 2) Fallback: banco_codigo da conta → driver → credencial ativa desse banco no business.
        $driver = $bancoCodigo !== null ? (self::BANCO_CODIGO_DRIVER[$bancoCodigo] ?? null) : null;

        if ($driver === null) {
            return ['gateway' => null, 'fonte' => null, 'motivo' => 'banco_codigo_sem_mapeamento', 'banco_codigo' => $bancoCodigo];
        }

        if (! in_array($driver, self::DRIVERS_SUPORTADOS, true)) {
            return ['gateway' => null, 'fonte' => null, 'motivo' => "driver_nao_suportado:{$driver}", 'banco_codigo' => $bancoCodigo];
        }

        $porBanco = $credenciaisAtivas->firstWhere('banco', $driver);
        if ($porBanco !== null) {
            return ['gateway' => $driver, 'fonte' => 'credencial_por_banco', 'motivo' => null, 'banco_codigo' => $bancoCodigo];
        }

        return ['gateway' => null, 'fonte' => null, 'motivo' => 'sem_credencial_ativa', 'banco_codigo' => $bancoCodigo];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{gateway:string|null, fonte:string|null, motivo:string|null, banco_codigo:string|null}  $resolucao
     */
    private function aplicar(Subscription $sub, int $businessId, array $metadata, array $resolucao): void
    {
        DB::transaction(function () use ($sub, $businessId, $metadata, $resolucao): void {
            $metadata['gateway'] = $resolucao['gateway'];
            $metadata['gateway_backfill'] = [
                'fonte' => $resolucao['fonte'],
                'em'    => now()->toIso8601String(),
                'via'   => 'rb:backfill-gateway',
                'us'    => 'US-RB-052',
            ];

            $sub->update(['metadata' => $metadata]);

            SubscriptionEvent::create([
                'business_id'     => $businessId,
                'subscription_id' => $sub->getKey(),
                'kind'            => SubscriptionEvent::KIND_NOTE,
                'by_actor'        => 'system:rb:backfill-gateway',
                'body'            => sprintf(
                    'Gateway "%s" atribuído via backfill US-RB-052 (fonte: %s). Assinatura estava dormente (gateway=NULL desde a migração WR2).',
                    $resolucao['gateway'],
                    $resolucao['fonte'],
                ),
                'occurred_at'     => now(),
            ]);
        });
    }
}
