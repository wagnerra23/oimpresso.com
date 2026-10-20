<?php

declare(strict_types=1);

namespace Modules\Financeiro\Jobs;

use App\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Mail\LembreteCobrancaMail;
use Modules\Financeiro\Services\ReguaCobrancaConfig;

/**
 * Régua de cobrança do título vencido — US-FIN-071 · UC-FLX-29.
 *
 * Para UM business (Tier 0, ADR 0093 — `$businessId` no constructor; job não tem session):
 *  1. régua desligada (default) → não faz nada;
 *  2. pega os títulos a receber em aberto/parcial cujo vencimento caiu EXATAMENTE há D dias,
 *     para cada D da régua (padrão 1/5/15). D+2 não é etapa → nada;
 *  3. por (título, etapa) grava uma linha em `fin_titulo_lembretes` — o UNIQUE garante um
 *     envio só, mesmo rodando duas vezes no dia;
 *  4. envia e-mail se `Contact::canReceiveEmailNotification()`; opt-out / sem e-mail / sem
 *     contato ficam como `nao_enviado` com o motivo (LGPD — proibicoes.md §FSM);
 *  5. na última etapa cria tarefa (`crm_schedules`, a mesma entidade do follow-up do CRM) para
 *     o vendedor da venda de origem; sem vendedor, para o dono do business.
 *
 * Fora desta entrega (follow-up no SPEC): WhatsApp, estado "em cobrança" no título, tela de
 * configuração com permissão.
 */
class ReguaCobrancaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $businessId,
        public readonly ?string $hoje = null,
    ) {}

    public function handle(ReguaCobrancaConfig $configs): void
    {
        $config = $configs->de($this->businessId);
        if (! $config['ativo'] || ! Schema::hasTable('fin_titulo_lembretes')) {
            return;
        }

        $hoje = Carbon::parse($this->hoje ?? now()->toDateString())->startOfDay();
        $ultima = max($config['dias']);
        $etapaPorData = [];
        foreach ($config['dias'] as $d) {
            $etapaPorData[$hoje->copy()->subDays($d)->toDateString()] = $d;
        }

        $titulos = DB::table('fin_titulos')
            ->where('business_id', $this->businessId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('valor_aberto', '>', 0)
            ->whereNull('deleted_at')
            ->whereIn('vencimento', array_keys($etapaPorData))
            ->get(['id', 'numero', 'cliente_id', 'vencimento', 'valor_aberto', 'origem', 'origem_id']);

        foreach ($titulos as $titulo) {
            $etapa = $etapaPorData[Carbon::parse($titulo->vencimento)->toDateString()];
            $this->processar($titulo, $etapa, $etapa === $ultima);
        }
    }

    private function processar(object $titulo, int $etapa, bool $ultima): void
    {
        $inseridos = DB::table('fin_titulo_lembretes')->insertOrIgnore([
            'business_id' => $this->businessId,
            'titulo_id' => $titulo->id,
            'etapa_dias' => $etapa,
            'canal' => 'email',
            'status' => 'nao_enviado',
            'motivo' => 'pendente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inseridos === 0) {
            return; // já processado nesta etapa — idempotência
        }

        $contato = $titulo->cliente_id
            ? Contact::withoutGlobalScopes() // SUPERADMIN: job sem session; business_id explícito abaixo
                ->where('business_id', $this->businessId)
                ->find($titulo->cliente_id)
            : null;

        [$status, $motivo] = $this->enviarEmail($titulo, $etapa, $contato);

        DB::table('fin_titulo_lembretes')
            ->where('business_id', $this->businessId)
            ->where('titulo_id', $titulo->id)
            ->where('etapa_dias', $etapa)
            ->update([
                'status' => $status,
                'motivo' => $motivo,
                'enviado_em' => $status === 'enviado' ? now() : null,
                'tarefa_id' => $ultima ? $this->criarTarefa($titulo, $etapa, $contato) : null,
                'updated_at' => now(),
            ]);
    }

    /** @return array{0: string, 1: ?string} */
    private function enviarEmail(object $titulo, int $etapa, ?Contact $contato): array
    {
        if ($contato === null) {
            return ['nao_enviado', 'sem_contato'];
        }
        if (! $contato->canReceiveEmailNotification()) {
            return ['nao_enviado', 'opt_out_email'];
        }
        $email = trim((string) $contato->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['nao_enviado', 'sem_email'];
        }

        $corpo = "Olá, {$contato->name}.\n\nO título {$titulo->numero}, vencido em "
            .Carbon::parse($titulo->vencimento)->format('d/m/Y')
            .', segue em aberto. Se já pagou, desconsidere esta mensagem.';

        try {
            Mail::to($email)->send(new LembreteCobrancaMail($this->businessId, (int) $titulo->id, $etapa, $corpo));
        } catch (\Throwable $e) {
            Log::error('[financeiro.regua_cobranca] falha no e-mail', [
                'business_id' => $this->businessId, 'titulo_id' => $titulo->id, 'error' => $e->getMessage(),
            ]);

            return ['nao_enviado', 'falha_envio'];
        }

        return ['enviado', null];
    }

    private function criarTarefa(object $titulo, int $etapa, ?Contact $contato): ?int
    {
        if (! Schema::hasTable('crm_schedules')) {
            return null;
        }
        $responsavel = $this->vendedor($titulo)
            ?? (int) DB::table('business')->where('id', $this->businessId)->value('owner_id');
        if (! $responsavel) {
            return null;
        }

        $id = DB::table('crm_schedules')->insertGetId([
            'business_id' => $this->businessId,
            'contact_id' => $contato?->id,
            'title' => "Cobrar título {$titulo->numero} — vencido há {$etapa} dias",
            'status' => 'scheduled',
            'start_datetime' => now(),
            'end_datetime' => now()->addDay(),
            'description' => 'Régua de cobrança (US-FIN-071): última etapa sem pagamento.',
            'schedule_type' => 'call',
            'allow_notification' => 0,
            'created_by' => $responsavel,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (Schema::hasTable('crm_schedule_users')) {
            DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $responsavel]);
        }

        return (int) $id;
    }

    /** Vendedor da venda de origem: comissionado, senão quem lançou a venda. */
    private function vendedor(object $titulo): ?int
    {
        if ($titulo->origem !== 'venda' || ! $titulo->origem_id) {
            return null;
        }
        $venda = DB::table('transactions')
            ->where('business_id', $this->businessId)
            ->where('id', $titulo->origem_id)
            ->first(['commission_agent', 'created_by']);

        $id = (int) ($venda->commission_agent ?? 0) ?: (int) ($venda->created_by ?? 0);

        return $id ?: null;
    }
}
