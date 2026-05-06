<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use App\TaxRate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;

/**
 * ADR ARQ-0005 · Bridge `nfe_fiscal_rules` → `tax_rates` (core UPos).
 *
 * Single listener consome 3 events. Mantém `tax_rates` derivada do motor
 * pra Connector + Accounting + outros módulos UPos seguirem lendo
 * normalmente. Mapping mantido em `nfe_fiscal_rule_tax_rate_links`.
 *
 * Política do `tax_rates.amount`: soma efetiva (ICMS + PIS + COFINS + IPI)
 * em decimal (0.18 = 18%) — ADR ARQ-0005 chama de "carga tributária efetiva".
 *
 * Multi-tenant: `business_id` sempre escopa (skill `multi-tenant-patterns`).
 *
 * Defensivo:
 *   - `created_by` em `tax_rates` é NOT NULL: fallback pra `auth()->id()`,
 *     primeiro user do business, ou 1 (sistema).
 *   - Falha do listener NÃO derruba o save da fiscal_rule (já commitada
 *     antes do event). Throwable é re-throwado pra queue retry.
 *
 * Fila: 'nfe_bridge' (separada de 'nfe' pra não competir com listeners
 * críticos de emissão).
 */
class SyncFiscalRuleToTaxRate implements ShouldQueue
{
    public string $queue = 'nfe_bridge';
    public int $tries = 3;
    public int $backoff = 30;

    public function handleCreated(FiscalRuleCreated $event): void
    {
        $this->sincronizar($event->rule);
    }

    public function handleUpdated(FiscalRuleUpdated $event): void
    {
        $this->sincronizar($event->rule);
    }

    public function handleDeleted(FiscalRuleDeleted $event): void
    {
        // Bridge link tem ON DELETE CASCADE no FK fiscal_rule_id, então a row
        // de bridge some sozinha. Aqui apenas removemos a tax_rate vinculada
        // (se ela ainda existir e foi auto-criada pela bridge).
        $link = DB::table('nfe_fiscal_rule_tax_rate_links')
            ->where('fiscal_rule_id', $event->ruleId)
            ->where('business_id', $event->businessId)
            ->first();

        if (! $link) {
            return; // sem mapping prévio — nada a fazer
        }

        try {
            TaxRate::where('id', $link->tax_rate_id)
                ->where('business_id', $event->businessId) // defesa multi-tenant
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('SyncFiscalRuleToTaxRate: falha ao deletar TaxRate vinculada — link já vai sumir via cascade', [
                'fiscal_rule_id' => $event->ruleId,
                'tax_rate_id'    => $link->tax_rate_id,
                'error'          => $e->getMessage(),
            ]);
        }

        // Link com fiscal_rule_id = ruleId será deletado em cascade quando
        // NfeFiscalRule::delete() finalizar. Não precisa apagar manualmente.
    }

    /**
     * Cria ou atualiza a TaxRate derivada da rule + persiste o mapping bridge.
     */
    private function sincronizar(NfeFiscalRule $rule): void
    {
        $name   = $this->montarNome($rule);
        $amount = $this->cargaEfetiva($rule);

        $link = DB::table('nfe_fiscal_rule_tax_rate_links')
            ->where('fiscal_rule_id', $rule->id)
            ->where('business_id', $rule->business_id)
            ->first();

        if ($link) {
            // Update
            TaxRate::where('id', $link->tax_rate_id)
                ->where('business_id', $rule->business_id)
                ->update([
                    'name'   => $name,
                    'amount' => $amount,
                ]);
            return;
        }

        // Create
        $taxRate = TaxRate::create([
            'business_id'  => $rule->business_id,
            'name'         => $name,
            'amount'       => $amount,
            'is_tax_group' => false,
            'created_by'   => $this->resolverCreatedBy((int) $rule->business_id),
        ]);

        DB::table('nfe_fiscal_rule_tax_rate_links')->insert([
            'business_id'    => $rule->business_id,
            'fiscal_rule_id' => $rule->id,
            'tax_rate_id'    => $taxRate->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Naming convention ADR ARQ-0005:
     *   "[NfeBrasil] NCM 22021000 SP->RJ"
     *   "[NfeBrasil] NCM 22021000 SP->all"
     */
    private function montarNome(NfeFiscalRule $rule): string
    {
        return sprintf(
            '[NfeBrasil] NCM %s %s->%s',
            $rule->ncm,
            $rule->uf_origem,
            $rule->uf_destino ?: 'all',
        );
    }

    /**
     * Carga tributária efetiva = soma das alíquotas em decimal.
     * Mantida simples — não soma MVA/FCP por serem componentes de regime
     * especial (ICMS-ST). UI core mostra esse número agregado em select
     * `tax_rates`; engine real continua usando `nfe_fiscal_rules` linha-a-linha.
     */
    private function cargaEfetiva(NfeFiscalRule $rule): float
    {
        return round(
            (float) $rule->aliquota_icms +
            (float) $rule->aliquota_pis +
            (float) $rule->aliquota_cofins +
            (float) $rule->aliquota_ipi,
            4,
        );
    }

    /**
     * `tax_rates.created_by` é NOT NULL FK pra users.id.
     * Tenta auth()->id() (caller direto); senão primeiro user do business;
     * fallback final 1 (admin/sistema).
     */
    private function resolverCreatedBy(int $businessId): int
    {
        $authId = auth()->id();
        if ($authId) return (int) $authId;

        try {
            $user = DB::table('users')
                ->where('business_id', $businessId)
                ->orderBy('id')
                ->value('id');
            if ($user) return (int) $user;
        } catch (\Throwable) {
            // tabela users ausente em testes isolados
        }

        return 1; // sistema/admin fallback
    }
}
