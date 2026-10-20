<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Retorno do orçamento não aberto (US-SELL-070 · UC-FLX-04).
 *
 * Por empresa ($businessId no construtor — fila não tem sessão, ADR 0093): orçamento em
 * `quote_sent` (ou cotação legada sem etapa) há mais de 48 h sem nenhuma abertura do link
 * público ganha UMA tarefa de retorno para o vendedor, no agendamento do CRM (`crm_schedules`).
 *
 * Idempotente: a linha do orçamento em `transaction_quote_accesses` é "reivindicada" marcando
 * `followup_created_at` antes de criar a tarefa, dentro da mesma transação. Rodar de novo não
 * duplica; empresa sem a tabela do CRM não ganha nada (o módulo é opcional).
 */
class QuoteFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const HORAS_SEM_ABERTURA = 48;

    public function __construct(public int $businessId)
    {
    }

    /** @return int quantas tarefas foram criadas */
    public function handle(): int
    {
        if ($this->businessId <= 0 || ! Schema::hasTable('crm_schedules')) {
            return 0;
        }

        $criadas = 0;
        foreach ($this->candidatos() as $orcamento) {
            if ($this->criarRetorno($orcamento)) {
                $criadas++;
            }
        }

        return $criadas;
    }

    /** Orçamentos da empresa enviados há mais de 48 h, nunca abertos e ainda sem retorno. */
    public function candidatos(): \Illuminate\Support\Collection
    {
        $limite = now()->subHours(self::HORAS_SEM_ABERTURA);

        return DB::table('transactions as t')
            ->leftJoin('sale_process_stages as sps', 'sps.id', '=', 't.current_stage_id')
            ->leftJoin('transaction_quote_accesses as a', 'a.transaction_id', '=', 't.id')
            ->where('t.business_id', $this->businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'draft')
            ->where('t.sub_status', 'quotation')
            ->where(fn ($x) => $x->whereNull('sps.key')->orWhere('sps.key', 'quote_sent'))
            ->whereNull('a.first_opened_at')
            ->whereNull('a.followup_created_at')
            ->whereRaw(
                'COALESCE((SELECT MAX(h.executed_at) FROM sale_stage_history h'
                . ' JOIN sale_process_stages hs ON hs.id = h.to_stage_id'
                . ' WHERE h.transaction_id = t.id AND h.business_id = t.business_id AND hs.key = ?), t.created_at) <= ?',
                ['quote_sent', $limite]
            )
            ->select('t.id', 't.contact_id', 't.created_by', 't.invoice_no')
            ->orderBy('t.id')
            ->get();
    }

    private function criarRetorno(object $orcamento): bool
    {
        try {
            return DB::transaction(function () use ($orcamento) {
                $agora = now();
                DB::table('transaction_quote_accesses')->insertOrIgnore([
                    'business_id' => $this->businessId,
                    'transaction_id' => (int) $orcamento->id,
                    'open_count' => 0,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                $linha = DB::table('transaction_quote_accesses')
                    ->where('business_id', $this->businessId)
                    ->where('transaction_id', (int) $orcamento->id);

                // Reivindica: só um processo marca a linha; aberto entre a busca e aqui, não conta.
                $reivindicou = (clone $linha)->whereNull('followup_created_at')->whereNull('first_opened_at')
                    ->update(['followup_created_at' => $agora, 'updated_at' => $agora]);
                if ($reivindicou !== 1) {
                    return false;
                }

                $scheduleId = DB::table('crm_schedules')->insertGetId([
                    'business_id' => $this->businessId,
                    'contact_id' => $orcamento->contact_id,
                    'title' => 'Retorno do orçamento ' . $orcamento->invoice_no,
                    'status' => 'scheduled',
                    'start_datetime' => $agora,
                    'end_datetime' => $agora->copy()->addHour(),
                    'description' => 'Orçamento enviado há mais de ' . self::HORAS_SEM_ABERTURA . ' h sem abertura do link pelo cliente.',
                    'schedule_type' => 'call',
                    'allow_notification' => 0,
                    'created_by' => (int) $orcamento->created_by,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
                DB::table('crm_schedule_users')->insert([
                    'schedule_id' => $scheduleId,
                    'user_id' => (int) $orcamento->created_by,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
                if (Schema::hasTable('crm_followup_invoices')) {
                    DB::table('crm_followup_invoices')->insert([
                        'follow_up_id' => $scheduleId,
                        'transaction_id' => (int) $orcamento->id,
                    ]);
                }

                (clone $linha)->update(['followup_schedule_id' => $scheduleId]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('quote_followup.failed', [
                'business_id' => $this->businessId,
                'transaction_id' => (int) $orcamento->id,
                'error' => class_basename($e),
            ]);

            return false;
        }
    }
}
