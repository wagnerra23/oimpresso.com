<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * US-OFICINA-014 · pagar pelo link da aprovação (decisão [W] 2026-10-27, opção a).
 *
 * Quando a OS fica pronta e a venda dela tem título a receber em aberto, a página pública da
 * aprovação mostra "Pagar agora". O clique gera, na hora, o link de pagamento do Financeiro
 * (`LinkDePagamentoDoTitulo`, US-FIN-081) para esse título e manda o cliente para `/pagar/{token}`.
 * A Oficina não guarda link nem token: o Financeiro guarda só o hash, e gerar outro fecha o anterior.
 *
 * Não calcula valor: mostra o `valor_aberto` gravado no título; a cobrança, o Pix/boleto e a baixa
 * são do Financeiro. Tier 0 (ADR 0093): tudo filtrado pelo `business_id` da OS (vindo do token assinado).
 */
class PagamentoDaOs
{
    public function ligado(): bool
    {
        return (bool) config('oficina-auto.pagar_pelo_link.enabled', false);
    }

    /** @return array{titulo_id:int, valor_aberto:float}|null */
    public function tituloEmAberto(ServiceOrder $os): ?array
    {
        if (! $os->transaction_id) {
            return null;
        }
        $titulo = DB::table('fin_titulos')
            ->where('business_id', (int) $os->business_id)
            ->where('origem', 'venda')
            ->where('origem_id', (int) $os->transaction_id)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('valor_aberto', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('vencimento')->orderBy('id')
            ->first(['id', 'valor_aberto']);

        return $titulo ? ['titulo_id' => (int) $titulo->id, 'valor_aberto' => round((float) $titulo->valor_aberto, 2)] : null;
    }

    /** URL da página pública de pagamento do título em aberto da OS, ou null se não houver. */
    public function linkParaPagar(ServiceOrder $os): ?string
    {
        $titulo = $this->tituloEmAberto($os);
        if ($titulo === null) {
            return null;
        }
        // Sem autor: o link nasce do cliente; o Financeiro registra a baixa em nome de um usuário da empresa.
        $link = app(LinkDePagamentoDoTitulo::class)->gerar((int) $os->business_id, $titulo['titulo_id']);

        return route('financeiro.pagamento-publico.mostrar', ['token' => $link['token']]);
    }
}
