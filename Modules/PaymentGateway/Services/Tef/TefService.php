<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Tef;

use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Contracts\TefDriver;
use Modules\PaymentGateway\Dto\TefRespostaTerminal;
use Modules\PaymentGateway\Models\TefTransacao;

/**
 * Maquininha integrada ao caixa (US-SELL-075) — camada independente da adquirente.
 *
 * Fluxo: o caixa chama `iniciar` com o valor; o driver manda para o terminal e
 * normalmente devolve `pendente`; a tela chama `consultar` até a resposta final.
 * Aprovada, a tela preenche a linha de pagamento com bandeira, NSU e autorização.
 *
 * Não grava pagamento nem mexe no total da venda: o valor cobrado é exatamente o
 * que o caixa pediu (centavos inteiros, sem arredondamento aqui). Quem grava o
 * pagamento é o store da venda, como já faz hoje com cartão digitado.
 *
 * Tier 0: toda leitura filtra business_id explícito (não depende de sessão).
 */
final class TefService
{
    /** @var array<string, class-string<TefDriver>> */
    private const DRIVERS = [
        'simulador' => SimuladorTefDriver::class,
    ];

    public static function habilitado(int $businessId): bool
    {
        if (! config('sells.tef.enabled')) {
            return false;
        }

        $empresas = (array) config('sells.tef.business_ids', []);

        return empty($empresas) || in_array($businessId, $empresas, true);
    }

    public function driver(): TefDriver
    {
        $key = (string) config('sells.tef.driver', 'simulador');
        $classe = self::DRIVERS[$key] ?? throw new \InvalidArgumentException("Driver TEF desconhecido: {$key}");

        return app($classe);
    }

    /**
     * @param array{valor_centavos:int, modalidade:string, parcelas?:int, idempotency_key:string, location_id?:int|null, terminal_id?:string|null} $dados
     */
    public function iniciar(int $businessId, ?int $userId, array $dados): TefTransacao
    {
        $existente = $this->buscarPorChave($businessId, $dados['idempotency_key']);
        if ($existente) {
            return $existente;
        }

        $driver = $this->driver();

        $tef = DB::transaction(fn () => TefTransacao::query()->create([
                'business_id'     => $businessId,
                'user_id'         => $userId,
                'location_id'     => $dados['location_id'] ?? null,
                'terminal_id'     => $dados['terminal_id'] ?? null,
                'driver'          => $driver->key(),
                'valor_centavos'  => $dados['valor_centavos'],
                'modalidade'      => $dados['modalidade'],
                'parcelas'        => $dados['parcelas'] ?? 1,
                'status'          => TefTransacao::PENDENTE,
                'idempotency_key' => $dados['idempotency_key'],
            ]));

        return $this->aplicar($tef, $this->chamar(fn () => $driver->iniciar($tef)));
    }

    public function consultar(int $businessId, int $id): ?TefTransacao
    {
        $tef = TefTransacao::query()
            ->where('business_id', $businessId)
            ->find($id);

        if (! $tef || $tef->finalizada()) {
            return $tef;
        }

        return $this->aplicar($tef, $this->chamar(fn () => $this->driver()->consultar($tef)));
    }

    private function buscarPorChave(int $businessId, string $chave): ?TefTransacao
    {
        return TefTransacao::query()
            ->where('business_id', $businessId)
            ->where('idempotency_key', $chave)
            ->first();
    }

    /** Falha de rede com a adquirente vira `erro` visível, nunca exceção muda. */
    private function chamar(callable $fn): TefRespostaTerminal
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return new TefRespostaTerminal(status: TefTransacao::ERRO, mensagem: 'Sem resposta da maquininha. Tente de novo.');
        }
    }

    private function aplicar(TefTransacao $tef, TefRespostaTerminal $r): TefTransacao
    {
        $tef->forceFill([
            'status'      => $r->status,
            'bandeira'    => $r->bandeira ?? $tef->bandeira,
            'nsu'         => $r->nsu ?? $tef->nsu,
            'autorizacao' => $r->autorizacao ?? $tef->autorizacao,
            'externo_id'  => $r->externoId ?? $tef->externo_id,
            'mensagem'    => $r->mensagem,
            'payload'     => $r->payload ?: $tef->payload,
        ])->save();

        return $tef;
    }
}
