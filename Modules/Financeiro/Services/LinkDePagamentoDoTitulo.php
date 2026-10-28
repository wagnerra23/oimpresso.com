<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Account;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;

/**
 * Link para o cliente pagar um título a receber (US-FIN-081).
 *
 * Gerar: o operador pede o link de um título a receber aberto ou parcial. Sai um token aleatório;
 * só o sha256 fica em `fin_titulo_links_pagamento`, válido por `dias_validade` dias. Gerar outro
 * fecha o anterior.
 *
 * Pagar (cliente, sem login): se o título já tem cobrança ainda pagável, a página mostra essa
 * (`MeioDePagarDoTitulo`) e nada é emitido. Senão emite Pix ou boleto pelo mesmo
 * `PaymentGatewayContract` do "Gerar boleto" do Unificado, com `origem_type = fin_titulo`: quando o
 * banco avisa o pagamento, `OnCobrancaPagaCreateFinanceiroTitulo` dá baixa neste título.
 *
 * Valor: o saldo em aberto do título, como está gravado (`valor_aberto`). Nada é recalculado aqui;
 * juros e multa de atraso não entram nesta versão.
 *
 * Tier 0 (ADR 0093): o token resolve a empresa; tudo depois filtra pelo `business_id` do link.
 */
final class LinkDePagamentoDoTitulo
{
    public const METODOS = ['pix', 'boleto'];

    /** Menor valor aceito pelo gateway para gerar o link, em centavos. */
    public const VALOR_MINIMO_CENTAVOS = 100;

    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly MeioDePagarDoTitulo $meio,
    ) {}

    public static function ligado(): bool
    {
        return (bool) config('financeiro.link_pagamento.enabled', false);
    }

    /**
     * @return array{token: string, expira_em: Carbon}
     * @throws \DomainException título que não vai ao cliente
     * @throws ModelNotFoundException título de outra empresa ou inexistente
     */
    public function gerar(int $businessId, int $tituloId, ?int $userId = null): array
    {
        $titulo = $this->titulo($businessId, $tituloId) ?? throw (new ModelNotFoundException())->setModel(Titulo::class, [$tituloId]);
        if ($titulo->tipo !== 'receber') {
            throw new \DomainException('Link de pagamento só vale para título a receber.');
        }
        if (! $this->pagavel($titulo)) {
            throw new \DomainException('Este título não tem valor em aberto.');
        }

        return DB::transaction(function () use ($businessId, $tituloId, $userId) {
            $agora = now();
            DB::table('fin_titulo_links_pagamento')->where('business_id', $businessId)->where('titulo_id', $tituloId)
                ->where('expira_em', '>', $agora)->update(['expira_em' => $agora, 'updated_at' => $agora]);

            $token = Str::random(48);
            $expira = $agora->copy()->addDays((int) config('financeiro.link_pagamento.dias_validade', 30));
            DB::table('fin_titulo_links_pagamento')->insert([
                'business_id' => $businessId, 'titulo_id' => $tituloId, 'token_hash' => self::hash($token),
                'expira_em' => $expira, 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
            ]);

            return ['token' => $token, 'expira_em' => $expira];
        });
    }

    /**
     * O que a página pública mostra: empresa, número, vencimento, valor em aberto e o meio de pagar
     * já emitido. Nada de documento, e-mail ou dado interno. Null para token desconhecido.
     *
     * @return array<string, mixed>|null
     */
    public function paraCliente(string $token): ?array
    {
        $link = $this->link($token);
        $titulo = $link ? $this->titulo((int) $link->business_id, (int) $link->titulo_id) : null;
        if ($titulo === null) {
            return null;
        }

        return [
            'empresa'      => (string) DB::table('business')->where('id', $link->business_id)->value('name'),
            'numero'       => (string) $titulo->numero,
            'vencimento'   => $titulo->vencimento,
            'valor_aberto' => round((float) $titulo->valor_aberto, 2),
            'situacao'     => $this->situacao($link, $titulo),
            'meio'         => $this->meio->de((int) $link->business_id, $titulo),
        ];
    }

    /**
     * @return array{linha_digitavel: ?string, pix_copia_cola: ?string, boleto_url: ?string}
     * @throws \DomainException o cliente não pode pagar por este link agora
     */
    public function pagar(string $token, string $metodo): array
    {
        if (! in_array($metodo, self::METODOS, true)) {
            throw new \DomainException('Escolha Pix ou boleto.');
        }
        $link = $this->link($token);
        $titulo = $link ? $this->titulo((int) $link->business_id, (int) $link->titulo_id) : null;
        $situacao = $titulo === null ? 'indisponivel' : $this->situacao($link, $titulo);
        if ($situacao !== 'aberto') {
            throw new \DomainException(match ($situacao) {
                'pago'    => 'Este título já está pago.',
                'vencido' => 'Este link venceu. Peça um novo à empresa.',
                default   => 'Este título não está mais disponível.',
            });
        }
        $biz = (int) $link->business_id;

        $existente = $this->meio->de($biz, $titulo);
        if ($existente !== null) {
            return $existente;
        }

        $valorCentavos = (int) round(((float) $titulo->valor_aberto) * 100);
        if ($valorCentavos < self::VALOR_MINIMO_CENTAVOS) {
            throw new \DomainException(sprintf(
                'O valor em aberto é menor que o mínimo de %s. Fale com a empresa.',
                'R$ '.number_format(self::VALOR_MINIMO_CENTAVOS / 100, 2, ',', '.'),
            ));
        }
        $conta = ContaBancaria::padraoParaCobranca($biz);
        $account = $conta ? Account::query()->where('business_id', $biz)->find($conta->getAttribute('account_id')) : null;
        if ($account === null) {
            throw new \DomainException('A empresa ainda não ativou o pagamento on-line. Fale com ela.');
        }

        // Vencimento nunca no passado: o banco exige hoje ou depois (mesma regra do "Gerar boleto").
        $venc = $titulo->vencimento && Carbon::parse($titulo->vencimento)->isFuture() ? Carbon::parse($titulo->vencimento) : now();
        $contato = $titulo->cliente_id
            ? DB::table('contacts')->where('business_id', $biz)->where('id', $titulo->cliente_id)->first(['name', 'tax_number', 'email'])
            : null;
        $input = new EmitirCobrancaInput(
            businessId: $biz,
            contactId: (int) ($titulo->cliente_id ?? 0),
            valorCentavos: $valorCentavos,
            vencimento: new \DateTimeImmutable($venc->toDateString()),
            descricao: 'Título ' . $titulo->numero,
            idempotencyKey: "fintitulo-link-{$metodo}-{$titulo->id}-{$valorCentavos}",
            origemType: 'fin_titulo',
            origemId: (int) $titulo->id,
            meta: [
                'payer_name'     => $contato->name ?? $titulo->cliente_descricao,
                'payer_cpf_cnpj' => $contato->tax_number ?? null,
                'payer_email'    => $contato->email ?? null,
            ],
        );

        try {
            $gw = $this->gateway->for($account);
            $res = $metodo === 'pix' ? $gw->emitirPix($input, 'cobv') : $gw->emitirBoleto($input);
        } catch (\Throwable $e) { // credencial, pagador inválido ou banco fora: o cliente vê só a mensagem
            report($e);
            throw new \DomainException('Não deu para gerar a cobrança agora. Tente de novo em alguns minutos.');
        }

        return [
            'linha_digitavel' => $res->linhaDigitavel,
            'pix_copia_cola'  => $res->pixEmv,
            'boleto_url'      => $res->boletoPdfUrl,
        ];
    }

    /** O título tem link de pagamento (usado pelo listener da baixa fora da empresa dona do SaaS). */
    public static function temLink(int $businessId, int $tituloId): bool
    {
        return DB::table('fin_titulo_links_pagamento')->where('business_id', $businessId)->where('titulo_id', $tituloId)->exists();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function link(string $token): ?object
    {
        return DB::table('fin_titulo_links_pagamento')->where('token_hash', self::hash($token))->first();
    }

    private function titulo(int $businessId, int $tituloId): ?object
    {
        return DB::table('fin_titulos')->where('business_id', $businessId)->where('id', $tituloId)->whereNull('deleted_at')
            ->first(['id', 'numero', 'tipo', 'status', 'origem', 'origem_id', 'cliente_id', 'cliente_descricao', 'valor_aberto', 'vencimento']);
    }

    private function pagavel(object $titulo): bool
    {
        return in_array($titulo->status, ['aberto', 'parcial'], true) && (float) $titulo->valor_aberto > 0.004;
    }

    private function situacao(object $link, object $titulo): string
    {
        if ($titulo->tipo !== 'receber' || $titulo->status === 'cancelado') {
            return 'indisponivel';
        }
        if (! $this->pagavel($titulo)) {
            return 'pago';
        }

        return Carbon::parse($link->expira_em)->isPast() ? 'vencido' : 'aberto';
    }
}
