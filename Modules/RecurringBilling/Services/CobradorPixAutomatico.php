<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\PaymentGatewayService;

/**
 * Motor de cobrança automática da assinatura pelo Pix Automático do BCB (US-RB-021 ·
 * US-RECURRINGBILLING-002 · decisão [W] 2026-10-26: banco direto, mandato BCB, sem Asaas).
 *
 * Para UM business (Tier 0, ADR 0093 — `business_id` explícito em toda consulta):
 *  1. faturas `open` de assinatura `active` cujo vencimento está na janela do BCB — de 10 a 2 dias
 *     à frente (a API Pix recusa cobrança fora dela; vencida não é cobrança de Pix Automático);
 *  2. só assinatura com recorrência APROVADA pelo pagador (`metadata.pix_automatico.status` e
 *     `id_rec`, gravados pela jornada de aprovação);
 *  3. pula a fatura que já tem cobrança viva (pending/emitida/vencida/paga) — uma por ciclo;
 *  4. cria a cobrança do ciclo pelo `PaymentGatewayService::emitirCobrancaRecorrente`, com o
 *     valor da FATURA em centavos inteiros (sem float);
 *  5. erro do banco marca a cobrança `erro`; a próxima rodada tenta de novo com a MESMA chave
 *     (a chave vira o txid: se o banco tinha aceitado e só a resposta se perdeu, o txid repetido
 *     é recusado e não sai segunda cobrança). Chave nova só depois de uma tentativa cancelada.
 *
 * `$dryRun` não grava nada e não chama o banco: devolve a lista do que seria cobrado.
 */
class CobradorPixAutomatico
{
    public const JANELA_MIN_DIAS = 2;

    public const JANELA_MAX_DIAS = 10;

    /**
     * @return list<array{fatura_id:int, numero_documento:string, vencimento:string, valor_centavos:int, acao:string}>
     */
    public function cobrar(int $businessId, ?string $hoje = null, bool $dryRun = false): array
    {
        $hoje = Carbon::parse($hoje ?? now()->toDateString())->startOfDay();

        // O `rb:generate-invoices` agendado gera a fatura no próprio vencimento (antecedência 0):
        // ela nunca cairia na janela de 10 a 2 dias. Só as assinaturas com recorrência aprovada
        // têm a fatura gerada antes, aqui — as de boleto seguem no calendário de sempre.
        $comMandato = DB::table('rb_subscriptions')
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('metadata->pix_automatico->status', 'APROVADA')
            ->whereNotNull('metadata->pix_automatico->id_rec')
            ->pluck('id')->map('intval')->all();
        if ($comMandato !== [] && ! $dryRun) {
            app(InvoiceGeneratorService::class)->run($businessId, $hoje->toDateString(), false, self::JANELA_MAX_DIAS, $comMandato);
        }

        $faturas = DB::table('rb_invoices as i')
            ->join('rb_subscriptions as s', function ($j) use ($businessId) {
                $j->on('s.id', '=', 'i.subscription_id')->where('s.business_id', '=', $businessId);
            })
            ->where('i.business_id', $businessId)
            ->where('i.status', 'open')
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at')
            ->whereBetween('i.vencimento', [
                $hoje->copy()->addDays(self::JANELA_MIN_DIAS)->toDateString(),
                $hoje->copy()->addDays(self::JANELA_MAX_DIAS)->toDateString(),
            ])
            ->orderBy('i.vencimento')
            ->orderBy('i.id')
            ->get(['i.id', 'i.numero_documento', 'i.valor', 'i.vencimento', 'i.contact_id', 's.metadata']);

        $resultado = [];
        $credencial = null;
        foreach ($faturas as $f) {
            $mandato = (array) (json_decode((string) $f->metadata, true)['pix_automatico'] ?? []);
            if (($mandato['status'] ?? null) !== 'APROVADA' || empty($mandato['id_rec'])) {
                continue; // sem recorrência aprovada pelo pagador: não é Pix Automático
            }

            $linha = [
                'fatura_id' => (int) $f->id,
                'numero_documento' => (string) $f->numero_documento,
                'vencimento' => substr((string) $f->vencimento, 0, 10),
                'valor_centavos' => Centavos::de((string) $f->valor),
                'acao' => 'cobrar',
            ];

            // SUPERADMIN: roda no comando agendado (sem sessão); o escopo vem do business_id explícito.
            $tentativas = Cobranca::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('origem_type', 'invoice')
                ->where('origem_id', (int) $f->id)
                ->pluck('status');
            if ($tentativas->contains(fn ($s) => in_array($s, ['pending', 'emitida', 'vencida', 'paga'], true))) {
                $linha['acao'] = 'ja_cobrada';
                $resultado[] = $linha;

                continue;
            }

            if ($dryRun) {
                $resultado[] = $linha;

                continue;
            }

            $credencial ??= PaymentGatewayCredential::query()
                ->where('business_id', $businessId)
                ->where('gateway_key', 'bcb_pix')
                ->where('ativo', true)
                ->orderByDesc('id')
                ->first();
            if ($credencial === null) {
                $linha['acao'] = 'sem_credencial';
                $resultado[] = $linha;

                continue;
            }

            // d8 (auditoria 2026-11-19): a chave vira o txid, e o txid é o que impede o banco de
            // criar a mesma cobrança duas vezes. Erro na chamada não prova que o banco recusou —
            // timeout depois de aceitar também sai como erro. Por isso a retentativa reusa a MESMA
            // chave do ciclo; só uma tentativa cancelada (encerrada de fato) abre chave nova.
            $chave = "rb-cobr-{$f->id}-".($tentativas->filter(fn ($s) => $s === 'cancelada')->count() + 1);

            // A tentativa com erro ainda segura a chave na UNIQUE (business_id, idempotency_key), e o
            // PaymentGatewayService devolveria a linha com erro sem chamar o banco. Renomeia a chave
            // dela (a linha fica, para auditoria) e libera a chave para a nova tentativa.
            // SUPERADMIN: mesmo contexto sem sessão; filtra pelo business_id explícito.
            $comErro = Cobranca::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('idempotency_key', $chave)
                ->where('status', 'erro')
                ->first(['id']);
            if ($comErro !== null) {
                // SUPERADMIN: idem, renomeia só a linha deste business_id.
                Cobranca::withoutGlobalScopes()->where('business_id', $businessId)->whereKey($comErro->id)
                    ->update(['idempotency_key' => "{$chave}#erro{$comErro->id}"]);
            }

            try {
                (new PaymentGatewayService())->comCredencial($credencial)->emitirCobrancaRecorrente(
                    new EmitirCobrancaInput(
                        businessId: $businessId,
                        contactId: (int) $f->contact_id,
                        valorCentavos: $linha['valor_centavos'],
                        vencimento: new \DateTimeImmutable($linha['vencimento']),
                        descricao: "Fatura {$f->numero_documento}",
                        idempotencyKey: $chave,
                        origemType: 'invoice',
                        origemId: (int) $f->id,
                    ),
                    (string) $mandato['id_rec'],
                );
            } catch (\Throwable $e) {
                // SUPERADMIN: mesmo contexto sem sessão; filtra pelo business_id explícito.
                Cobranca::withoutGlobalScopes()->where('business_id', $businessId)->where('idempotency_key', $chave)
                    ->update(['status' => 'erro']);
                Log::warning('[rb.pix_automatico] cobrança do ciclo falhou', [
                    'business_id' => $businessId, 'fatura_id' => $f->id, 'erro' => $e->getMessage(),
                ]);
                $linha['acao'] = 'erro';
            }

            $resultado[] = $linha;
        }

        return $resultado;
    }
}
