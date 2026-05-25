<?php

declare(strict_types=1);

namespace Modules\Repair\Observers;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;

/**
 * JobSheetObserver — Auto-faturar OS → Venda derivada quando OS entregue.
 *
 * Onda 2 do plano F3 Integração Vendas × Oficina (A1 KB-9.75).
 *
 * Trigger: hook `updated` quando `status_id` transiciona pra RepairStatus com
 * `is_completed_status === true` (canonical "Pronto/Entregue" multi-vertical).
 * Per ADR 0192 + decisão Wagner 2026-05-25, dispara apenas em conclusão
 * terminal (cliente buscou OS · dinheiro entrou) — conservador.
 *
 * Cria Transaction (type=sell · source='oficina' · os_ref='OS-{id}') herdando
 * business_id da OS (multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Idempotência: skip se Transaction ATIVA já existe pra essa OS, via 2 chaves
 * (defesa-em-profundidade):
 *   (a) `repair_job_sheet_id = $jobSheet->id` (FK UPOS legacy desde 2020-08)
 *   (b) `os_ref = "OS-{$jobSheet->id}"` (string canonical UI cross-link · ADR 0192)
 * Ambas scopadas por `business_id` pra impedir cross-tenant.
 * Filtro `whereNull('cancelled_at')` ignora cancelamentos prévios (reverse hook)
 * pra permitir re-completion criar NOVA Transaction (ADR 0192 follow-up).
 *
 * Reverse hook (ADR 0192 follow-up · 2026-05-25 review trigger):
 *   OS terminal → não-terminal (reaberta) → marca Transaction derivada como
 *   cancelada (`cancelled_at = now()`) preservando audit trail · NÃO delete.
 *   Caminho B' (cancelled_at TIMESTAMP NULL) escolhido sobre Caminho A
 *   (SoftDeletes) porque `Transaction` UPOS legacy não usa trait e Caminho B
 *   (status='cancelled') exigiria ALTER TABLE no ENUM rígido.
 *
 * OS sem nota fiscal vira venda mesmo assim (`fiscal: {}` vazio · sem badge
 * SEFAZ na UI) — fluxo informal não bloqueia auto-faturar (Wagner 2026-05-25).
 *
 * Registrado no boot do RepairServiceProvider:
 *   JobSheet::observe(JobSheetObserver::class);
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md
 */
class JobSheetObserver
{
    public function updated(JobSheet $jobSheet): void
    {
        // Só age quando status_id mudou (filtragem cedo · skip noise).
        if (! $jobSheet->wasChanged('status_id')) {
            return;
        }

        // Lookup do novo RepairStatus + check terminal.
        $newStatus = RepairStatus::where('business_id', $jobSheet->business_id)
            ->where('id', $jobSheet->status_id)
            ->first();

        $isTerminal = $newStatus && (bool) $newStatus->is_completed_status;

        // Lookup do status anterior pra detectar transição terminal → não-terminal (reverse).
        $oldStatusId = $jobSheet->getOriginal('status_id');
        $oldStatus = $oldStatusId
            ? RepairStatus::where('business_id', $jobSheet->business_id)
                ->where('id', $oldStatusId)
                ->first()
            : null;
        $wasTerminal = $oldStatus && (bool) $oldStatus->is_completed_status;

        // REVERSE hook (ADR 0192 follow-up): OS reaberta · marca Transaction como cancelada.
        if ($wasTerminal && ! $isTerminal) {
            $this->reverseTransaction($jobSheet);

            return;
        }

        // Nada a fazer se transição não termina em terminal (não-terminal → não-terminal).
        if (! $isTerminal) {
            return;
        }

        // Idempotência: skip se já existe Transaction derivada ATIVA (cancelled_at NULL).
        // Defesa-em-profundidade: 2 chaves (repair_job_sheet_id FK + os_ref string).
        // Filtro whereNull('cancelled_at') permite re-completion pós-cancelamento criar NOVA.
        $osRef = $this->buildOsRef($jobSheet);
        $exists = Transaction::where('business_id', $jobSheet->business_id)
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($jobSheet, $osRef) {
                $q->where('repair_job_sheet_id', $jobSheet->id)
                    ->orWhere('os_ref', $osRef);
            })
            ->exists();

        if ($exists) {
            Log::info('JobSheetObserver: skip · Transaction derivada já existe', [
                'os_ref' => $osRef,
                'job_sheet_id' => $jobSheet->id,
                'business_id' => $jobSheet->business_id,
            ]);

            return;
        }

        try {
            $tx = Transaction::create([
                'business_id' => $jobSheet->business_id,
                'location_id' => $jobSheet->location_id,
                'contact_id' => $jobSheet->contact_id,
                'type' => 'sell',
                'status' => 'final',
                'sub_type' => null,
                'payment_status' => 'due',
                'source' => 'oficina',
                'os_ref' => $osRef,
                'repair_job_sheet_id' => $jobSheet->id,
                'final_total' => (float) ($jobSheet->estimated_cost ?? 0),
                'total_before_tax' => (float) ($jobSheet->estimated_cost ?? 0),
                'transaction_date' => Carbon::now(),
                'created_by' => $jobSheet->service_staff ?? $jobSheet->created_by,
            ]);

            Log::info('JobSheetObserver: Transaction derivada criada', [
                'transaction_id' => $tx->id,
                'os_ref' => $osRef,
                'job_sheet_id' => $jobSheet->id,
                'business_id' => $jobSheet->business_id,
                'final_total' => $tx->final_total,
            ]);
        } catch (\Throwable $e) {
            // Não bloqueia a transição FSM se Transaction create falhar.
            // Log pra Wagner investigar · review trigger ADR 0192 pode demandar Job assíncrono.
            Log::error('JobSheetObserver: falha ao criar Transaction derivada', [
                'os_ref' => $osRef,
                'job_sheet_id' => $jobSheet->id,
                'business_id' => $jobSheet->business_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildOsRef(JobSheet $jobSheet): string
    {
        return "OS-{$jobSheet->id}";
    }

    /**
     * Marca Transaction derivada como cancelada quando OS é reaberta.
     *
     * Caminho B' (cancelled_at TIMESTAMP NULL) — preserva row + audit trail
     * Spatie. Idempotente: já-cancelada vira no-op (whereNull filtra).
     * Multi-tenant Tier 0: filtro `business_id` impede cross-tenant.
     *
     * Não-bloqueante: erro vira Log::error (pattern existente · não interrompe FSM).
     */
    private function reverseTransaction(JobSheet $jobSheet): void
    {
        $osRef = $this->buildOsRef($jobSheet);

        try {
            $tx = Transaction::where('business_id', $jobSheet->business_id)
                ->whereNull('cancelled_at')
                ->where(function ($q) use ($jobSheet, $osRef) {
                    $q->where('repair_job_sheet_id', $jobSheet->id)
                        ->orWhere('os_ref', $osRef);
                })
                ->first();

            if (! $tx) {
                Log::info('JobSheetObserver: reverse skip · sem Transaction ativa pra cancelar', [
                    'os_ref' => $osRef,
                    'job_sheet_id' => $jobSheet->id,
                    'business_id' => $jobSheet->business_id,
                ]);

                return;
            }

            $tx->forceFill(['cancelled_at' => Carbon::now()])->save();

            Log::info('JobSheetObserver: Transaction derivada cancelada (OS reaberta)', [
                'transaction_id' => $tx->id,
                'os_ref' => $osRef,
                'job_sheet_id' => $jobSheet->id,
                'business_id' => $jobSheet->business_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('JobSheetObserver: falha ao cancelar Transaction derivada', [
                'os_ref' => $osRef,
                'job_sheet_id' => $jobSheet->id,
                'business_id' => $jobSheet->business_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
