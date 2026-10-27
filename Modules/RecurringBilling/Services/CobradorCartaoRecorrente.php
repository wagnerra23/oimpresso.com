<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CardDeclinedException;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\PaymentGatewayService;
use Modules\RecurringBilling\Models\CartaoSalvo;
use Modules\RecurringBilling\Models\ChargeAttempt;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;

/**
 * Cobra a fatura da assinatura no cartão salvo e tenta de novo na recusa leve (US-RB-013).
 *
 * Para UM business (Tier 0, ADR 0093 — `business_id` explícito em toda consulta):
 *  1. faturas `open`, vencidas até hoje, de assinatura `active` com cartão salvo `ativo`;
 *  2. cada tentativa vira uma linha em `rb_charge_attempts` (append-only, `attempt_n` 1, 2, 3…);
 *  3. recusa leve (`soft_decline`) ou erro do gateway (`failed`) → nova tentativa depois de
 *     `cartao_retry_dias[n-1]` dias da última; o limite é 1 + a quantidade de intervalos;
 *  4. recusa definitiva (`hard_decline`: cartão vencido, cancelado, bloqueado, roubado, fraude…)
 *     → não tenta mais e avisa o cliente pelo WhatsApp;
 *  5. o valor cobrado é o da FATURA em centavos inteiros, sem float.
 *
 * O pagamento aprovado vira fatura paga pelo caminho que já existe: webhook → `CobrancaPaga` →
 * `MarcarFaturaPagaDaCobranca` (US-RB-057). `$dryRun` não grava nem chama o gateway.
 */
class CobradorCartaoRecorrente
{
    /**
     * @return list<array{fatura_id:int, numero_documento:string, vencimento:string, valor_centavos:int, tentativa:int, acao:string, motivo:?string}>
     */
    public function cobrar(int $businessId, ?string $hoje = null, bool $dryRun = false): array
    {
        $hoje = Carbon::parse($hoje ?? now()->toDateString())->startOfDay();
        $intervalos = self::intervalos();
        $limite = 1 + count($intervalos);

        $faturas = DB::table('rb_invoices as i')
            ->join('rb_subscriptions as s', function ($j) use ($businessId) {
                $j->on('s.id', '=', 'i.subscription_id')->where('s.business_id', '=', $businessId);
            })
            ->join('rb_cartoes_salvos as c', function ($j) use ($businessId) {
                $j->on('c.id', '=', 's.cartao_salvo_id')->where('c.business_id', '=', $businessId)->where('c.status', '=', 'ativo');
            })
            ->where('i.business_id', $businessId)
            ->where('i.status', 'open')
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at')
            ->where('i.vencimento', '<=', $hoje->toDateString())
            ->orderBy('i.vencimento')->orderBy('i.id')
            ->get(['i.id', 'i.numero_documento', 'i.valor', 'i.vencimento', 'i.contact_id', 'c.id as cartao_id']);

        $resultado = [];
        $credencial = null;
        foreach ($faturas as $f) {
            // SUPERADMIN: comando agendado, sem sessão; o business_id explícito filtra.
            $tentativas = ChargeAttempt::withoutGlobalScopes()->where('business_id', $businessId)
                ->where('invoice_id', (int) $f->id)->orderBy('attempt_n')->get();
            $n = $tentativas->count();
            $linha = [
                'fatura_id' => (int) $f->id,
                'numero_documento' => (string) $f->numero_documento,
                'vencimento' => substr((string) $f->vencimento, 0, 10),
                'valor_centavos' => CobradorPixAutomatico::centavos((string) $f->valor),
                'tentativa' => $n + 1,
                'acao' => 'cobrar',
                'motivo' => null,
            ];

            $ultima = $tentativas->last();
            if ($tentativas->contains(fn ($t) => in_array($t->status, ['succeeded', 'sent', 'pending'], true))) {
                $linha['acao'] = 'ja_cobrada';
            } elseif ($tentativas->contains(fn ($t) => $t->status === 'hard_decline')) {
                $linha['acao'] = 'recusa_definitiva';
            } elseif ($n >= $limite) {
                $linha['acao'] = 'tentativas_esgotadas';
            } elseif ($ultima !== null) {
                $proxima = Carbon::parse($ultima->created_at)->startOfDay()->addDays($intervalos[$n - 1]);
                if ($hoje->lt($proxima)) {
                    $linha['acao'] = 'aguardando';
                    $linha['motivo'] = 'próxima tentativa em '.$proxima->toDateString();
                }
            }

            if ($linha['acao'] !== 'cobrar' || $dryRun) {
                $resultado[] = $linha;

                continue;
            }

            $credencial ??= PaymentGatewayCredential::query()->where('business_id', $businessId)
                ->where('gateway_key', 'asaas')->where('ativo', true)->orderByDesc('id')->first();
            if ($credencial === null) {
                $linha['acao'] = 'sem_credencial';
                $resultado[] = $linha;

                continue;
            }

            $resultado[] = $this->tentar($businessId, $f, $linha, $credencial);
        }

        return $resultado;
    }

    /** @param array<string,mixed> $linha */
    private function tentar(int $businessId, object $f, array $linha, PaymentGatewayCredential $credencial): array
    {
        // SUPERADMIN: idem — cartão lido no business explícito.
        $cartao = CartaoSalvo::withoutGlobalScopes()->where('business_id', $businessId)->findOrFail((int) $f->cartao_id);
        $chave = "rb-cartao-{$f->id}-{$linha['tentativa']}";
        $status = 'sent';
        $codigo = null;
        $resposta = null;

        try {
            $res = (new PaymentGatewayService())->comCredencial($credencial)->cobrarCartao(
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
                new CardToken((string) $cartao->token, (string) $cartao->bandeira, (string) $cartao->ultimos_4, '', '', ''),
            );
            $resposta = ['status' => $res->payloadGateway['status'] ?? null, 'id' => $res->gatewayExternalId];
            if (in_array($resposta['status'], ['CONFIRMED', 'RECEIVED'], true)) {
                $status = 'succeeded';
            }
        } catch (CardDeclinedException $e) {
            $mensagem = self::legivel($e->getMessage());
            $status = self::recusaDefinitiva($mensagem) ? 'hard_decline' : 'soft_decline';
            $codigo = 'card_declined';
            $linha['motivo'] = mb_substr($mensagem, 0, 200);
        } catch (\Throwable $e) {
            $status = 'failed';
            $codigo = 'gateway_error';
            $linha['motivo'] = mb_substr($e->getMessage(), 0, 200);
        }

        if ($status === 'soft_decline' || $status === 'hard_decline' || $status === 'failed') {
            // SUPERADMIN: a cobrança que o PaymentGateway deixou `pending` vira `erro`, no business explícito.
            Cobranca::withoutGlobalScopes()->where('business_id', $businessId)->where('idempotency_key', $chave)
                ->update(['status' => 'erro']);
        }

        ChargeAttempt::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'invoice_id' => (int) $f->id, 'gateway' => 'asaas',
            'attempt_n' => $linha['tentativa'], 'status' => $status,
            'request_json' => ['valor_centavos' => $linha['valor_centavos'], 'cartao_salvo_id' => $cartao->id, 'idempotency_key' => $chave],
            'response_json' => $resposta, 'error_code' => $codigo, 'error_message' => $linha['motivo'],
        ]);

        if ($status === 'hard_decline') {
            $this->avisarCliente($businessId, (int) $f->contact_id, (string) $f->numero_documento, (string) $cartao->ultimos_4);
        }
        $linha['acao'] = $status;

        return $linha;
    }

    private function avisarCliente(int $businessId, int $contactId, string $documento, string $ultimos4): void
    {
        try {
            app(NotificadorClienteWhatsapp::class)->notificar($businessId, $contactId, 'billing',
                "O cartão final {$ultimos4} foi recusado na cobrança da fatura {$documento} e não vamos tentar de novo nele. "
                .'Fale com a gente para cadastrar outro cartão ou pagar de outra forma.');
        } catch (\Throwable $e) {
            Log::warning('[rb.cartao] aviso de recusa definitiva não saiu', ['business_id' => $businessId, 'erro' => $e->getMessage()]);
        }
    }

    /** @return list<int> dias entre tentativas (`recurringbilling.cartao_retry_dias`, padrão 1, 3, 7) */
    public static function intervalos(): array
    {
        $cfg = config('recurringbilling.cartao_retry_dias', '1,3,7');
        $lista = is_array($cfg) ? $cfg : explode(',', (string) $cfg);

        return array_values(array_filter(array_map('intval', $lista), fn ($d) => $d > 0));
    }

    /** A mensagem do driver carrega o corpo JSON do gateway: `ã` volta a ser `ã`. */
    private static function legivel(string $mensagem): string
    {
        return (string) preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($m) => mb_chr((int) hexdec($m[1])), $mensagem);
    }

    /** Recusa definitiva quando a mensagem do gateway cita um dos termos de `cartao_recusa_definitiva`. */
    public static function recusaDefinitiva(string $mensagem): bool
    {
        $texto = mb_strtolower($mensagem);
        foreach ((array) config('recurringbilling.cartao_recusa_definitiva', []) as $termo) {
            if ($termo !== '' && str_contains($texto, mb_strtolower((string) $termo))) {
                return true;
            }
        }

        return false;
    }
}
