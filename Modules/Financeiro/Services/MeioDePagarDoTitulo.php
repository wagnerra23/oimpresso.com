<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Como o cliente paga um título a receber — para a régua de cobrança pôr na mensagem
 * (US-FIN-033 · lacuna P0 "régua com link de pagamento").
 *
 * Procura a cobrança AINDA PAGÁVEL (`emitida`/`vencida`) do próprio título
 * (`origem_type = 'fin_titulo'`, o "Gerar boleto" do Unificado) e, para título de venda, a da venda
 * de origem (`origem_type = 'sale'`). A mais recente vale. Paga, cancelada ou com erro não entra:
 * mandar o cliente pagar um Pix que já foi pago ou cancelado é pior do que não mandar nada.
 *
 * Só lê. Não emite cobrança nem gera link novo — título sem cobrança segue sem meio de pagar.
 * Tier 0 (ADR 0093): `business_id` explícito em toda consulta.
 */
class MeioDePagarDoTitulo
{
    /**
     * @return array{linha_digitavel: ?string, pix_copia_cola: ?string, boleto_url: ?string}|null
     */
    public function de(int $businessId, object $titulo): ?array
    {
        if (! Schema::hasTable('cobrancas')) {
            return null;
        }

        $cobranca = DB::table('cobrancas')
            ->where('business_id', $businessId)
            ->whereIn('status', ['emitida', 'vencida'])
            ->where(function ($q) use ($titulo) {
                $q->where(fn ($q) => $q->where('origem_type', 'fin_titulo')->where('origem_id', (int) $titulo->id));
                if (($titulo->origem ?? null) === 'venda' && ! empty($titulo->origem_id)) {
                    $q->orWhere(fn ($q) => $q->where('origem_type', 'sale')->where('origem_id', (int) $titulo->origem_id));
                }
            })
            ->orderByDesc('id')
            ->first(['linha_digitavel', 'pix_emv', 'boleto_pdf_url']);

        if ($cobranca === null) {
            return null;
        }

        $meio = [
            'linha_digitavel' => $this->texto($cobranca->linha_digitavel),
            'pix_copia_cola' => $this->texto($cobranca->pix_emv),
            'boleto_url' => $this->texto($cobranca->boleto_pdf_url),
        ];

        return array_filter($meio) === [] ? null : $meio;
    }

    /** Parágrafo pronto para o corpo do e-mail (vazio quando não há meio de pagar). */
    public function paragrafo(?array $meio): string
    {
        if ($meio === null) {
            return '';
        }
        $linhas = [];
        if ($meio['pix_copia_cola']) {
            $linhas[] = "Pix copia e cola: {$meio['pix_copia_cola']}";
        }
        if ($meio['linha_digitavel']) {
            $linhas[] = "Linha digitável do boleto: {$meio['linha_digitavel']}";
        }
        if ($meio['boleto_url']) {
            $linhas[] = "Boleto: {$meio['boleto_url']}";
        }

        return "\n\n".implode("\n", $linhas);
    }

    private function texto(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
