<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contact;
use App\Contracts\Atendimento\EnviaPesquisaSatisfacao;
use App\Mail\PesquisaSatisfacaoEntregaMail;
use App\Services\Sells\PesquisaEntregaConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Pesquisa de satisfação depois da entrega — US-SELL-071 · UC-FLX-25.
 *
 * Para UM business (Tier 0, ADR 0093 — `$businessId` no construtor; fila não tem sessão):
 *  1. pesquisa desligada (default) → não faz nada;
 *  2. vendas que entraram em `delivered` (último registro em `sale_stage_history`) há 2 dias ou
 *     mais — até 9, para não disparar o histórico inteiro no dia em que a empresa liga — e que
 *     ainda estão em `delivered`/`completed` (cancelada antes disso não recebe);
 *  3. uma linha por venda em `transaction_satisfaction_surveys` — o UNIQUE garante uma pesquisa só;
 *  4. envia pelo WhatsApp via contrato `App\Contracts\Atendimento\EnviaPesquisaSatisfacao` (o
 *     módulo Whatsapp liga o CSAT, nota 1-5; sem o módulo a interface não está ligada) se
 *     `canReceiveWhatsappNotification()`; senão por e-mail se `canReceiveEmailNotification()`;
 *     senão fica `nao_enviado` com o motivo (LGPD — proibicoes.md §FSM);
 *  5. copia a nota respondida no WhatsApp (lida pelo mesmo contrato) e, se a nota for
 *     igual ou menor que o limite da empresa, abre um atendimento: tarefa no agendamento do CRM
 *     (`crm_schedules`, ligada à venda em `crm_followup_invoices`) para o vendedor.
 *
 * Fora desta entrega (follow-up no SPEC): resposta por e-mail (não há parser), tela das respostas.
 */
class PesquisaSatisfacaoEntregaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const DIAS_DEPOIS_DA_ENTREGA = 2;

    public const JANELA_DIAS = 7;

    public int $tries = 1;

    public function __construct(public readonly int $businessId) {}

    /** @return array{enviadas: int, nao_enviadas: int, atendimentos: int} */
    public function handle(PesquisaEntregaConfig $configs): array
    {
        $r = ['enviadas' => 0, 'nao_enviadas' => 0, 'atendimentos' => 0];
        $config = $configs->de($this->businessId);
        if ($this->businessId <= 0 || ! $config['ativo'] || ! Schema::hasTable('transaction_satisfaction_surveys')) {
            return $r;
        }

        foreach ($this->candidatos() as $venda) {
            $status = $this->processar($venda);
            if ($status === 'enviado') {
                $r['enviadas']++;
            } elseif ($status === 'nao_enviado') {
                $r['nao_enviadas']++;
            }
        }

        $this->copiarNotasDoWhatsapp();
        $r['atendimentos'] = $this->abrirAtendimentos($config['nota_baixa']);

        return $r;
    }

    public function candidatos(): \Illuminate\Support\Collection
    {
        $entregueEm = '(SELECT MAX(h.executed_at) FROM sale_stage_history h'
            .' JOIN sale_process_stages hs ON hs.id = h.to_stage_id'
            .' WHERE h.transaction_id = t.id AND h.business_id = t.business_id AND hs.key = ?)';
        $limite = now()->subDays(self::DIAS_DEPOIS_DA_ENTREGA);

        return DB::table('transactions as t')
            ->join('sale_process_stages as cur', 'cur.id', '=', 't.current_stage_id')
            ->where('t.business_id', $this->businessId)
            ->where('t.type', 'sell')
            ->whereIn('cur.key', ['delivered', 'completed'])
            ->whereNotExists(fn ($q) => $q->from('transaction_satisfaction_surveys as s')
                ->whereColumn('s.transaction_id', 't.id')->where('s.business_id', $this->businessId))
            ->whereRaw("{$entregueEm} <= ?", ['delivered', $limite])
            ->whereRaw("{$entregueEm} >= ?", ['delivered', $limite->copy()->subDays(self::JANELA_DIAS)])
            ->selectRaw("t.id, t.contact_id, t.created_by, t.commission_agent, t.invoice_no, {$entregueEm} as entregue_em", ['delivered'])
            ->orderBy('t.id')
            ->get();
    }

    private function processar(object $venda): ?string
    {
        $inseridos = DB::table('transaction_satisfaction_surveys')->insertOrIgnore([
            'business_id' => $this->businessId,
            'transaction_id' => (int) $venda->id,
            'contact_id' => $venda->contact_id,
            'entregue_em' => $venda->entregue_em,
            'status' => 'nao_enviado',
            'motivo' => 'pendente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inseridos === 0) {
            return null; // outra execução já pegou esta venda
        }

        $contato = $venda->contact_id
            ? Contact::withoutGlobalScopes() // SUPERADMIN: job sem session; business_id explícito abaixo
                ->where('business_id', $this->businessId)->find($venda->contact_id)
            : null;

        [$status, $canal, $motivo, $csatId] = $this->enviar($venda, $contato);

        DB::table('transaction_satisfaction_surveys')
            ->where('business_id', $this->businessId)->where('transaction_id', (int) $venda->id)
            ->update(['status' => $status, 'canal' => $canal, 'motivo' => $motivo, 'csat_response_id' => $csatId, 'updated_at' => now()]);

        return $status;
    }

    /** @return array{0: string, 1: string, 2: ?string, 3: ?int} */
    private function enviar(object $venda, ?Contact $contato): array
    {
        if ($contato === null) {
            return ['nao_enviado', 'nenhum', 'sem_contato', null];
        }
        if (! $contato->canReceiveWhatsappNotification() && ! $contato->canReceiveEmailNotification()) {
            return ['nao_enviado', 'nenhum', 'opt_out', null];
        }

        if ($contato->canReceiveWhatsappNotification() && ($csat = $this->enviarWhatsapp($venda, $contato))) {
            return ['enviado', 'whatsapp', null, $csat];
        }

        $email = trim((string) $contato->email);
        if ($contato->canReceiveEmailNotification() && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $corpo = "Olá, {$contato->name}.\n\nSeu pedido {$venda->invoice_no} foi entregue. "
                .'Como você avalia a entrega, de 1 a 5? Responda este e-mail com o número.';
            try {
                Mail::to($email)->send(new PesquisaSatisfacaoEntregaMail($this->businessId, (int) $venda->id, $corpo));

                return ['enviado', 'email', null, null];
            } catch (\Throwable $e) {
                Log::warning('[sells.pesquisa_entrega] falha no e-mail', ['business_id' => $this->businessId, 'transaction_id' => (int) $venda->id, 'error' => class_basename($e)]);

                return ['nao_enviado', 'email', 'falha_envio', null];
            }
        }

        return ['nao_enviado', 'nenhum', 'sem_canal', null];
    }

    private function whatsapp(): ?EnviaPesquisaSatisfacao
    {
        return app()->bound(EnviaPesquisaSatisfacao::class) ? app(EnviaPesquisaSatisfacao::class) : null;
    }

    /** Pesquisa pelo WhatsApp (contrato do módulo); devolve a referência para ler a nota depois. */
    private function enviarWhatsapp(object $venda, Contact $contato): ?int
    {
        if (! $canal = $this->whatsapp()) {
            return null;
        }
        try {
            $r = $canal->enviar($this->businessId, (int) $contato->id, (int) $venda->id, (int) $venda->created_by);
        } catch (\Throwable $e) {
            Log::warning('[sells.pesquisa_entrega] falha no whatsapp', ['business_id' => $this->businessId, 'transaction_id' => (int) $venda->id, 'error' => class_basename($e)]);

            return null;
        }

        return $r['enviado'] && $r['referencia'] !== null ? $r['referencia'] : null;
    }

    private function copiarNotasDoWhatsapp(): void
    {
        if (! $canal = $this->whatsapp()) {
            return;
        }
        $pendentes = DB::table('transaction_satisfaction_surveys')
            ->where('business_id', $this->businessId)->where('canal', 'whatsapp')
            ->whereNull('nota')->whereNotNull('csat_response_id')
            ->get(['id', 'csat_response_id']);

        foreach ($pendentes as $p) {
            $nota = $canal->notaRespondida($this->businessId, (int) $p->csat_response_id);
            if ($nota === null) {
                continue;
            }
            DB::table('transaction_satisfaction_surveys')->where('business_id', $this->businessId)->where('id', $p->id)
                ->update(['nota' => $nota, 'respondido_em' => now(), 'status' => 'respondido', 'updated_at' => now()]);
        }
    }

    private function abrirAtendimentos(int $notaBaixa): int
    {
        if (! Schema::hasTable('crm_schedules')) {
            return 0;
        }
        $baixas = DB::table('transaction_satisfaction_surveys as s')
            ->join('transactions as t', function ($j) {
                $j->on('t.id', '=', 's.transaction_id')->on('t.business_id', '=', 's.business_id');
            })
            ->where('s.business_id', $this->businessId)
            ->whereNotNull('s.nota')->where('s.nota', '<=', $notaBaixa)->whereNull('s.atendimento_schedule_id')
            ->get(['s.id', 's.nota', 't.id as transaction_id', 't.contact_id', 't.created_by', 't.commission_agent', 't.invoice_no']);

        $abertos = 0;
        foreach ($baixas as $b) {
            $abertos += DB::transaction(function () use ($b) {
                $linha = DB::table('transaction_satisfaction_surveys')->where('business_id', $this->businessId)
                    ->where('id', $b->id)->whereNull('atendimento_schedule_id')->lockForUpdate()->first();
                if (! $linha) {
                    return 0;
                }
                $responsavel = (int) ($b->commission_agent ?: $b->created_by)
                    ?: (int) DB::table('business')->where('id', $this->businessId)->value('owner_id');

                $id = DB::table('crm_schedules')->insertGetId([
                    'business_id' => $this->businessId,
                    'contact_id' => $b->contact_id,
                    'title' => "Atendimento: nota {$b->nota} na entrega do pedido {$b->invoice_no}",
                    'status' => 'scheduled',
                    'start_datetime' => now(),
                    'end_datetime' => now()->addHour(),
                    'description' => 'Pesquisa de satisfação depois da entrega (US-SELL-071) com nota baixa.',
                    'schedule_type' => 'call',
                    'allow_notification' => 0,
                    'created_by' => $responsavel,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (Schema::hasTable('crm_schedule_users')) {
                    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $responsavel, 'created_at' => now(), 'updated_at' => now()]);
                }
                if (Schema::hasTable('crm_followup_invoices')) {
                    DB::table('crm_followup_invoices')->insert(['follow_up_id' => $id, 'transaction_id' => (int) $b->transaction_id]);
                }
                DB::table('transaction_satisfaction_surveys')->where('business_id', $this->businessId)->where('id', $b->id)
                    ->update(['atendimento_schedule_id' => $id, 'updated_at' => now()]);

                return 1;
            });
        }

        return $abertos;
    }
}
