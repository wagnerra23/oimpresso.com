<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Repository de leitura pra Cobrança UI (F3 Tela 1 /financeiro/cobranca).
 *
 * Multi-tenant Tier 0 — herda business_id global scope via HasBusinessScope
 * dos Models. Filtros via querystring shape conforme charter.
 *
 * Performance: limit 100 cobranças, eager load credential.contaBancaria.account
 * pra evitar N+1. Shape via $this->shape() helper (T-AP-5 LICOES).
 *
 * Idempotente / read-only. Usado dentro de Inertia::defer() no Controller.
 *
 * ADR 0144 · ADR 0170.
 */
class CobrancaQuery
{
    /**
     * Lista cobranças filtradas + shape pro frontend.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    public function listar(int $businessId, array $filtros = []): array
    {
        $query = Cobranca::query()
            ->where('business_id', $businessId)
            ->with(['credential:id,gateway_key,ambiente,conta_bancaria_id,nome_display'])
            ->orderByDesc('id')
            ->limit(100);

        $this->aplicarFiltros($query, $filtros);

        return $query->get()->map(fn (Cobranca $c) => $this->shape($c))->all();
    }

    /**
     * KPIs derivados: pago_mes / vencido / aberto + mandatos / mrr (PIX Aut.).
     *
     * @return array<string, mixed>
     */
    public function kpis(int $businessId, CarbonImmutable $hoje): array
    {
        $inicioMes = $hoje->startOfMonth();
        $fimMes = $hoje->endOfMonth();

        $pagoMes = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'paga')
            ->whereBetween('paga_em', [$inicioMes, $fimMes])
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_centavos), 0) as valor_centavos')
            ->first();

        $vencido = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'vencida')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_centavos), 0) as valor_centavos')
            ->first();

        $aberto = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'emitida')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_centavos), 0) as valor_centavos')
            ->first();

        $mandatos = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'pix_recv')
            ->whereIn('status', ['emitida', 'paga'])
            ->count();

        $mrrPago = (int) Cobranca::query()
            ->where('business_id', $businessId)
            ->where('origem_type', 'subscription_license')
            ->where('status', 'paga')
            ->whereBetween('paga_em', [$inicioMes, $fimMes])
            ->sum('valor_centavos');

        return [
            'pago_mes' => ['qtd' => (int) $pagoMes->qtd, 'valor' => $this->centavos($pagoMes->valor_centavos)],
            'vencido'  => ['qtd' => (int) $vencido->qtd, 'valor' => $this->centavos($vencido->valor_centavos)],
            'aberto'   => ['qtd' => (int) $aberto->qtd,  'valor' => $this->centavos($aberto->valor_centavos)],
            'mandatos_ativos' => $mandatos,
            'mrr_pago' => $this->centavos($mrrPago),
        ];
    }

    /**
     * Funil 5 etapas (UI-only derivação por status + regras simples).
     * Jobs reais de lembrete/cobrança ativa/protesto entram em Onda 5.
     *
     * @return array<string, mixed>
     */
    public function funil(int $businessId, CarbonImmutable $hoje): array
    {
        $aberto = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'emitida')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_centavos), 0) as valor_centavos')
            ->first();

        $lembrete = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'emitida')
            ->whereBetween('vencimento', [$hoje->addDay()->toDateString(), $hoje->addDays(3)->toDateString()])
            ->count();

        $cobrancaAtiva = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'emitida')
            ->where('vencimento', '<', $hoje->toDateString())
            ->where('vencimento', '>=', $hoje->subDays(5)->toDateString())
            ->count();

        $vencidoMais5d = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('status', 'vencida')
            ->where('vencimento', '<', $hoje->subDays(5)->toDateString())
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor_centavos), 0) as valor_centavos')
            ->first();

        $mandatosCancelados = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'pix_recv')
            ->where('status', 'cancelada')
            ->count();

        return [
            'aberto'         => ['qtd' => (int) $aberto->qtd, 'valor' => $this->centavos($aberto->valor_centavos)],
            'lembrete'       => ['qtd' => (int) $lembrete, 'desc' => '3d antes do vcto'],
            'cobranca_ativa' => ['qtd' => (int) $cobrancaAtiva, 'desc' => '1-5d após vcto'],
            'vencido_5d'     => ['qtd' => (int) $vencidoMais5d->qtd, 'valor' => $this->centavos($vencidoMais5d->valor_centavos)],
            'protesto'       => ['qtd' => 0, 'desc' => '30d+ (Onda 5)'],
            'mandatos_cancelados' => $mandatosCancelados,
        ];
    }

    /**
     * Gateways configurados pro filtro dropdown.
     *
     * @return Collection<int, array{key: string, nome: string, ambiente: string, ativo: bool}>
     */
    public function gateways(int $businessId): Collection
    {
        return PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->orderBy('gateway_key')
            ->get()
            ->map(fn (PaymentGatewayCredential $c) => [
                'id' => $c->id,
                'key' => $c->gateway_key,
                'nome' => $c->nome_display ?: $c->gateway_key,
                'ambiente' => $c->ambiente,
                'ativo' => (bool) $c->ativo,
            ]);
    }

    /**
     * @param  Builder<Cobranca>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (! empty($filtros['tipo'])) {
            if ($filtros['tipo'] === 'pix') {
                $query->whereIn('tipo', ['pix_cob', 'pix_cobv']);
            } else {
                $query->where('tipo', $filtros['tipo']);
            }
        }
        if (! empty($filtros['gateway'])) {
            $query->whereHas('credential', fn (Builder $q) => $q->where('gateway_key', $filtros['gateway']));
        }
        if (! empty($filtros['account_id'])) {
            $query->whereHas('credential', fn (Builder $q) => $q->where('conta_bancaria_id', (int) $filtros['account_id']));
        }
        if (! empty($filtros['origem'])) {
            $query->where('origem_type', $filtros['origem']);
        }
        if (! empty($filtros['busca'])) {
            $q = '%'.$filtros['busca'].'%';
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('payer_name', 'like', $q)
                    ->orWhere('payer_cpf_cnpj', 'like', $q)
                    ->orWhere('nosso_numero', 'like', $q)
                    ->orWhere('descricao', 'like', $q);
            });
        }
    }

    /**
     * Shape Eloquent → array Inertia (T-AP-5 LICOES — não vazar Eloquent).
     *
     * @return array<string, mixed>
     */
    private function shape(Cobranca $c): array
    {
        return [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'status' => $c->status,
            'gateway' => $c->credential?->gateway_key,
            'account_id' => $c->credential?->conta_bancaria_id,
            'contato' => $c->payer_name,
            'contato_doc' => $c->payer_cpf_cnpj,
            'valor' => $this->centavos($c->valor_centavos),
            'vencimento' => $c->vencimento?->toDateString(),
            'emitida_em' => $c->created_at?->toIso8601String(),
            'paga_em' => $c->paga_em?->toIso8601String(),
            'cancelada_em' => null,
            'origem_type' => $c->origem_type,
            'origem_id' => $c->origem_id,
            'origem_label' => $this->origemLabel($c),
            'nosso_numero' => $c->nosso_numero,
            'linha_digitavel' => $c->linha_digitavel,
            'codigo_barras' => $c->codigo_barras,
            'pix_emv' => $c->pix_emv,
            'pix_qr_code_path' => $c->pix_qr_code_path,
            'mandato_ciclo' => null,
            'mandato_inicio' => null,
            'mandato_proximo' => null,
            'card_brand' => null,
            'card_last4' => null,
            'card_3ds' => null,
            'erro_msg' => null,
            'cancelamento_motivo' => null,
        ];
    }

    private function origemLabel(Cobranca $c): ?string
    {
        if (! $c->origem_type || ! $c->origem_id) {
            return null;
        }

        return match ($c->origem_type) {
            'sale' => 'Venda #'.$c->origem_id,
            'invoice' => 'Fatura RB #'.$c->origem_id,
            'subscription_license' => 'Assinatura SaaS #'.$c->origem_id,
            default => null,
        };
    }

    private function centavos(int|string|null $centavos): float
    {
        return ((int) ($centavos ?? 0)) / 100;
    }
}
